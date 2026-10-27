<?php

namespace Test\Michel\SqlMapper;

use Michel\SqlMapper\Graph\Builder\GraphBuilder;
use Michel\SqlMapper\Graph\Builder\Table;
use Michel\SqlMapper\QL\JoinQL;
use Michel\UniTester\TestCase;
use PDO;

/**
 * A query without join skips GraphBuilder: its result must stay exactly the one GraphBuilder gives.
 */
class JoinQLWithoutJoinTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_EMULATE_PREPARES => false]);
        $this->connection->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name VARCHAR(255), age INTEGER, email VARCHAR(255))');
        $insert = $this->connection->prepare('INSERT INTO user (id, name, age, email) VALUES (?, ?, ?, ?)');
        foreach ([[1, 'Alice', 30, 'alice@example.com'], [2, 'Bob', null, null], [3, 'Chloe', 25, 'chloe@example.com']] as $user) {
            $insert->execute($user);
        }
    }

    protected function tearDown(): void
    {
    }

    protected function execute(): void
    {
        $this->testSameResultAsGraphBuilder();
        $this->testSameResultWithIterator();
        $this->testShortenedAliasesAreRestored();
        $this->testPrimaryKeyNotFirstFallsBackToGraphBuilder();
        $this->testEmptyResult();
    }

    private function testSameResultAsGraphBuilder(): void
    {
        $joinQl = (new JoinQL($this->connection))
            ->select('user', 'u', ['id', 'name', 'age', 'email'])
            ->orderBy('u.id');

        $this->assertStrictEquals($this->withGraphBuilder($joinQl), $joinQl->getResult());
    }

    private function testSameResultWithIterator(): void
    {
        $joinQl = (new JoinQL($this->connection))
            ->select('user', 'u', ['id', 'name', 'age', 'email'])
            ->orderBy('u.id');

        $this->assertStrictEquals($this->withGraphBuilder($joinQl), iterator_to_array($joinQl->getResultIterator(), false));
        $this->assertStrictEquals($this->withGraphBuilder($joinQl)[0], $joinQl->getOneOrNullResult());
    }

    private function testShortenedAliasesAreRestored(): void
    {
        // "u__email" is longer than 5 characters: selected under a shortened alias, returned under its name.
        $rows = (new JoinQL($this->connection, 'id', 5))
            ->select('user', 'u', ['id', 'name', 'email'])
            ->orderBy('u.id')
            ->getResult();

        $this->assertStrictEquals(['id', 'name', 'email'], array_keys($rows[0]));
        $this->assertStrictEquals('alice@example.com', $rows[0]['email']);
    }

    private function testPrimaryKeyNotFirstFallsBackToGraphBuilder(): void
    {
        $joinQl = (new JoinQL($this->connection))
            ->select('user', 'u', ['name', 'id'])
            ->orderBy('u.id');

        $this->assertStrictEquals($this->withGraphBuilder($joinQl), $joinQl->getResult());
    }

    private function testEmptyResult(): void
    {
        $joinQl = (new JoinQL($this->connection))
            ->select('user', 'u', ['id', 'name'])
            ->where('u.id > 100');

        $this->assertStrictEquals([], $joinQl->getResult());
        $this->assertNull($joinQl->getOneOrNullResult());
    }

    private function withGraphBuilder(JoinQL $joinQl): array
    {
        $rows = $this->connection->query($joinQl->getQuery())->fetchAll(PDO::FETCH_ASSOC);
        return (new GraphBuilder($rows, [new Table('user', 'u')], 'id'))->buildGraph();
    }
}
