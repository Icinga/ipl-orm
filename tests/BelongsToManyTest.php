<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Orm\Relation\BelongsToMany;
use ipl\Orm\Relations;
use ipl\Orm\Resolver;
use ipl\Sql\Test\SqlAssertions;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Book;
use RuntimeException;

class BelongsToManyTest extends \PHPUnit\Framework\TestCase
{
    use SqlAssertions;

    public function setUp(): void
    {
        $this->setUpSqlAssertions();
    }

    public function testResolveDefaultKeys()
    {
        $model = new Car();
        $relations = new Relations();
        $model->createRelations($relations);
        $expected = [
            [
                'from'          => 'car',
                'to'            => 'car_user',
                'candidate_key' => 'id',
                'foreign_key'   => 'car_id'
            ],
            [
                'from'          => 'car_user',
                'to'            => 'user',
                'candidate_key' => 'user_id',
                'foreign_key'   => 'id'
            ]
        ];
        $actual = [];
        foreach (
            $relations
                ->get('user')
                ->bindTo($model, 'car.user', $this->createStub(Resolver::class))
                ->resolve() as [$from, $to, $keys]
        ) {
            reset($keys);
            $actual[] = [
                'from'          => $from->getTableName(),
                'to'            => $to->getTableName(),
                'candidate_key' => current($keys),
                'foreign_key'   => key($keys)
            ];
        }
        $this->assertSame($expected, $actual);
    }

    public function testResolveRespectsCustomKeysInTroughModels()
    {
        $model = new Car();
        $relations = new Relations();
        $model->createRelations($relations);
        $expected = [
            [
                'from'          => 'car',
                'to'            => 'car_user',
                'candidate_key' => 'car_custom_foreign_key',
                'foreign_key'   => 'car_user_car_candidate_key'
            ],
            [
                'from'          => 'car_user',
                'to'            => 'user',
                'candidate_key' => 'car_user_user_candidate_key',
                'foreign_key'   => 'user_custom_foreign_key'
            ]
        ];
        $actual = [];
        foreach (
            $relations
                ->get('user_custom_keys')
                ->bindTo($model, 'car.user_custom_keys', $this->createStub(Resolver::class))
                ->resolve() as [$from, $to, $keys]
        ) {
            reset($keys);
            $actual[] = [
                'from'          => $from->getTableName(),
                'to'            => $to->getTableName(),
                'candidate_key' => current($keys),
                'foreign_key'   => key($keys)
            ];
        }
        $this->assertSame($expected, $actual);
    }

    public function testUniqueAliasesAreUsedToJoinThroughTables()
    {
        $profile = (new Query())
            ->setModel(new Group())
            ->with('group.user.group');

        $sql = <<<'SQL'
SELECT group.id,
       group.name,
       group_user_group.id AS group_user_group_id,
       group_user_group.name AS group_user_group_name
FROM group
    INNER JOIN user_group group_t_user_group ON group_t_user_group.group_id = group.id
    INNER JOIN user group_user ON group_user.id = group_t_user_group.user_id
    INNER JOIN user_group group_user_t_user_group ON group_user_t_user_group.user_id = group_user.id
    INNER JOIN group group_user_group ON group_user_group.id = group_user_t_user_group.group_id
SQL;

        $this->assertSql($sql, $profile->assembleSelect());
    }

    public function testGetThroughFilterReturnsAnEmptyChainByDefault()
    {
        $filter = (new BelongsToMany())->getThroughFilter();

        $this->assertInstanceOf(Filter\Chain::class, $filter);
        $this->assertTrue($filter->isEmpty(), 'Default through filter is not empty');
    }

    public function testSetThroughFilterWrapsABareConditionInAnAllChain()
    {
        $condition = Filter::equal('foo', 'bar');
        $filter = (new BelongsToMany())
            ->setThroughFilter($condition)
            ->getThroughFilter();

        $this->assertInstanceOf(Filter\All::class, $filter);
        $this->assertSame([$condition], iterator_to_array($filter));
    }

    public function testThroughFilterSupportsSourceAndJunctionReferencesAtAllTimes(): void
    {
        $resolver = new Resolver($this->createStub(Query::class));
        $target = new User();
        $source = new Car();

        $relation = (new BelongsToMany())
            ->setName('user')
            ->setTarget($target)
            ->through(CarUser::class)
            ->setThroughAlias('my_through')
            ->bindTo($source, 'car.user', $resolver);

        $this->assertSame(
            [
                'car' => $source,
                'car_user' => $relation->getThrough(),
                'my_through' => $relation->getThrough()
            ],
            $relation->getThroughFilterSubjects()
        );

        $reversed = iterator_to_array($relation->reverse($resolver))[0];

        $newSource = new User();
        $reversed->bindTo($newSource, 'user.car', $resolver);

        $this->assertSame(
            [
                'car' => $source,
                'user' => $newSource,
                'car_user' => $relation->getThrough(),
                'my_through' => $relation->getThrough()
            ],
            $reversed->getThroughFilterSubjects()
        );
    }

    public function testResolveYieldsJunctionAndTargetRelationsWithTheirFiltersAndJoinType()
    {
        $query = (new Query())->setModel(new Car());
        $resolver = $query->getResolver();

        $resolver->getRelations($query->getModel())->get('user')
            ->setJoinType('LEFT')
            ->setThroughFilter(Filter::equal('user_id', 5))
            ->setFilter(Filter::equal('username', 'root'))
            ->bindTo($query->getModel(), 'car.user', $resolver);

        $resolved = [];
        foreach ($resolver->resolveRelation('car.user')->resolve() as $key => $_) {
            $resolved[] = $key;
        }

        $this->assertCount(2, $resolved, 'A many-to-many relation must resolve to two joins');

        [$toJunction, $toTarget] = $resolved;

        // The join type is propagated to both joins
        $this->assertSame('LEFT', $toJunction->getJoinType());
        $this->assertSame('LEFT', $toTarget->getJoinType());

        // The junction sits between source and target
        $this->assertSame('car', $toJunction->getSource()->getTableName());
        $this->assertSame('car_user', $toJunction->getTarget()->getTableName());
        $this->assertSame('car_user', $toTarget->getSource()->getTableName());
        $this->assertSame('user', $toTarget->getTarget()->getTableName());

        // The junction join carries the through filter ...
        $throughFilter = iterator_to_array($toJunction->getFilter()->yieldRules());
        $this->assertNotEmpty($throughFilter, 'The junction join does not carry the through filter');
        $this->assertSame(
            'car_user.user_id',
            $throughFilter[0]->getColumn(),
            'The through filter column is incorrectly resolved'
        );

        // ... and the target join carries the relation filter
        $relationFilter = iterator_to_array($toTarget->getFilter()->yieldRules());
        $this->assertNotEmpty($relationFilter, 'The target join does not carry the relation filter');
        $this->assertSame(
            'user.username',
            $relationFilter[0]->getColumn(),
            'The relation filter column is incorrectly resolved'
        );
    }

    public function testSetTargetForeignKeyAcceptsNull()
    {
        $this->assertNull((new BelongsToMany())->setTargetForeignKey(null)->getTargetForeignKey());
    }

    public function testSetTargetCandidateKeyAcceptsNull()
    {
        $this->assertNull((new BelongsToMany())->setTargetCandidateKey(null)->getTargetCandidateKey());
    }

    public function testReverseYieldsAnInverseBelongsToManyPreservingTheJunctionAndSwappingTheKeys()
    {
        $source = new Book();
        $resolver = (new Query())->setModel($source)->getResolver();
        // Book->author: many-to-many through a plain junction with explicit keys; Author declares no inverse,
        // so it is created eagerly during reversal (which is where the key pairs must be exchanged)
        $forward = $resolver->getRelations($source)->get('author')->bindTo($source, 'book.author', $resolver);

        $reversed = iterator_to_array($forward->reverse($resolver));

        $this->assertCount(1, $reversed);
        $inverse = $reversed[0];

        $this->assertInstanceOf(BelongsToMany::class, $inverse);
        $this->assertSame('book', $inverse->getName());
        $this->assertSame($source, $inverse->getTarget());

        // The junction is preserved ...
        $this->assertSame($forward->getThroughClass(), $inverse->getThroughClass());
        $this->assertSame($forward->getThroughAlias(), $inverse->getThroughAlias());

        // ... and the source-side and target-side key pairs are exchanged as a whole
        $this->assertSame($forward->getTargetCandidateKey(), $inverse->getCandidateKey());
        $this->assertSame($forward->getTargetForeignKey(), $inverse->getForeignKey());
        $this->assertSame($forward->getCandidateKey(), $inverse->getTargetCandidateKey());
        $this->assertSame($forward->getForeignKey(), $inverse->getTargetForeignKey());
    }

    public function testReverseThrowsInCaseTheThroughTableIsDifferent(): void
    {
        $resolver = new Resolver($this->createStub(Query::class));

        $relation = (new BelongsToMany())
            ->setName('user')
            ->setTargetClass(User::class)
            ->through('car_user')
            ->bindTo(new Car(), 'car.user', $resolver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'The junction model of the relation "user" (%s) is not compatible'
            . ' with the junction model of the inverse relation (%s != %s)',
            Car::class,
            CarUser::class,
            'car_user'
        ));

        iterator_to_array($relation->reverse($resolver));
    }
}
