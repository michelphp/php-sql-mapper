<?php

namespace Test\Michel\SqlMapper;

use Michel\SqlMapper\Expression\Expr;
use Michel\UniTester\TestCase;

class ExprTest extends TestCase
{
    protected function setUp(): void
    {
        // TODO: Implement setUp() method.
    }

    protected function tearDown(): void
    {
        // TODO: Implement tearDown() method.
    }

    protected function execute(): void
    {
        $this->testEqual();
        $this->testNotEqual();
        $this->testGreaterThan();
        $this->testGreaterThanEqual();
        $this->testLowerThan();
        $this->testLowerThanEqual();
        $this->testIsNull();
        $this->testIsNotNull();
        $this->testIn();
        $this->testNotIn();
        $this->testInPlaceholders();
        $this->testInEscapesQuotes();
        $this->testInRejectsUnsafeValues();
        $this->testInEscapingAgainstTheDatabase();
    }
    public function testEqual()
    {
        $this->assertEquals('id = 1', Expr::equal('id', '1'));
    }

    public function testNotEqual()
    {
        $this->assertEquals('name <> John', Expr::notEqual('name', 'John'));
    }

    public function testGreaterThan()
    {
        $this->assertEquals('quantity > 10', Expr::greaterThan('quantity', '10'));
    }

    public function testGreaterThanEqual()
    {
        $this->assertEquals('price >= 100', Expr::greaterThanEqual('price', '100'));
    }

    public function testLowerThan()
    {
        $this->assertEquals('age < 30', Expr::lowerThan('age', '30'));
    }

    public function testLowerThanEqual()
    {
        $this->assertEquals('score <= 80', Expr::lowerThanEqual('score', '80'));
    }

    public function testIsNull()
    {
        $this->assertEquals('description IS NULL', Expr::isNull('description'));
    }

    public function testIsNotNull()
    {
        $this->assertEquals('status IS NOT NULL', Expr::isNotNull('status'));
    }

    public function testIn()
    {
        $this->assertEquals('category IN (1, 2, 3)', Expr::in('category', [1, 2, 3]));
    }

    public function testNotIn()
    {
        $this->assertEquals("color NOT IN ('red', 'blue')", Expr::notIn('color', ['red', 'blue']));
    }

    public function testInPlaceholders()
    {
        $this->assertEquals('id IN (:a, :id_2, ?)', Expr::in('id', [':a', ':id_2', '?']));
        $this->assertEquals('price IN (1.5, -2)', Expr::in('price', [1.5, -2]));
    }

    public function testInEscapesQuotes()
    {
        $this->assertEquals("name IN ('O''Brien')", Expr::in('name', ["O'Brien"]));
        $this->assertEquals("name IN ('x'') OR 1=1 --')", Expr::in('name', ["x') OR 1=1 --"]));
        $this->assertEquals("name NOT IN ('x'') OR 1=1 --')", Expr::notIn('name', ["x') OR 1=1 --"]));
        // Only a whole name is a placeholder: the rest of this value is data.
        $this->assertEquals("name IN (':a'') OR 1=1 --')", Expr::in('name', [":a') OR 1=1 --"]));
    }

    public function testInRejectsUnsafeValues()
    {
        foreach (["\\' OR 1=1 --", "a\0b", null, true, [1], INF, NAN] as $value) {
            $rejected = false;
            try {
                Expr::in('name', [$value]);
            } catch (\InvalidArgumentException $e) {
                $rejected = true;
            }
            $this->assertTrue($rejected, var_export($value, true) . ' should be rejected');
        }
    }

    public function testInEscapingAgainstTheDatabase()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE user (name VARCHAR(255))');
        $pdo->exec("INSERT INTO user (name) VALUES ('O''Brien'), ('alice')");

        $names = $pdo->query('SELECT name FROM user WHERE ' . Expr::in('name', ["O'Brien"]))->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertEquals(["O'Brien"], $names);

        $names = $pdo->query('SELECT name FROM user WHERE ' . Expr::in('name', ["x') OR 1=1 --"]))->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertEquals([], $names);
    }

}
