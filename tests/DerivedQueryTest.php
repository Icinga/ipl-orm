<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\NoopQuery;
use ipl\Orm\Query;
use ipl\Orm\Relations;
use ipl\Sql\Connection;
use ipl\Sql\Test\SqlAssertions;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Book;
use ipl\Tests\Orm\Lib\Model\Employee;
use ipl\Tests\Orm\Lib\Model\Loose;
use PHPUnit\Framework\TestCase;

class DerivedQueryTest extends TestCase
{
    use SqlAssertions;

    protected Connection $db;

    public function setUp(): void
    {
        $this->setUpSqlAssertions();
        $this->db = new Connection(['db' => 'sqlite', 'dbname' => ':memory:']);
        $this->executeSql(<<<'SQL'
            CREATE TABLE loose (id INTEGER, tenant_id INTEGER, coupler TEXT, label TEXT, PRIMARY KEY (tenant_id, id));
            CREATE TABLE relationship (id INTEGER PRIMARY KEY, coupler TEXT);
            INSERT INTO loose VALUES
                (1, 0, 'shared', 'source'), (2, 1, 'shared', 'sibling'), (3, 2, 'other', 'unrelated');
            INSERT INTO relationship VALUES (10, 'shared'), (11, 'shared'), (12, 'other');
            SQL);
    }

    public function testLazyRelationReturnsEachTargetOnce(): void
    {
        $source = Loose::on($this->db)->filter(Filter::equal('id', 1))->first();
        $targets = $source['relationship']->orderBy('id');

        $this->assertSame([10, 11], array_map(
            static fn(Model $target): int => $target['id'],
            iterator_to_array($targets, false),
        ));
        $this->assertSame(2, $targets->count());
        $this->assertSame(11, $targets->limit(1)->offset(1)->first()['id']);
    }

    public function testSelfFiltersCannotMatchAnotherSourceWithTheSameRelationKey(): void
    {
        $source = new class (['id' => 1, 'coupler' => 'shared']) extends Loose {
            public function getColumns(): array
            {
                return ['coupler', 'label'];
            }
        };
        $query = (new Query())->setDb($this->db);

        $this->assertEmpty(iterator_to_array(
            $query->derive('relationship', $source)->filter(Filter::equal('self.id', 2)),
        ));
        $this->assertEmpty(iterator_to_array(
            $query->derive('relationship', $source)->filter(Filter::equal('self.label', 'sibling')),
        ));
        $this->assertSame(2, $query->derive('relationship', $source)
            ->filter(Filter::equal('self.label', 'source'))->count());
    }

    public function testCustomPrimaryKeyIdentifiesTheSource(): void
    {
        $source = new class (['tenant_id' => 0, 'coupler' => 'shared']) extends Loose {
            public function getKeyName(): string
            {
                return 'tenant_id';
            }
        };
        $targets = (new Query())->setDb($this->db)->derive('relationship', $source);

        $this->assertSame(2, $targets->count());
        $this->assertSql(
            'SELECT relationship.id, relationship.coupler FROM relationship'
            . ' LEFT JOIN loose relationship_self ON relationship_self.coupler = relationship.coupler'
            . ' WHERE (relationship_self.coupler = ?) AND (relationship_self.tenant_id = ?)',
            $targets->assembleSelect(),
            ['shared', 0],
        );
    }

    public function testCompleteCompositePrimaryKeyIdentifiesTheSource(): void
    {
        $this->executeSql("INSERT INTO loose VALUES (0, 0, 'shared', 'zero'), (0, 3, 'shared', 'other tenant')");
        $source = new class (['tenant_id' => 0, 'id' => 0, 'coupler' => 'shared']) extends Loose {
            public function getKeyName(): array
            {
                return ['tenant_id', 'id'];
            }
        };
        $targets = (new Query())->setDb($this->db)->derive('relationship', $source);

        $this->assertSame(2, $targets->count());
        $this->assertSql(
            'SELECT relationship.id, relationship.coupler FROM relationship'
            . ' LEFT JOIN loose relationship_self ON relationship_self.coupler = relationship.coupler'
            . ' WHERE (relationship_self.coupler = ?) AND (relationship_self.tenant_id = ?)'
            . ' AND (relationship_self.id = ?)',
            $targets->assembleSelect(),
            ['shared', 0, 0],
        );
    }

    public function testBelongsToRelationIdentifiesTheSource(): void
    {
        $this->executeSql(<<<'SQL'
            CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT, password TEXT);
            CREATE TABLE profile (id INTEGER PRIMARY KEY, user_id INTEGER, given_name TEXT, surname TEXT);
            INSERT INTO user VALUES (1, 'admin', 'secret');
            INSERT INTO profile VALUES (10, 1, 'First', 'Profile'), (11, 1, 'Second', 'Profile');
            SQL);
        $source = Profile::on($this->db)->filter(Filter::equal('id', 10))->first();
        $query = (new Query())->setDb($this->db);

        $this->assertSame(1, $source['user']->first()['id']);
        $this->assertSame(1, $query->derive('user', $source)->count());
        $this->assertEmpty(iterator_to_array(
            $query->derive('user', $source)->filter(Filter::equal('self.id', 11)),
        ));
    }

    public function testCustomJunctionKeysRetainTheSourceIdentity(): void
    {
        $this->executeSql(<<<'SQL'
            CREATE TABLE book (id INTEGER PRIMARY KEY, book_no TEXT, title TEXT);
            CREATE TABLE author (author_ref INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE authorship (authored_book TEXT, authoring INTEGER);
            INSERT INTO book VALUES (1, 'shared', 'First'), (2, 'shared', 'Second');
            INSERT INTO author VALUES (10, 'First author'), (11, 'Second author');
            INSERT INTO authorship VALUES ('shared', 10), ('shared', 11);
            SQL);
        $source = new class (['id' => 1, 'book_no' => 'shared']) extends Book {
            public function getKeyName(): string
            {
                return 'id';
            }
        };
        $query = (new Query())->setDb($this->db);
        $targets = $query->derive('author', $source)->orderBy('author_ref');

        $this->assertSame([10, 11], array_map(
            static fn(Model $author): int => $author['author_ref'],
            iterator_to_array($targets, false),
        ));
        $this->assertSame(2, $targets->count());
        $this->assertEmpty(iterator_to_array(
            $query->derive('author', $source)->filter(Filter::equal('self.id', 2)),
        ));
    }

    public function testGroupedRelationRetainsTargetVisibilityAndRelationFilters(): void
    {
        $this->executeSql(<<<'SQL'
            CREATE TABLE employee (
                id INTEGER PRIMARY KEY, name TEXT, active TEXT, deleted TEXT, role TEXT,
                department_id TEXT, office_id INTEGER
            );
            INSERT INTO employee VALUES
                (10, 'Visible', 'y', 'n', 'developer', 'shared', NULL),
                (11, 'Deleted', 'y', 'y', 'developer', 'shared', NULL),
                (12, 'Inactive', 'n', 'n', 'developer', 'shared', NULL);
            SQL);
        $source = new class (['id' => 1, 'coupler' => 'shared']) extends Loose {
            public function createRelations(Relations $relations): void
            {
                $relations->hasMany('employee', Employee::class)
                    ->setCandidateKey('coupler')
                    ->setForeignKey('department_id')
                    ->setFilter(Filter::equal('active', 'y'));
            }
        };
        $targets = (new Query())->setDb($this->db)->derive('employee', $source);

        $this->assertSame([10], array_map(
            static fn(Model $employee): int => $employee['id'],
            iterator_to_array($targets, false),
        ));
    }

    public function testZeroRelationKeyLinksTheSource(): void
    {
        $this->executeSql("INSERT INTO loose VALUES (4, 3, '0', 'zero'); INSERT INTO relationship VALUES (13, '0')");
        $source = new Loose(['id' => 4, 'coupler' => 0]);
        $targets = (new Query())->setDb($this->db)->derive('relationship', $source);

        $this->assertSame(1, $targets->count());
        $this->assertSame(13, $targets->first()['id']);
    }

    public function testMissingPrimaryKeyRetainsRelationKeyFiltering(): void
    {
        $this->assertRelationKeyFiltering(new Loose(['coupler' => 'shared']));
        $this->assertRelationKeyFiltering(new Loose(['id' => null, 'coupler' => 'shared']));
    }

    public function testIncompleteCompositePrimaryKeyRetainsRelationKeyFiltering(): void
    {
        $source = new class (['tenant_id' => 0, 'coupler' => 'shared']) extends Loose {
            public function getKeyName(): array
            {
                return ['tenant_id', 'id'];
            }
        };

        $this->assertRelationKeyFiltering($source);
        $source['id'] = null;
        $this->assertRelationKeyFiltering($source);
    }

    public function testModelWithoutAPrimaryKeyRetainsRelationKeyFiltering(): void
    {
        $source = new class (['coupler' => 'shared']) extends Loose {
            public function getKeyName(): array
            {
                return [];
            }
        };

        $this->assertRelationKeyFiltering($source);
    }

    public function testMissingRelationKeyReturnsAnEmptyQueryEvenWithAPrimaryKey(): void
    {
        $query = (new Query())->setDb($this->db);

        foreach ([new Loose(['id' => 1]), new Loose(['id' => 1, 'coupler' => null])] as $source) {
            $targets = $query->derive('relationship', $source);
            $this->assertInstanceOf(NoopQuery::class, $targets);
            $this->assertEmpty(iterator_to_array($targets));
        }
    }

    /**
     * Execute the fixture statements
     *
     * @param string $sql
     *
     * @return void
     */
    protected function executeSql(string $sql): void
    {
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->db->prepexec($statement);
            }
        }
    }

    /**
     * Assert that a source without its complete primary key uses the relation keys
     *
     * @param Loose $source
     *
     * @return void
     */
    protected function assertRelationKeyFiltering(Loose $source): void
    {
        $targets = (new Query())->setDb($this->db)->derive('relationship', $source);

        $this->assertNotInstanceOf(NoopQuery::class, $targets);
        $this->assertSql(
            'SELECT relationship.id, relationship.coupler FROM relationship'
            . ' LEFT JOIN loose relationship_self ON relationship_self.coupler = relationship.coupler'
            . ' WHERE relationship_self.coupler = ?',
            $targets->assembleSelect(),
            ['shared'],
        );
    }
}
