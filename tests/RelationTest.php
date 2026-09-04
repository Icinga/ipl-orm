<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Orm\Relation;
use ipl\Orm\Relation\BelongsTo;
use ipl\Orm\Relation\HasMany;
use ipl\Orm\Relation\HasOne;
use ipl\Orm\Resolver;
use ipl\Sql\Connection;
use ipl\Sql\Test\SqlAssertions;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Department;
use ipl\Tests\Orm\Lib\Model\Loose;
use ipl\Tests\Orm\Lib\Model\RestrictedUser;
use LogicException;
use RuntimeException;

class RelationTest extends \PHPUnit\Framework\TestCase
{
    use SqlAssertions;

    public function setUp(): void
    {
        $this->setUpSqlAssertions();
    }

    public function testGetNameReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getName());
    }

    public function testGetNameReturnsCorrectNameIfSet()
    {
        $name = 'relation';
        $relation = (new Relation())
            ->setName($name);

        $this->assertSame($name, $relation->getName());
    }

    public function testGetForeignKeyReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getForeignKey());
    }

    public function testGetForeignKeyReturnsCorrectArrayIfArrayHasBeenSet()
    {
        $foreignKey = ['foreign', 'key'];
        $relation = (new Relation())
            ->setForeignKey($foreignKey);

        $this->assertSame($foreignKey, $relation->getForeignKey());
    }

    public function testGetForeignKeyReturnsCorrectStringIfStringHasBeenSet()
    {
        $foreignKey = 'foreign_key';
        $relation = (new Relation())
            ->setForeignKey($foreignKey);

        $this->assertSame($foreignKey, $relation->getForeignKey());
    }

    public function testGetCandidateKeyReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getCandidateKey());
    }

    public function testGetCandidateKeyReturnsCorrectArrayIfArrayHasBeenSet()
    {
        $candidateKey = ['candidate', 'key'];
        $relation = (new Relation())
            ->setCandidateKey($candidateKey);

        $this->assertSame($candidateKey, $relation->getCandidateKey());
    }

    public function testGetCandidateKeyReturnsCorrectStringIfStringHasBeenSet()
    {
        $candidateKey = 'candidate_key';
        $relation = (new Relation())
            ->setCandidateKey($candidateKey);

        $this->assertSame($candidateKey, $relation->getCandidateKey());
    }

    public function testGetTargetClassReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getTargetClass());
    }

    public function testGetTargetClassReturnsCorrectTargetClassIfSet()
    {
        $targetClass = TestModel::class;
        $relation = (new Relation())
            ->setTargetClass($targetClass);

        $this->assertSame($targetClass, $relation->getTargetClass());
    }

    public function testGetDefaultCandidateKeyReturnsEmptyArrayIfSourceModelsPrimaryKeyIsUnset()
    {
        $candidateKey = Relation::getDefaultCandidateKey(new TestModel());

        $this->assertTrue(is_array($candidateKey));
        $this->assertEmpty($candidateKey);
    }

    public function testGetDefaultCandidateKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsAString()
    {
        $candidateKey = Relation::getDefaultCandidateKey(new TestModelWithPrimaryKey());

        $this->assertSame(['id'], $candidateKey);
    }

    public function testGetDefaultCandidateKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsCompound()
    {
        $candidateKey = Relation::getDefaultCandidateKey(new TestModelWithCompoundPrimaryKey());

        $this->assertSame(['i', 'd'], $candidateKey);
    }

    public function testGetDefaultForeignKeyReturnsEmptyArrayIfSourceModelsPrimaryKeyIsUnset()
    {
        $foreignKey = Relation::getDefaultForeignKey(new TestModel());

        $this->assertTrue(is_array($foreignKey));
        $this->assertEmpty($foreignKey);
    }

    public function testGetDefaultForeignKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsAString()
    {
        $foreignKey = Relation::getDefaultForeignKey(new TestModelWithPrimaryKey());

        $this->assertSame(['test_id'], $foreignKey);
    }

    public function testGetDefaultForeignKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsCompound()
    {
        $foreignKey = Relation::getDefaultForeignKey(new TestModelWithCompoundPrimaryKey());

        $this->assertSame(['test_i', 'test_d'], $foreignKey);
    }

    public function testGetTargetReturnsAnInstanceOfTheTargetClass()
    {
        $relation = (new Relation())
            ->setTargetClass(TestModel::class);

        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(TestModel::class, $relation->getTarget());
    }

    public function testGetTargetReturnsTheModelFromSetTarget()
    {
        $target = new TestModel();
        $relation = (new Relation())
            ->setTarget($target);

        $this->assertSame($target, $relation->getTarget());
    }

    public function testGetTargetPrefersTheModelFromSetTarget()
    {
        $target = new TestModel();
        $relation = (new Relation())
            ->setTarget($target)
            ->setTargetClass(TestModelWithPrimaryKey::class);

        $this->assertSame($target, $relation->getTarget());
    }

    public function testMultipleCallsToGetTargetAlwaysReturnsTheVerySameTargetInstance()
    {
        $relation = (new Relation())
            ->setTargetClass(TestModel::class);

        $target = $relation->getTarget();

        $this->assertSame($target, $relation->getTarget());
        $this->assertSame($target, $relation->getTarget());
    }

    public function testGetFilterReturnsAnEmptyChainByDefault()
    {
        $filter = (new Relation())->getFilter();

        $this->assertInstanceOf(Filter\Chain::class, $filter);
        $this->assertTrue($filter->isEmpty(), 'Default filter is not empty');
    }

    public function testSetFilterWrapsABareConditionInAnAllChain()
    {
        $condition = Filter::equal('foo', 'bar');
        $filter = (new Relation())
            ->setFilter($condition)
            ->getFilter();

        $this->assertInstanceOf(Filter\All::class, $filter);
        $this->assertSame([$condition], iterator_to_array($filter));
    }

    public function testSetFilterKeepsAChainAsIs()
    {
        $chain = Filter::any(Filter::equal('foo', 'bar'));
        $relation = (new Relation())
            ->setFilter($chain);

        $this->assertSame($chain, $relation->getFilter());
    }

    public function testResolveYieldsTheRelationItselfAsKey()
    {
        $relation = (new Relation())
            ->setName('test')
            ->setSource(new TestModelWithPrimaryKey())
            ->setTargetClass(TestModelWithPrimaryKey::class);

        $keys = [];
        foreach ($relation->resolve() as $key => $_) {
            $keys[] = $key;
        }

        $this->assertSame([$relation], $keys);
    }

    public function testGetReverseNameReturnsNullByDefault()
    {
        $this->assertNull((new Relation())->getReverseName());
    }

    public function testSetReverseNameSetsTheReverseName()
    {
        $this->assertSame('foo', (new Relation())->setReverseName('foo')->getReverseName());
    }

    public function testGetReverseClassFallsBackToTheRelationsOwnClass()
    {
        $this->assertSame(Relation::class, (new Relation())->getReverseClass());
        // Subclasses provide sensible defaults
        $this->assertSame(BelongsTo::class, (new HasMany())->getReverseClass());
        $this->assertSame(HasMany::class, (new BelongsTo())->getReverseClass());
    }

    public function testSetReverseClassOverridesTheDefault()
    {
        $this->assertSame(
            HasOne::class,
            (new BelongsTo())->setReverseClass(HasOne::class)->getReverseClass()
        );
    }

    public function testReverseThrowsIfTheRelationIsUnbound()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot reverse an unbound relation');

        (new HasMany())->reverse($this->createStub(Resolver::class));
    }

    public function testReverseReusesADeclaredInverseRelation()
    {
        $source = new Department();
        $resolver = (new Query())->setModel($source)->getResolver();
        // Binding qualifies the filter and registers the target alias, as resolveRelations() would
        $forward = $resolver->getRelations($source)
            ->get('employee')
            ->bindTo($source, 'department.employee', $resolver);

        $resolver->getRelations($forward->getTarget())
            ->get('department')
            ->setCandidateKey('office_id'); // Silly, but must be retained

        $inverse = $forward->reverse($resolver);

        // Employee declares a matching belongsTo 'department' (named after the source's table alias) which
        // is reused as the inverse and re-targeted at the very source instance
        $this->assertSame('office_id', $inverse->getCandidateKey());
        $this->assertSame('department', $inverse->getName());
        $this->assertSame($source, $inverse->getTarget());
    }

    public function testADeclaredInverseRelationCanBeReusedDuringReverse()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('select')->willReturnCallback(function () {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('setFetchMode')->with(\PDO::FETCH_ASSOC);
            $stmt->method('getIterator')->willReturn(new \ArrayIterator([
                ['id' => 1, 'coupler' => 'test']
            ]));

            return $stmt;
        });

        $loose = Loose::on($connection)
            ->filter(Filter::equal('id', 1))
            ->columns('id')
            ->first();

        $others = $loose->relationship->filter(Filter::unequal('loose.id', 1));

        $this->assertSql(
            <<<'SQL'
            SELECT relationship.id, relationship.coupler
            FROM relationship
            INNER JOIN loose relationship_self ON relationship_self.coupler = relationship.coupler
            WHERE (relationship_self.coupler = ?)
              AND ((relationship.id NOT IN ((SELECT sub_loose_relationship.id AS sub_loose_relationship_id
                 FROM loose sub_loose
                 LEFT JOIN relationship sub_loose_relationship ON sub_loose_relationship.coupler = sub_loose.coupler
                 WHERE (sub_loose.id = ?) AND (sub_loose_relationship.id IS NOT NULL)
                 GROUP BY sub_loose_relationship.id
                 HAVING COUNT(DISTINCT sub_loose.id) >= ?)) OR relationship.id IS NULL))
            SQL,
            $others->assembleSelect(),
            ['test', 1, 1]
        );
    }

    public function testReverseCreatesAnInverseRelationWhenNoneIsDeclared()
    {
        $source = new RestrictedUser();
        $resolver = (new Query())->setModel($source)->getResolver();
        // RestrictedGroup declares no relations, so the inverse has to be created eagerly
        $forward = $resolver->getRelations($source)
            ->get('restricted_group')
            ->bindTo($source, 'restricted_user.restricted_group', $resolver);

        $inverse = $forward->reverse($resolver);

        $this->assertInstanceOf(BelongsTo::class, $inverse);
        $this->assertSame('restricted_user', $inverse->getName());
        $this->assertSame($source, $inverse->getTarget());
        $this->assertInstanceOf(RestrictedUser::class, $inverse->getTarget());
    }

    public function testReverseThrowsIfADeclaredInverseTargetsAnIncompatibleModel()
    {
        $source = new Department();
        $forward = (new Query())->setModel($source)->getResolver()->getRelations($source)->get('employee')
            ->setSource($source)
            // Employee.office targets Office, but the source of this relation is a Department
            ->setReverseName('office');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not compatible with the target model of the inverse relation');

        $forward->reverse((new Query())->getResolver());
    }
}
