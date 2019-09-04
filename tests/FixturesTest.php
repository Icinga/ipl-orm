<?php

namespace ipl\Tests\Orm;

use ipl\Sql\Connection;

class FixturesTest extends \PHPUnit\Framework\TestCase
{
    public function testHydratedModelClassIsCorrect()
    {
        foreach (User::on($this->db)->execute() as $user) {
            /** @noinspection PhpParamsInspection */
            $this->assertInstanceOf(User::class, $user);
        }
    }

    public function testHydratedRelationClassIsCorrect()
    {
        foreach (User::on($this->db)->with('profile')->execute() as $user) {
            /** @noinspection PhpParamsInspection */
            $this->assertInstanceOf(Profile::class, $user->profile);
        }
    }

    /** @var Connection */
    protected $db;

    public function setUp()
    {
        $db = new Connection([
            'db'     => 'sqlite',
            'dbname' => ':memory:'
        ]);

        $fixtures = file_get_contents(__DIR__ . '/fixtures.sql');

        $db->exec($fixtures);

        $this->db = $db;
    }
}
