<?php

namespace Michel\SqlMapper;

use LogicException;
use Michel\SqlMapper\Interfaces\QueryInterface;

/**
 * @package    php-query-builder
 * @author    PhpDevCommunity <dev@phpdevcommunity.com>
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link    https://www.phpdevcommunity.com
 */

/**
 * Class Select
 * Represents a SELECT query builder.
 */
final class Select implements QueryInterface
{

    /**
     * @var array List of fields to select
     */
    private array $fields = [];

    /**
     * @var array List of conditions for WHERE clause
     */
    private array $conditions = [];

    /**
     * @var array List of order fields for ORDER BY clause
     */
    private array $order = [];

    /**
     * @var array List of tables for FROM clause
     */
    private array $from = [];

    /**
     * @var array List of fields to group by
     */
    private array $groupBy = [];

    /**
     * @var array List of conditions for HAVING clause
     */
    private array $having = [];

    /**
     * @var ?int Offset for the query
     */
    private ?int $offset = null;

    /**
     * @var ?int|string Limit for the query
     */
    private  $limit = null;

    /**
     * @var bool Indicates if DISTINCT should be used
     */
    private bool $distinct = false;

    /**
     * @var array List of JOIN clauses
     */
    private array $join = [];

    /**
     * Select constructor.
     * @param array $select Initial fields to select
     */
    public function __construct(array $select)
    {
        $this->fields = $select;
    }

    /**
     * Add fields to select
     * @param string ...$select List of fields to select
     * @return $this
     */
    public function select(string ...$select): self
    {
        foreach ($select as $arg) {
            $this->fields[] = $arg;
        }
        return $this;
    }

    /**
     * Build the string representation of the query
     * @return string
     */
    public function __toString(): string
    {
        if ($this->from === []) {
            throw new LogicException('No table specified');
        }


        $fields = $this->fields;
        $distinct = $this->distinct;
        if ($distinct && count($fields) === 1 && stripos($fields[0], 'COUNT(') === 0) {
            if (stripos($fields[0], 'COUNT(DISTINCT') !== 0) {
                $fields[0] = str_ireplace('COUNT(', 'COUNT(DISTINCT ', $fields[0]);
            }
            $distinct = false;
        }

        return trim(
            'SELECT ' . ($distinct === true ? 'DISTINCT ' : '') . implode(', ', $fields)
            . ' FROM ' . implode(', ', $this->from)
            . ($this->join === [] ? '' : ' ' . implode(' ', $this->join))
            . ($this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', $this->conditions))
            . ($this->groupBy === [] ? '' : ' GROUP BY ' . implode(', ', $this->groupBy))
            . ($this->having === [] ? '' : ' HAVING ' . implode(' AND ', $this->having))
            . ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order))
            . ($this->limit === null ? '' : ' LIMIT ' . $this->limit)
            . ($this->offset === null ? '' : ' OFFSET ' . $this->offset)
        );
    }

    public function cloneAndResetParts(...$parts): self
    {
        $clone = clone $this;

        if (in_array('fields', $parts, true)) {
            $clone->fields = [];
        }
        if (in_array('conditions', $parts, true)) {
            $clone->conditions = [];
        }
        if (in_array('order', $parts, true)) {
            $clone->order = [];
        }
        if (in_array('from', $parts, true)) {
            $clone->from = [];
        }
        if (in_array('groupBy', $parts, true)) {
            $clone->groupBy = [];
        }
        if (in_array('having', $parts, true)) {
            $clone->having = [];
        }
        if (in_array('limit', $parts, true) || in_array('offset', $parts, true)) {
            $clone->limit = null;
            $clone->offset = null;
        }

        if (in_array('distinct', $parts, true)) {
            $clone->distinct = false;
        }

        if (in_array('join', $parts, true)) {
            $clone->join = [];
        }

        return $clone;
    }

    public function count(): Count
    {
        return Count::createFromSelect($this);
    }


    /**
     * Add conditions for the WHERE clause
     * @param string ...$where List of conditions
     * @return $this
     */
    public function where(string ...$where): self
    {
        foreach ($where as $arg) {
            $this->conditions[] = $arg;
        }
        return $this;
    }

    /**
     * Set the table for the FROM clause
     * @param string $table Table name
     * @param string|null $alias Optional table alias
     * @return $this
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->from[] = $alias === null ? $table : "{$table} AS {$alias}";
        return $this;
    }

    /**
     * Set the limit for the query
     * @param int|null $limit Limit value
     * @return $this
     */
    public function limit(?int $limit, ?int $offset = null): self
    {
        if ($limit !== null && $limit <= 0) {
            throw new LogicException('Limit must be greater than 0');
        }

        if ($offset !== null && $offset < 0) {
            throw new LogicException('Offset must be greater than or equal to 0');
        }

        if ($offset !== null && $limit === null) {
            $limit = PHP_INT_MAX;
        }

        $this->limit = $limit;
        $this->offset = ($limit === null) ? null : $offset; // ← reset offset

        return $this;
    }

    /**
     * Add order fields for the ORDER BY clause
     * @param string $sort Field to sort by
     * @param string $order Sorting order (ASC or DESC)
     * @return $this
     */
    public function orderBy(string $sort, string $order = 'ASC'): self
    {
        $this->order[] = "$sort $order";
        return $this;
    }

    /**
     * Add INNER JOIN clause
     * @param string ...$join List of tables to join
     * @return $this
     */
    public function innerJoin(string ...$join): self
    {
        foreach ($join as $arg) {
            $this->join[] = "INNER JOIN $arg";
        }
        return $this;
    }

    /**
     * Add LEFT JOIN clause
     * @param string ...$join List of tables to join
     * @return $this
     */
    public function leftJoin(string ...$join): self
    {
        foreach ($join as $arg) {
            $this->join[] = "LEFT JOIN $arg";
        }
        return $this;
    }

    /**
     * Perform a right join with the given tables.
     *
     * @param string ...$join The tables to perform right join with
     * @return self
     */
    public function rightJoin(string ...$join): self
    {
        foreach ($join as $arg) {
            $this->join[] = "RIGHT JOIN $arg";
        }
        return $this;
    }

    /**
     * Set the query to return distinct results.
     *
     * @return self
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Group the query results by the given columns.
     *
     * @param string ...$groupBy The columns to group by
     * @return self
     */
    public function groupBy(string ...$groupBy): self
    {
        foreach ($groupBy as $arg) {
            $this->groupBy[] = $arg;
        }
        return $this;
    }

    /**
     * Set the HAVING clause for the query.
     *
     * @param string ...$having The conditions for the HAVING clause
     * @return self
     */
    public function having(string ...$having): self
    {
        foreach ($having as $arg) {
            $this->having[] = $arg;
        }
        return $this;
    }
}
