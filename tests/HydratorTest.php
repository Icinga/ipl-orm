<?php

namespace ipl\Tests\Orm;

use ipl\Tests\Sql\TestCase;

class HydratorTest extends TestCase
{
    public function testWhetherAmbiguousColumnsAreCorrectlyMapped(): void
    {
        $query = Subsystem::on(new TestConnection())
            ->with(['audit', 'audit.user']);

        $hydrator = $query->createHydrator();

        $subject = new Subsystem();
        $hydrator->hydrate(['subsystem_audit_user_id' => 2], $subject);

        $this->assertTrue(isset($subject->audit->user_id), 'Ambiguous columns are not mapped correctly');
        $this->assertSame($subject->audit->user_id, 2, 'Ambiguous columns are not mapped correctly');

        $this->assertTrue(isset($subject->audit->user->id), 'Ambiguous columns are not mapped correctly');
        $this->assertSame($subject->audit->user->id, 2, 'Ambiguous columns are not mapped correctly');
    }
}
