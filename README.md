# PHP SQL Mapper

A powerful PHP library that not only builds complex SQL queries but also maps the results into structured object graphs. It simplifies handling relational data by automatically transforming flat result sets from joins into nested arrays, making it ideal for working with one-to-one and one-to-many relationships.

## Installation

You can install this library via [Composer](https://getcomposer.org/). Make sure your project meets the minimum PHP version requirement of 7.4 or higher.

```bash
composer require michel/php-sql-mapper
```

## Usage

The SQL Query Builder library allows you to build SQL queries fluently using an object-oriented approach. Here are some examples of usage:

### Creating a SELECT Query

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query
$query = QueryBuilder::select('name', 'email')
    ->from('users')
    ->where('status = "active"')
    ->orderBy('name')
    ->limit(10);

echo $query; // Outputs: SELECT name, email FROM users WHERE status = "active" ORDER BY name LIMIT 10
```

### Types of SQL Joins with QueryBuilder

The SQL Query Builder library supports various types of JOIN operations to combine rows from multiple tables based on a related column between them. Below are examples of different JOIN types you can use with `QueryBuilder`:

#### 1. INNER JOIN

An INNER JOIN returns records that have matching values in both tables.

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query with INNER JOIN
$query = QueryBuilder::select('u.name', 'a.address')
    ->from('users u')
    ->innerJoin('addresses a ON u.id = a.user_id');

echo $query; // Outputs: SELECT u.name, a.address FROM users u INNER JOIN addresses a ON u.id = a.user_id
```

#### 2. LEFT JOIN

A LEFT JOIN returns all records from the left table (first table) and the matched records from the right table (second table). If there is no match, the result is NULL on the right side.

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query with LEFT JOIN
$query = QueryBuilder::select('u.name', 'a.address')
    ->from('users u')
    ->leftJoin('addresses a ON u.id = a.user_id');

echo $query; // Outputs: SELECT u.name, a.address FROM users u LEFT JOIN addresses a ON u.id = a.user_id
```

#### 3. RIGHT JOIN

A RIGHT JOIN returns all records from the right table (second table) and the matched records from the left table (first table). If there is no match, the result is NULL on the left side.

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query with RIGHT JOIN
$query = QueryBuilder::select('u.name', 'a.address')
    ->from('users u')
    ->rightJoin('addresses a ON u.id = a.user_id');

echo $query; // Outputs: SELECT u.name, a.address FROM users u RIGHT JOIN addresses a ON u.id = a.user_id
```

### Creating a SELECT Query with DISTINCT

You can use the `distinct()` method to specify a `SELECT DISTINCT` query with QueryBuilder.

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query with DISTINCT using QueryBuilder
$query = QueryBuilder::select('name', 'email')
    ->distinct()
    ->from('users')
    ->where('status = "active"')
    ->orderBy('name')
    ->limit(10);

echo $query; // Outputs: SELECT DISTINCT name, email FROM users WHERE status = "active" ORDER BY name LIMIT 10
```

### Creating a SELECT Query with GROUP BY

You can use the `groupBy()` method to specify a `GROUP BY` clause with QueryBuilder.

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query with GROUP BY using QueryBuilder
$query = QueryBuilder::select('category_id', 'COUNT(*) as count')
    ->from('products')
    ->groupBy('category_id');

echo $query; // Outputs: SELECT category_id, COUNT(*) as count FROM products GROUP BY category_id
```

### Creating a SELECT Query with HAVING Clause

You can use the `having()` method to specify a `HAVING` clause with QueryBuilder.

```php
use Michel\SqlMapper\QueryBuilder;

// Create a SELECT query with HAVING using QueryBuilder
$query = QueryBuilder::select('category_id', 'COUNT(*) as count')
    ->from('products')
    ->groupBy('category_id')
    ->having('COUNT(*) > 5');

echo $query; // Outputs: SELECT category_id, COUNT(*) as count FROM products GROUP BY category_id HAVING COUNT(*) > 5
```


---

### Creating an INSERT Query

```php
use Michel\SqlMapper\QueryBuilder;

// Create an INSERT query
$query = QueryBuilder::insert('users')
    ->setValue('name', '"John Doe"')
    ->setValue('email', '"john.doe@example.com"')
    ->setValue('status', '"active"');

echo $query; // Outputs: INSERT INTO users (name, email, status) VALUES ("John Doe", "john.doe@example.com", "active")
```

### Creating an UPDATE Query

```php
use Michel\SqlMapper\QueryBuilder;

// Create an UPDATE query
$query = QueryBuilder::update('users')
    ->set('status', '"inactive"')
    ->where('id = 123');

echo $query; // Outputs: UPDATE users SET status = "inactive" WHERE id = 123
```

### Creating an DELETE Query

```php
use Michel\SqlMapper\QueryBuilder;

// Create a DELETE query
$query = QueryBuilder::delete('users')
    ->where('status = "inactive"');

echo $query; // Outputs: DELETE FROM users WHERE status = "inactive"
```

### Creating a SELECT Query with Custom Expression

```php
use Michel\SqlMapper\QueryBuilder;
use Michel\SqlMapper\Expression\Expr;

// Example of a query with a custom expression
$whereClause = Expr::greaterThan('age', '18');
$query = QueryBuilder::select('name', 'email')
    ->from('users')
    ->where($whereClause);

echo $query; // Outputs: SELECT name, email FROM users WHERE age > 18
```

### List of Available Expressions (`Expr`)

Here is a comprehensive list of available static methods in the `Expr` class along with examples demonstrating their usage:

#### `Expr::equal(string $key, string $value)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate an equal comparison expression
$equalExpr = Expr::equal('age', '30');
echo "Equal Expression: $equalExpr"; // Outputs: Equal Expression: age = 30
```

#### `Expr::notEqual(string $key, string $value)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate a not equal comparison expression
$notEqualExpr = Expr::notEqual('status', '"active"');
echo "Not Equal Expression: $notEqualExpr"; // Outputs: Not Equal Expression: status <> "active"
```

#### `Expr::greaterThan(string $key, string $value)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate a greater than comparison expression
$greaterThanExpr = Expr::greaterThan('salary', '50000');
echo "Greater Than Expression: $greaterThanExpr"; // Outputs: Greater Than Expression: salary > 50000
```

#### `Expr::greaterThanEqual(string $key, string $value)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate a greater than or equal comparison expression
$greaterThanEqualExpr = Expr::greaterThanEqual('points', '100');
echo "Greater Than or Equal Expression: $greaterThanEqualExpr"; // Outputs: Greater Than or Equal Expression: points >= 100
```

#### `Expr::lowerThan(string $key, string $value)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate a lower than comparison expression
$lowerThanExpr = Expr::lowerThan('price', '50');
echo "Lower Than Expression: $lowerThanExpr"; // Outputs: Lower Than Expression: price < 50
```

#### `Expr::lowerThanEqual(string $key, string $value)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate a lower than or equal comparison expression
$lowerThanEqualExpr = Expr::lowerThanEqual('quantity', '10');
echo "Lower Than or Equal Expression: $lowerThanEqualExpr"; // Outputs: Lower Than or Equal Expression: quantity <= 10
```

#### `Expr::isNull(string $key)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate an IS NULL expression
$isNullExpr = Expr::isNull('description');
echo "IS NULL Expression: $isNullExpr"; // Outputs: IS NULL Expression: description IS NULL
```

#### `Expr::isNotNull(string $key)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate an IS NOT NULL expression
$isNotNullExpr = Expr::isNotNull('created_at');
echo "IS NOT NULL Expression: $isNotNullExpr"; // Outputs: IS NOT NULL Expression: created_at IS NOT NULL
```

#### `Expr::in(string $key, array $values)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate an IN expression
$inExpr = Expr::in('category_id', [1, 2, 3]);
echo "IN Expression: $inExpr"; // Outputs: IN Expression: category_id IN (1, 2, 3)
```

#### `Expr::notIn(string $key, array $values)`

```php
use Michel\SqlMapper\Expression\Expr;

// Example: Generate a NOT IN expression
$notInExpr = Expr::notIn('role', ['"admin"', '"manager"']);
echo "NOT IN Expression: $notInExpr"; // Outputs: NOT IN Expression: role NOT IN ("admin", "manager")
```

These examples demonstrate how to use each `Expr` class method to generate SQL expressions for various comparison and conditional operations. Incorporate these methods into your SQL Query Builder usage to construct complex and precise SQL queries effectively.

---

## Relational Data Mapping with `JoinQL`

While the `QueryBuilder` is excellent for creating raw SQL queries, the `JoinQL` class provides a higher-level abstraction to automatically handle relational data. It is designed to transform flat result sets from complex queries with `JOINs` into structured, nested arrays (or object graphs), making it incredibly easy to work with one-to-one and one-to-many relationships.

### How It Works

`JoinQL` builds upon the `Select` query builder but adds a layer of "graph-aware" logic. When you define a join, you also specify the nature of the relationship (e.g., one-to-many) and the desired key for the nested data. After executing the query, `JoinQL` processes the results and intelligently groups child rows under their parent entities.

### Example: Fetching a User and All Their Posts (One-to-Many)

Imagine you have a `users` table and a `posts` table. Here’s how you can fetch a user and embed all their posts directly in the result, without manually looping through the results.

```php
use Michel\SqlMapper\QL\JoinQL;

// 1. Initialize JoinQL with your PDO connection
$pdo = new PDO('your_dsn', 'user', 'pass');
$joinQL = new JoinQL($pdo);

// 2. Build the query
$user = $joinQL
    // Start with the primary entity
    ->select('users', 'u', ['id', 'name'])
    
    // Join the related entity
    ->leftJoin(
        'u',                  // From table alias (the parent)
        'posts',              // To table (the child)
        'p',                  // To table alias
        ['u.id = p.user_id'], // Join condition
        true,                 // IS a one-to-many relationship
        'posts'               // The key for the nested posts array in the result
    )
    
    // Add conditions and parameters as usual
    ->where('u.id = :user_id')
    ->setParam('user_id', 1)
    
    // 3. Fetch the structured result
    ->getOneOrNullResult();

/*
The $user variable will contain a perfectly structured array:

[
    'id' => 1,
    'name' => 'John Doe',
    'posts' => [
        [
            'id' => 123,
            'title' => 'My First Post',
            'content' => '...'
        ],
        [
            'id' => 124,
            'title' => 'Another Post',
            'content' => '...'
        ]
    ]
]
*/
```

### Example: Fetching a Post and Its Author (One-to-One)

Here is how to handle a `one-to-one` relationship, where a post has a single author.

```php
use Michel\SqlMapper\\QL\JoinQL;

$pdo = new PDO('your_dsn', 'user', 'pass');
$joinQL = new JoinQL($pdo);

$post = $joinQL
    ->select('posts', 'p', ['id', 'title', 'content'])
    ->leftJoin(
        'p',                  // From table alias
        'users',              // To table
        'u',                  // To table alias
        ['p.user_id = u.id'], // Join condition
        false,                // NOT a one-to-many relationship (it's one-to-one)
        'author'              // The key for the nested author object
    )
    ->where('p.id = :post_id')
    ->setParam('post_id', 123)
    ->getOneOrNullResult();

/*
The $post variable will look like this:

[
    'id' => 123,
    'title' => 'My First Post',
    'content' => '...',
    'author' => [
        'id' => 1,
        'name' => 'John Doe'
    ]
]
*/
```

By using `JoinQL`, you delegate the complex task of structuring relational data to the library, resulting in cleaner, more readable application code.

## Features

- Fluent generation of SELECT, INSERT, UPDATE, and DELETE queries.
- Secure SQL query building to prevent SQL injection vulnerabilities.
- Support for WHERE, ORDER BY, GROUP BY, HAVING, LIMIT, and JOIN clauses.
- Simplified methods for creating custom SQL expressions.

## License

This library is open-source software licensed under the [MIT license](LICENSE).
