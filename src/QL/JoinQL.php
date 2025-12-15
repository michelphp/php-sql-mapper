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
    private PDO $pdo;
    private ?Select $selectQuery = null;
    private ?string $firstTable = null;
    private ?string $firstAlias = null;
    private array $joins = [];
    private string $primaryKey;
    private ?int $aliasMaxLength;
    private ?array $lastRow = null;
    private int $countRows = 0;
    private ?int $offset = null;
    private ?int $limit = null;
    private array $params = [];
    private array $aliases = [];

    public function __construct(PDO $pdo, string $primaryKey = 'id', ?int $aliasMaxLength = null)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->primaryKey = $primaryKey;
        $this->aliasMaxLength = $aliasMaxLength;
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
        $this->selectQuery->leftJoin(sprintf('%s %s %s', $toTable, $toTableAlias, sprintf('ON %s', implode(' AND ', $joinConditions))));

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
        $this->selectQuery->innerJoin(sprintf('%s %s %s', $toTable, $toTableAlias, sprintf('ON %s', implode(' AND ', $joinConditions))));
        return $this;
    }

    public function setParam(string $name, $value): self
    {
        $this->params[$name] = $value;
        return $this;
    }


    public function getResultIterator(): iterable
    {
        $db = $this->executeQuery($this->selectQuery);
        $this->lastRow = null;
        $this->countRows = 0;
        $count = 0;
        while (($rows = $this->fetchIterator($db)) !== null) {
            if ($rows instanceof \Traversable) {
                $rows = iterator_to_array($rows);
            }

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if ($this->limit !== null && $count >= $this->limit) {
                    break 2;
                }
                yield $row;
                $count++;
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
        if ($this->limit !== null) {
            $data = [];
            foreach ($this->getResultIterator() as $row) {
                $data[] = $row;
            }
            return $data;
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
                $this->countRows++;
                if ($this->offset !== null && $this->countRows <= $this->offset) {
                    $data = [$row];
                    $previous = $row[$primaryKey];
                    continue;
                }
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

    private function executeQuery(string $query): PDOStatement
    {
        $db = $this->pdo->prepare($query);
        foreach ($this->params as $key => $value) {
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

        foreach ($data as &$row) {
            $copyRow = $row;
            foreach ($row as $key => $value) {
                $aliasTable = explode('__', $key)[0];
                if (isset($this->aliases[$aliasTable][$key])) {
                    $copyRow[$this->aliases[$aliasTable][$key]] = $value;
                }
            }

            $row = $copyRow;
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
