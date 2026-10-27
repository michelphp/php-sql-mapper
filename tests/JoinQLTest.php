<?php

namespace Test\Michel\SqlMapper;

use PDO;
use Michel\SqlMapper\QL\JoinQL;
use Michel\UniTester\TestCase;

class JoinQLTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO(
            'sqlite::memory:',
            null,
            null,
            [PDO::ATTR_EMULATE_PREPARES => false]
        );
        $this->setUpDatabaseSchema();
    }


    protected function tearDown(): void
    {
    }

    protected function execute(): void
    {
        $this->testRealRelations();
        $this->testSelect();
        $this->testAddSelect();
        $this->testWhere();
        $this->testOrderBy();
        $this->testLeftJoin();
        $this->testInnerJoin();
        $this->testWithLimit();
        $this->testWithParams();
        $this->testPaginationWithOffsetAndLimit();
        $this->testCloneHasItsOwnQuery();
    }

    public function testCloneHasItsOwnQuery(): void
    {
        $original = (new JoinQL($this->connection))->select('post', 'p', ['id', 'title']);
        $sql = $original->getQuery();

        $copy = clone $original;
        $copy->where('p.id = :id')->orderBy('p.title')->setParam('id', 1);

        $this->assertEquals($sql, $original->getQuery());
        $this->assertEquals($sql . ' WHERE p.id = :id ORDER BY p.title ASC', $copy->getQuery());
        $this->assertEquals(10, count($original->getResult()));
        $this->assertEquals(1, count($copy->getResult()));
    }

    public function testSelect(): void
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl->select('table', 'alias', ['column', 'column2' => 'alias__column']);
        $this->assertEquals('SELECT alias.column AS alias__column, alias.column2 AS alias__alias__column FROM table AS alias', $joinQl->getQuery());
    }

    public function testAddSelect(): void
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl->select('table', 'alias', ['column']);
        $joinQl->addSelect('alias', ['column']);
        $this->assertEquals('SELECT alias.column AS alias__column, alias.column AS alias__column FROM table AS alias', $joinQl->getQuery());
    }

    public function testWhere(): void
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl->select('table', 'alias', ['column']);
        $joinQl->where('alias.column = value');
        $this->assertEquals('SELECT alias.column AS alias__column FROM table AS alias WHERE alias.column = value', $joinQl->getQuery());
    }

    public function testOrderBy(): void
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl->select('table', 'alias', ['column']);
        $joinQl->orderBy('column');
        $this->assertEquals('SELECT alias.column AS alias__column FROM table AS alias ORDER BY column ASC', $joinQl->getQuery());

    }

    public function testLeftJoin(): void
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl->select('table', 'alias', ['column']);
        $joinQl->leftJoin('table', 'table2', 'alias2', ['column = column'], false, 'relation');
        $this->assertEquals('SELECT alias.column AS alias__column FROM table AS alias LEFT JOIN table2 alias2 ON column = column', $joinQl->getQuery());
    }

    public function testInnerJoin(): void
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl->select('table', 'alias', ['column']);
        $joinQl->innerJoin('table', 'table2', 'alias2', ['column = column'], false, 'relation');
        $this->assertEquals('SELECT alias.column AS alias__column FROM table AS alias INNER JOIN table2 alias2 ON column = column', $joinQl->getQuery());
    }

    private function testRealRelations(): void
    {
        $joinQl = new JoinQL($this->connection, 'id', 5);
        $joinQl
            ->select('user', 'u', ['id', 'firstname' => 'firstname', 'lastname', 'email' => 'email_address', 'password', 'is_active', 'created_at'])
            ->addSelect('p', ['id', 'title', 'user_id', 'content', 'created_at'])
            ->addSelect('t', ['id', 'name' => 'tag_name', 'post_id'])
            ->addSelect('c', ['id', 'body', 'post_id'])
            ->leftJoin('user', 'post', 'p', ['u.id = p.user_id'], true, 'posts', 'user_id')
            ->leftJoin('post', 'tag', 't', ['p.id = t.post_id'], true, 'tags', 'post_id')
            ->leftJoin('post', 'comment', 'c', ['p.id = c.post_id'], true, 'comments', 'post_id');

        foreach ($joinQl->getResult() as $row) {
            $this->testRowOneToMany($row);
        }
        $row = $joinQl->getOneOrNullResult();
        $this->testRowOneToMany($row);

        foreach ($joinQl->getResultIterator() as $row) {
            $this->testRowOneToMany($row);
        }
        $row = $joinQl->getOneOrNullResult();
        $this->testRowOneToMany($row);

        $joinQl = new JoinQL($this->connection);
        $joinQl
            ->select('post', 'p', ['id', 'title', 'user_id', 'content', 'created_at'])
            ->addSelect('u', ['id', 'firstname' , 'lastname', 'email' , 'password', 'is_active', 'created_at'])
            ->addSelect('t', ['id', 'name', 'post_id'])
            ->addSelect('c', ['id', 'body', 'post_id'])
            ->leftJoin('post', 'user', 'u', ['u.id = p.user_id'], false, 'user', 'user_id')
            ->leftJoin('post', 'tag', 't', ['p.id = t.post_id'], true, 'tags', 'post_id')
            ->leftJoin('post', 'comment', 'c', ['p.id = c.post_id'], true, 'comments', 'post_id')
            ->orderBy('p.id', 'desc')
            ->setMaxResults(3);
        $data = $joinQl->getResult();
        $this->assertEquals( 3 , count($data));
        foreach ($data as $row) {
            $this->assertTrue(array_key_exists('user', $row));
            $this->assertTrue(array_key_exists('comments', $row));
            $this->assertTrue(array_key_exists('tags', $row));
        }

        $count = $joinQl->count();
        $this->assertEquals( 10 , $count);
    }

    private function testWithLimit()
    {
        $joinQl = new JoinQL($this->connection);
        $joinQl
            ->select('post', 'p', ['id', 'title', 'user_id', 'content', 'created_at'])
            ->setMaxResults(1000000);
        $data = $joinQl->getResult();
        $this->assertEquals( 10 , count($data));

        $count = $joinQl->count();
        $this->assertEquals( 10 , $count);
    }


    public function testWithParams(): void {
        $joinQl = new JoinQL($this->connection);
        $joinQl
            ->select('post', 'p', ['id', 'title', 'user_id', 'content', 'created_at'])
            ->where('id > :id')
            ->setParam('id', 5)
            ->setMaxResults(1000000);
        $data = $joinQl->getResult();


        $this->assertEquals( 5 , count($data));
        $this->assertEquals( 5 , $joinQl->count());
    }


    public function testPaginationWithOffsetAndLimit(): void
    {
        $joinQl = new JoinQL($this->connection);

        // Page 1 : OFFSET 0 LIMIT 2
        $page1 = $joinQl
            ->select('post', 'p', ['id', 'title'])
            ->setFirstResult(0)
            ->setMaxResults(2)
            ->getResult();

        $this->assertEquals(2, count($page1));
        $this->assertEquals(1, $page1[0]['id']);
        $this->assertEquals(2, $page1[1]['id']);

        // Page 2 : OFFSET 2 LIMIT 2
        $joinQl = new JoinQL($this->connection);
        $page2 = $joinQl
            ->select('post', 'p', ['id', 'title'])
            ->setFirstResult(2)
            ->setMaxResults(2)
            ->getResult();

        $this->assertEquals(2, count($page2));
        $this->assertEquals(3, $page2[0]['id']);
        $this->assertEquals(4, $page2[1]['id']);

        // Vérifier que le count() ignore la pagination
        $total = $joinQl->count();
        $this->assertEquals(10, $total);
    }

    private function testRowOneToMany($row)
    {

        $this->assertTrue(is_array($row));
        $this->assertTrue(array_key_exists('id', $row));
        $this->assertTrue(array_key_exists('firstname', $row));
        $this->assertTrue(array_key_exists('lastname', $row));
        $this->assertTrue(array_key_exists('email_address', $row));
        $this->assertTrue(array_key_exists('password', $row));
        $this->assertTrue(array_key_exists('is_active', $row));
        $this->assertTrue(array_key_exists('posts', $row));
        $this->assertTrue(array_key_exists('tags', $row['posts'][0]));
        $this->assertTrue(array_key_exists('comments', $row['posts'][0]));
        $this->assertTrue(array_key_exists('tag_name', $row['posts'][0]['tags'][0]));
        $this->assertEquals(2, count($row['posts']));
    }

    protected function setUpDatabaseSchema(): void
    {
        $this->connection->exec('CREATE TABLE user (
                id INTEGER PRIMARY KEY,
                firstname VARCHAR(255),
                lastname VARCHAR(255),
                email VARCHAR(255),
                password VARCHAR(255),
                is_active BOOLEAN,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );');

        $this->connection->exec('CREATE TABLE post (
                id INTEGER PRIMARY KEY,
                user_id INTEGER,
                title VARCHAR(255),
                content VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES user (id)
            );');

        $this->connection->exec('CREATE TABLE tag (
                id INTEGER PRIMARY KEY,
                post_id INTEGER,
                name VARCHAR(255)
        )');
        $this->connection->exec('CREATE TABLE comment (
                id INTEGER PRIMARY KEY,
                post_id INTEGER,
                body VARCHAR(255)
        )');


        for ($i = 0; $i < 5; $i++) {
            $user = [
                'firstname' => 'John' . $i,
                'lastname' => 'Doe' . $i,
                'email' => $i . 'bqQpB@example.com',
                'password' => 'password123',
                'is_active' => true,
            ];

            $this->connection->exec("INSERT INTO user (firstname, lastname, email, password, is_active) VALUES (
                '{$user['firstname']}',
                '{$user['lastname']}',
                '{$user['email']}',
                '{$user['password']}',
                '{$user['is_active']}'
            )");
        }

        for ($i = 0; $i < 5; $i++) {
            $id = uniqid('post_', true);
            $post = [
                'user_id' => $i + 1,
                'title' => 'Post ' . $id,
                'content' => 'Content ' . $id,
            ];
            $this->connection->exec("INSERT INTO post (user_id, title, content) VALUES (
                '{$post['user_id']}',
                '{$post['title']}',
                '{$post['content']}'
            )");

            $id = uniqid('post_', true);
            $post = [
                'user_id' => $i + 1,
                'title' => 'Post ' . $id,
                'content' => 'Content ' . $id,
            ];
            $this->connection->exec("INSERT INTO post (user_id, title, content) VALUES (
                '{$post['user_id']}',
                '{$post['title']}',
                '{$post['content']}'
            )");
        }

        for ($i = 0; $i < 10; $i++) {
            $id = uniqid('tag_', true);
            $tag = [
                'post_id' => $i + 1,
                'name' => 'Tag ' . $id,
            ];
            $this->connection->exec("INSERT INTO tag (post_id, name) VALUES (
                '{$tag['post_id']}',
                '{$tag['name']}'
            )");

            $id = uniqid('tag_', true);
            $tag = [
                'post_id' => $i + 1,
                'name' => 'Tag ' . $id,
            ];
            $this->connection->exec("INSERT INTO tag (post_id, name) VALUES (
                '{$tag['post_id']}',
                '{$tag['name']}'
            )");
        }

        for ($i = 0; $i < 10; $i++) {
            $id = uniqid('comment_', true);
            $comment = [
                'post_id' => $i + 1,
                'body' => 'Comment ' . $id,
            ];
            $this->connection->exec("INSERT INTO comment (post_id, body) VALUES (
                '{$comment['post_id']}',
                '{$comment['body']}'
            )");
        }

    }
}
