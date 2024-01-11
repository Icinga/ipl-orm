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
        $this->assertSame(2, $subject->audit->user_id, 'Ambiguous columns are not mapped correctly');

        $this->assertTrue(isset($subject->audit->user->id), 'Ambiguous columns are not mapped correctly');
        $this->assertSame(2, $subject->audit->user->id, 'Ambiguous columns are not mapped correctly');
    }

    public function testWhetherProperlyQualifiedColumnsAreOnlyPassedOnToMatchingTargets()
    {
        $query = Car::on(new TestConnection())
            ->with('user_custom_keys');

        $hydrator = $query->createHydrator();

        $subject = new Car();
        $hydrator->hydrate(['car_user_custom_keys_username' => 'foo'], $subject);

        $subject2 = new Car();
        $hydrator->hydrate(['car_user_custom_keys_username' => 'bar'], $subject2);

        $this->assertFalse(
            isset($subject->user->custom_keys_username),
            'This should not fail. If it does, there is a new issue'
        );

        $this->assertFalse(
            isset($subject2->user->custom_keys_username),
            'Properly qualified relation columns are spilled onto the base model'
        );
    }
}
