<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Compat\FilterProcessor;
use ipl\Orm\Query;
use ipl\Stdlib\Filter;

class FilterProcessorTest extends \PHPUnit\Framework\TestCase
{
    public function testUnequalDoesNotOverrideUnlike()
    {
        $query = new Query();
        $query->setModel(new Car());
        $query->setDb(new TestConnection());

        $filter = Filter::all(
            Filter::unequal('passenger.name', 'foo'),
            Filter::unlike('passenger.gender', 'b*r')
        );

        FilterProcessor::apply($filter, $query);

        $where = $query->getSelectBase()->getWhere();

        $this->assertArrayHasKey(1, $where);
        $this->assertArrayHasKey(0, $where[1]);
        $this->assertArrayHasKey(1, $where[1][0]);
        $this->assertArrayHasKey(0, $where[1][0][1]);
        $this->assertArrayHasKey(1, $where[1][0][1][0]);
        $this->assertArrayHasKey('(car.id NOT IN (?) OR car.id IS NULL)', $where[1][0][1][0][1]);
        $this->assertSame(
            ['AND', [
                ['AND', [
                    ['OR', [
                        ['AND', [
                            ['AND', [
                                'sub_passenger.name = ?' => 'foo'
                            ]]
                        ]]
                    ]]
                ]]
            ]],
            $where[1][0][1][0][1]['(car.id NOT IN (?) OR car.id IS NULL)']->getWhere()
        );

        $this->assertArrayHasKey(1, $where[1][0][1]);
        $this->assertArrayHasKey(1, $where[1][0][1][1]);
        $this->assertArrayHasKey('(car.id NOT IN (?) OR car.id IS NULL)', $where[1][0][1][1][1]);
        $this->assertSame(
            ['AND', [
                ['AND', [
                    ['OR', [
                        ['AND', [
                            ['AND', [
                                'sub_passenger.gender LIKE ?' => 'b%r'
                            ]]
                        ]]
                    ]]
                ]]
            ]],
            $where[1][0][1][1][1]['(car.id NOT IN (?) OR car.id IS NULL)']->getWhere()
        );
    }
}
