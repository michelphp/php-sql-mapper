<?php

namespace Test\Michel\SqlMapper;

use Michel\SqlMapper\QL\JoinQL;
use Michel\UniTester\TestCase;
use PDO;

/**
 * Reference behaviour of limit/offset with a one-to-many relation:
 * limit and offset count ROOT entities, never SQL rows, and every entity comes with all its children.
 *
 * Data set:
 *   1 Alice 30  posts A1, A2, A3
 *   2 Bob   25  posts B1
 *   3 Chloe 30  posts C1, C2
 *   4 David 40  posts D1
 *   5 Emma  25  (no post)
 *   6 Fred  35  posts F1, F2
 */
class JoinQLPaginationTest extends TestCase
{
    private PDO $connection;

    /**
     * @var callable|null
     */
    private $limitWriter = null;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_EMULATE_PREPARES => false]);
        $this->connection->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name VARCHAR(255), age INTEGER)');
        $this->connection->exec('CREATE TABLE post (id INTEGER PRIMARY KEY, user_id INTEGER, title VARCHAR(255))');

        $users = [
            1 => ['Alice', 30, ['A1', 'A2', 'A3']],
            2 => ['Bob', 25, ['B1']],
            3 => ['Chloe', 30, ['C1', 'C2']],
            4 => ['David', 40, ['D1']],
            5 => ['Emma', 25, []],
            6 => ['Fred', 35, ['F1', 'F2']],
        ];
        $insertUser = $this->connection->prepare('INSERT INTO user (id, name, age) VALUES (?, ?, ?)');
        $insertPost = $this->connection->prepare('INSERT INTO post (user_id, title) VALUES (?, ?)');
        foreach ($users as $id => [$name, $age, $titles]) {
            $insertUser->execute([$id, $name, $age]);
            foreach ($titles as $title) {
                $insertPost->execute([$id, $title]);
            }
        }
    }

    protected function tearDown(): void
    {
    }

    protected function execute(): void
    {
        // Offset skipped in PHP: works on any database.
        $this->limitWriter = null;
        $this->runAll();

        // Offset pushed to the database when the ids query has one row per entity.
        $this->limitWriter = static function (string $sql, ?int $limit, ?int $offset): string {
            return sprintf('%s LIMIT %d OFFSET %d', $sql, $limit ?? -1, $offset ?? 0);
        };
        $this->runAll();
    }

    private function runAll(): void
    {
        $this->testPageCountsEntitiesNotRows();
        $this->testLastPageIsPartial();
        $this->testOffsetBeyondTheEnd();
        $this->testFilterOnRelation();
        $this->testOrderByRootColumnWithTieBreaker();
        $this->testOrderByNonUniqueRootColumn();
        $this->testOrderByRelationColumn();
        $this->testCountIgnoresPagination();
    }

    public function testPageCountsEntitiesNotRows(): void
    {
        $rows = $this->usersWithPosts()
            ->orderBy('u.id')
            ->setFirstResult(2)
            ->setMaxResults(2)
            ->getResult();

        $this->assertEquals([3, 4], $this->ids($rows));
        $this->assertEquals(['C1', 'C2'], $this->titles($rows[0]));
        $this->assertEquals(['D1'], $this->titles($rows[1]));
    }

    public function testLastPageIsPartial(): void
    {
        $rows = $this->usersWithPosts()
            ->orderBy('u.id')
            ->setFirstResult(4)
            ->setMaxResults(10)
            ->getResult();

        $this->assertEquals([5, 6], $this->ids($rows));
        $this->assertEquals([], $this->titles($rows[0]));
        $this->assertEquals(['F1', 'F2'], $this->titles($rows[1]));
    }

    public function testOffsetBeyondTheEnd(): void
    {
        $rows = $this->usersWithPosts()
            ->orderBy('u.id')
            ->setFirstResult(10)
            ->setMaxResults(2)
            ->getResult();

        $this->assertEquals([], $rows);
    }

    public function testFilterOnRelation(): void
    {
        // Users having B1, C1 or F2: Bob, Chloe, Fred. Page 2 of size 2 starts at Chloe.
        $rows = $this->usersWithPosts(true)
            ->where("p.title IN ('B1', 'C1', 'F2')")
            ->orderBy('u.id')
            ->setFirstResult(1)
            ->setMaxResults(2)
            ->getResult();

        $this->assertEquals([3, 6], $this->ids($rows));
        $this->assertEquals(['C1'], $this->titles($rows[0]));
        $this->assertEquals(['F2'], $this->titles($rows[1]));
    }

    public function testOrderByRootColumnWithTieBreaker(): void
    {
        // age ASC, id ASC: Bob, Emma, Alice, Chloe, Fred, David
        $rows = $this->usersWithPosts()
            ->orderBy('u.age')
            ->orderBy('u.id')
            ->setFirstResult(1)
            ->setMaxResults(3)
            ->getResult();

        $this->assertEquals([5, 1, 3], $this->ids($rows));
        $this->assertEquals(['A1', 'A2', 'A3'], $this->titles($rows[1]));
        $this->assertEquals(['C1', 'C2'], $this->titles($rows[2]));
    }

    public function testOrderByNonUniqueRootColumn(): void
    {
        // Alice/Chloe and Bob/Emma share the same age: the order between them is not defined,
        // but walking every page must return each user exactly once, with all its posts.
        $seen = [];
        foreach ([0, 2, 4] as $offset) {
            $rows = $this->usersWithPosts()
                ->orderBy('u.age')
                ->setFirstResult($offset)
                ->setMaxResults(2)
                ->getResult();
            $this->assertCount(2, $rows);
            foreach ($rows as $row) {
                $seen[(int) $row['id']] = count($row['posts']);
            }
        }
        ksort($seen);

        $this->assertEquals([1 => 3, 2 => 1, 3 => 2, 4 => 1, 5 => 0, 6 => 2], $seen);
    }

    public function testOrderByRelationColumn(): void
    {
        // A user takes the position of its first row: title DESC gives Fred, David, Chloe, Bob, Alice, then Emma (NULL).
        $rows = $this->usersWithPosts()
            ->orderBy('p.title', 'DESC')
            ->orderBy('u.id')
            ->setFirstResult(1)
            ->setMaxResults(3)
            ->getResult();

        $this->assertEquals([4, 3, 2], $this->ids($rows));
        $this->assertEquals(['C1', 'C2'], $this->titles($rows[1]));
    }

    public function testCountIgnoresPagination(): void
    {
        $joinQl = $this->usersWithPosts(true)
            ->where("p.title IN ('B1', 'C1', 'F2')")
            ->setFirstResult(1)
            ->setMaxResults(1);
        $this->assertEquals(3, $joinQl->count());

        $joinQl = $this->usersWithPosts()
            ->setFirstResult(4)
            ->setMaxResults(1);
        $this->assertEquals(6, $joinQl->count());
    }

    private function usersWithPosts(bool $innerJoin = false): JoinQL
    {
        $joinQl = (new JoinQL($this->connection, 'id', null, $this->limitWriter))
            ->select('user', 'u', ['id', 'name', 'age'])
            ->addSelect('p', ['id', 'title', 'user_id']);

        if ($innerJoin) {
            return $joinQl->innerJoin('u', 'post', 'p', ['u.id = p.user_id'], true, 'posts', 'user_id');
        }
        return $joinQl->leftJoin('u', 'post', 'p', ['u.id = p.user_id'], true, 'posts', 'user_id');
    }

    private function ids(array $rows): array
    {
        return array_map(function (array $row) {
            return (int) $row['id'];
        }, $rows);
    }

    private function titles(array $row): array
    {
        $titles = array_column($row['posts'], 'title');
        sort($titles);
        return $titles;
    }
}
