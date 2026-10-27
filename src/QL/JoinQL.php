<?php

namespace Michel\SqlMapper\QL;

use LogicException;
use PDO;
use PDOStatement;
use Michel\SqlMapper\Count;
use Michel\SqlMapper\Graph\Builder\GraphBuilder;
use Michel\SqlMapper\Graph\Builder\Table;
use Michel\SqlMapper\Select;

final class JoinQL
{
    /**
     * Maximum number of ids sent in one "IN (...)" clause (SQLite < 3.32 allows 999 parameters).
     */
    private const PAGE_IDS_CHUNK_SIZE = 500;

    private PDO $pdo;
    private ?Select $selectQuery = null;
    private ?string $firstTable = null;
    private ?string $firstAlias = null;
    private array $joins = [];
    private string $primaryKey;
    private ?int $aliasMaxLength;
    private ?array $lastRow = null;
    private ?int $offset = null;
    private ?int $limit = null;
    private array $params = [];
    private array $aliases = [];
    private array $conditions = [];
    private array $orderBys = [];

    /**
     * @var callable|null fn(string $sql, ?int $limit, ?int $offset): string
     */
    private $limitWriter;

    /**
     * @param callable|null $limitWriter Writes the database-specific LIMIT/OFFSET clause.
     *                                   Without it, the offset is always skipped in PHP (works on any database).
     */
    public function __construct(PDO $pdo, string $primaryKey = 'id', ?int $aliasMaxLength = null, ?callable $limitWriter = null)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->primaryKey = $primaryKey;
        $this->aliasMaxLength = $aliasMaxLength;
        $this->limitWriter = $limitWriter;
    }

    public function select(string $table, string $aliasTable, array $columns): self
    {
        $this->resolveColumns($aliasTable, $columns);

        $this->selectQuery = (new Select($columns))->from($table, $aliasTable);
        $this->firstTable = $table;
        $this->firstAlias = $aliasTable;
        return $this;
    }

    public function addSelect(string $aliasTable, array $columns): self
    {
        $this->resolveColumns($aliasTable, $columns);

        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding a SELECT clause.'
            );
        }
        $this->selectQuery->select(...$columns);
        return $this;

    }

    public function where(string ...$where): self
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding a WHERE clause.'
            );
        }
        $this->selectQuery->where(...$where);
        foreach ($where as $condition) {
            $this->conditions[] = $condition;
        }
        return $this;
    }

    public function orderBy($sort, $order = 'ASC'): self
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding an ORDER BY clause.'
            );
        }
        $this->selectQuery->orderBy($sort, $order);
        $this->orderBys[] = (string)$sort;
        return $this;
    }

    public function setFirstResult(?int $firstResult): self
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding an FIRST RESULT clause.'
            );
        }
        $this->offset = $firstResult;
        return $this;
    }

    public function setMaxResults(?int $maxResults): self
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding a MAX RESULTS clause.'
            );
        }
        $this->limit = $maxResults;
        return $this;
    }

    public function leftJoin(
        string $fromTable,            // The source table (the table to join from or alias)
        string $toTable,              // The target table (the table to join to)
        string $toTableAlias,         // The alias for the joined table (used in the query)
        array  $joinConditions,       // The conditions for the JOIN clause (e.g., ['posts.user_id', '=', 'users.id'])
        bool   $isOneToMany = false,  // Whether the relationship is OneToMany (default is false for OneToOne)
        string $relationKey = null,   // The key to use for the relationship in the result set (e.g., 'user' to replace 'user_id')
        string $sourceForeignKey = null // The foreign key in the source table (e.g., 'user_id' in 'posts')
    ): self
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding a LEFT JOIN clause.'
            );
        }
        $this->joins[$toTableAlias] = [
            'type' => 'left',
            'from' => $fromTable,
            'to' => $toTable,
            'alias' => $toTableAlias,
            'key_relation' => $relationKey ?? $toTableAlias,
            'condition' => $joinConditions,
            'one_to_many' => $isOneToMany,
            'foreign_key' => $sourceForeignKey
        ];
        $this->selectQuery->leftJoin($this->joinClause($this->joins[$toTableAlias]));

        return $this;
    }

    public function innerJoin(
        string $fromTable,            // The source table (the table to join from or alias)
        string $toTable,              // The target table (the table to join to)
        string $toTableAlias,         // The alias for the joined table (used in the query)
        array  $joinConditions,       // The conditions for the JOIN clause (e.g., ['posts.user_id', '=', 'users.id'])
        bool   $isOneToMany = false,  // Whether the relationship is OneToMany (default is false for OneToOne)
        string $relationKey = null,   // The key to use for the relationship in the result set (e.g., 'user' to replace 'user_id')
        string $sourceForeignKey = null // The foreign key in the source table (e.g., 'user_id' in 'posts')
    ): self
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding a INNER JOIN clause.'
            );
        }
        $this->joins[$toTableAlias] = [
            'type' => 'inner',
            'from' => $fromTable,
            'to' => $toTable,
            'alias' => $toTableAlias,
            'key_relation' => $relationKey ?? $toTableAlias,
            'condition' => $joinConditions,
            'one_to_many' => $isOneToMany,
            'foreign_key' => $sourceForeignKey
        ];
        $this->selectQuery->innerJoin($this->joinClause($this->joins[$toTableAlias]));
        return $this;
    }

    public function setParam(string $name, $value): self
    {
        $this->params[$name] = $value;
        return $this;
    }


    public function getResultIterator(): iterable
    {
        if ($this->isPaginated()) {
            foreach ($this->getPage() as $row) {
                yield $row;
            }
            return;
        }

        $db = $this->executeQuery($this->selectQuery);
        $this->lastRow = null;
        while (($rows = $this->fetchIterator($db)) !== null) {
            if ($rows instanceof \Traversable) {
                $rows = iterator_to_array($rows);
            }

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                yield $row;
            }
        }

        $db->closeCursor();
    }

    public function getOneOrNullResult(): ?array
    {
        foreach ($this->getResultIterator() as $row) {
            if ($row instanceof \Traversable) {
                $row = iterator_to_array($row)[0] ?? null;
            }
            return $row;
        }

        return null;
    }

    public function getResult(): array
    {
        if ($this->isPaginated()) {
            return $this->getPage();
        }
        $db = $this->executeQuery($this->selectQuery);
        return $this->buildGraph($db->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(): int
    {
        $field = sprintf('%s.%s', $this->firstAlias, $this->primaryKey);
        $count = $this->toCountBuilder()->on($field, null, true);
        $stmt = $this->executeQuery($count);
        return $stmt->fetchColumn();
    }

    public function getQuery(): string
    {
        return $this->selectQuery->__toString();
    }

    public function toCountBuilder(): Count
    {
        if ($this->selectQuery === null) {
            throw new LogicException(
                'You must call the select() method first to define the main table for the query ' .
                'before adding a SELECT clause.'
            );
        }
        return Count::createFromSelect($this->selectQuery);
    }

    private function fetchIterator(PDOStatement $db): iterable
    {
        $data = null;
        if ($this->lastRow !== null) {
            $data[] = $this->lastRow;
            $this->lastRow = null;
        }

        $previous = null;
        $primaryKey = sprintf('%s__%s', $this->firstAlias, $this->primaryKey);
        while ($row = $db->fetch(PDO::FETCH_ASSOC)) {
            if ($previous === null) {
                $previous = $row[$primaryKey];
            }

            if ($previous !== $row[$primaryKey]) {
                $this->lastRow = $row;
                break;
            }
            $data[] = $row;
        }

        if ($data === null) {
            return null;
        }

        $items = $this->buildGraph($data) ?? [];
        foreach ($items as $item) {
            yield $item;
        }
        return null;
    }

    private function isPaginated(): bool
    {
        return $this->limit !== null || $this->offset !== null;
    }

    /**
     * Limit and offset count root entities, not SQL rows: a one-to-many join repeats the root row.
     * 1. Fetch only the root ids, with the joins used by conditions and order. The first occurrence of an id
     *    gives its position, so the offset is skipped and the limit taken on distinct ids.
     *    When no one-to-many join is left, there is one row per id and the database applies LIMIT/OFFSET itself.
     * 2. Load the complete rows of those ids only, with every join, then restore the order of step 1.
     * Plain SELECT / JOIN / WHERE / ORDER BY / IN: no DISTINCT, GROUP BY or sub-query needed.
     */
    private function getPage(): array
    {
        $ids = $this->fetchPageIds();
        if ($ids === []) {
            return [];
        }

        $rowsById = [];
        foreach (array_chunk($ids, self::PAGE_IDS_CHUNK_SIZE) as $chunk) {
            foreach ($this->fetchRowsByIds($chunk) as $row) {
                $rowsById[(string)$row[$this->primaryKey]] = $row;
            }
        }

        $page = [];
        foreach ($ids as $id) {
            if (isset($rowsById[(string)$id])) {
                $page[] = $rowsById[(string)$id];
            }
        }
        return $page;
    }

    private function fetchPageIds(): array
    {
        [$query, $uniqueRows] = $this->buildPageIdsQuery();
        $offset = $this->offset ?? 0;
        if ($uniqueRows && $this->limitWriter !== null) {
            $query = ($this->limitWriter)($query, $this->limit, $this->offset);
            $offset = 0;
        }

        $db = $this->executeQuery($query);
        $skipped = 0;
        $seen = [];
        $ids = [];
        while (($id = $db->fetchColumn()) !== false) {
            $key = (string)$id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if ($skipped < $offset) {
                $skipped++;
                continue;
            }

            $ids[] = $id;
            if ($this->limit !== null && count($ids) >= $this->limit) {
                break;
            }
        }
        $db->closeCursor();

        return $ids;
    }

    /**
     * @return array{0: string, 1: bool} The ids query, and whether it returns one row per id.
     */
    private function buildPageIdsQuery(): array
    {
        // INNER joins filter roots and referenced joins are used by conditions or order: both are kept,
        // with the joins they depend on. A LEFT join that only loads data has no effect on the ids.
        $kept = [];
        foreach ($this->joins as $alias => $join) {
            if ($join['type'] === 'inner' || $this->isAliasReferenced($alias)) {
                $this->keepJoin($alias, $kept);
            }
        }

        $primaryKey = sprintf('%s.%s', $this->firstAlias, $this->primaryKey);
        // The primary key as last sort criterion keeps the order stable when the other criteria have ties.
        $query = $this->selectQuery
            ->cloneAndResetParts('fields', 'join')
            ->select($primaryKey)
            ->orderBy($primaryKey);

        $uniqueRows = true;
        foreach ($this->joins as $alias => $join) {
            if (!isset($kept[$alias])) {
                continue;
            }
            if ($join['one_to_many']) {
                $uniqueRows = false;
            }
            if ($join['type'] === 'inner') {
                $query->innerJoin($this->joinClause($join));
            } else {
                $query->leftJoin($this->joinClause($join));
            }
        }

        return [(string)$query, $uniqueRows];
    }

    private function keepJoin(string $alias, array &$kept): void
    {
        $current = $alias;
        while ($current !== null && !isset($kept[$current])) {
            $kept[$current] = true;
            $current = $this->findJoinAlias($this->joins[$current]['from']);
        }
    }

    /**
     * The source of a join is either an alias or a table name, null when it is the root.
     */
    private function findJoinAlias(string $from): ?string
    {
        if (isset($this->joins[$from])) {
            return $from;
        }
        foreach ($this->joins as $alias => $join) {
            if ($join['to'] === $from) {
                return $alias;
            }
        }
        return null;
    }

    private function isAliasReferenced(string $alias): bool
    {
        // Matches alias.column, `alias`.column, "alias".column and [alias].column. A false positive only keeps a join.
        $pattern = sprintf('/(?<![\w`"\[])[`"\[]?%s[`"\]]?\./', preg_quote($alias, '/'));
        foreach (array_merge($this->conditions, $this->orderBys) as $expression) {
            if (preg_match($pattern, $expression) === 1) {
                return true;
            }
        }
        return false;
    }

    private function joinClause(array $join): string
    {
        return sprintf('%s %s ON %s', $join['to'], $join['alias'], implode(' AND ', $join['condition']));
    }

    private function fetchRowsByIds(array $ids): array
    {
        // PDO cannot mix named and positional placeholders, follow the style of the user parameters.
        $positional = $this->params !== [] && is_int(array_key_first($this->params));
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            if ($positional) {
                $placeholders[] = '?';
                $params[] = $id;
                continue;
            }
            $name = '__jql_page_id_' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $id;
        }

        $query = (clone $this->selectQuery)->where(sprintf(
            '%s.%s IN (%s)',
            $this->firstAlias,
            $this->primaryKey,
            implode(', ', $placeholders)
        ));

        $db = $this->executeQuery($query, $params);
        return $this->buildGraph($db->fetchAll(PDO::FETCH_ASSOC));
    }

    private function executeQuery(string $query, array $extraParams = []): PDOStatement
    {
        $db = $this->pdo->prepare($query);
        // array_merge() renumbers positional keys, so extra positional values follow the user ones.
        foreach (array_merge($this->params, $extraParams) as $key => $value) {
            $type = \PDO::PARAM_STR;
            if (is_bool($value)) {
                $type = \PDO::PARAM_BOOL;
            }
            if (is_string($key)) {
                $db->bindValue(':' . $key, $value, $type);
            } else {
                $db->bindValue($key + 1, $value, $type);
            }
        }
        $db->execute();
        return $db;
    }

    private function buildGraph(array $data): array
    {
        $tables[$this->firstAlias] = new Table($this->firstTable, $this->firstAlias);
        foreach ($this->joins as $alias => $join) {
            $tables[$alias] = new Table($join['to'], $alias, $join['one_to_many'], $join['key_relation'], $join['foreign_key']);
        }
        foreach ($tables as $alias => $table) {
            foreach ($this->joins as $join) {
                if ($join['alias'] !== $alias) {
                    continue;
                }
                /** @var array<Table> $parents */
                $parents = array_values(array_filter($tables, function ($table) use ($join) {
                    return $table->getTable() === $join['from'] || $join['from'] === $table->getAlias();
                }));

                foreach (array_unique($parents) as $parent) {
                    $parent->addChild($table);
                }
            }
        }

        if ($data === []) {
            return [];
        }

        // Every row has the same columns: restore the aliases shortened by $aliasMaxLength once, from the first row.
        $keys = [];
        $renamed = false;
        foreach (array_keys(reset($data)) as $key) {
            $aliasTable = explode('__', $key)[0];
            $originalKey = $this->aliases[$aliasTable][$key] ?? $key;
            $renamed = $renamed || $originalKey !== $key;
            $keys[] = $originalKey;
        }
        if ($renamed) {
            foreach ($data as &$row) {
                $row = array_combine($keys, $row);
            }
            unset($row);
        }

        return (new GraphBuilder($data, $tables, $this->primaryKey))->buildGraph();
    }

    private function resolveColumns(string $aliasTable, array &$columns): void
    {
        $this->aliases[$aliasTable] = [];
        $processedColumns = [];
        foreach ($columns as $key => $columnName) {
            $columnToSelect = $aliasTable . '.' . $columnName;
            $alias = $columnToSelect;
            $initialAlias = str_replace('.', '__', str_replace('`', '', $alias));
            $finalAlias = $initialAlias;
            if ($this->aliasMaxLength) {
                $count = 0;
                while (strlen($finalAlias) > $this->aliasMaxLength || array_key_exists($finalAlias, $this->aliases[$aliasTable])) {
                    $count++;
                    $maxLengthWithoutSuffix = $this->aliasMaxLength - $count;
                    $truncatedBase = substr($initialAlias, 0, $maxLengthWithoutSuffix);
                    $finalAlias = $truncatedBase . $count;
                }
            }
            $this->aliases[$aliasTable][$finalAlias] = $initialAlias;
            if (is_string($key)) {
                $columnToSelect = $aliasTable . '.' . $key;
            }
            $processedColumns[] = sprintf('%s AS %s', $columnToSelect, $finalAlias);
        }
        $columns = $processedColumns;
    }
}
