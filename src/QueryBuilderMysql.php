<?php

namespace MkyCore;

use Exception;
use MkyCore\Abstracts\Entity;
use MkyCore\Abstracts\Manager;

class QueryBuilderMysql
{

    /**
     * SELECT
     * @var array
     */
    private array $fields = [];

    /**
     * FROM
     * @var array
     */
    private array $from = [];

    /**
     * WHERE
     * @var array
     */
    private array $conditions = [];

    /**
     * ORDER BY
     * @var array
     */
    private array $order = [];

    /**
     * LIMIT
     * @var array
     */
    private array $limit = [];

    /**
     * JOIN ON
     * @var array
     */
    private array $joins = [];

    /**
     * GROUP BY
     * @var array
     */
    private array $group = [];

    private Manager $instance;
    private \PDO $pdoConnection;

    /**
     * @throws Exception
     */
    public function __construct(Manager $instance)
    {
        $this->instance = $instance;
        $this->pdoConnection = Database::getConnection();
    }

    /**
     * @param string $statement
     * @param array $attribute
     * @return array
     * @throws Exception
     * @see Database::prepare()
     */
    public function prepare(string $statement, array $attribute): array
    {
        return Database::prepare($statement, $attribute);
    }

    /**
     * @return $this
     */
    public function select(...$args): static
    {
        $this->fields = $args;
        return $this;
    }

    /**
     * @param string $table
     * @param null $alias
     * @return $this
     */
    public function from(string $table, $alias = null): static
    {
        if (is_null($alias)) {
            $this->from[] = "$table";
        } else {
            $this->from[] = "$table AS $alias";
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function where(string $column, ...$condition): static
    {
        if (count($condition) === 1)
            $this->conditions[] = sprintf('%s = "%s"', $column, ...$condition);
        else if (count($condition) === 2)
            $this->conditions[] = sprintf('%s %s "%s"', $column, ...$condition);
        return $this;
    }

    /**
     * @return $this
     */
    public function groupBy(...$groupBy): static
    {
        $this->group[] = join(', ', $groupBy);
        return $this;
    }

    /**
     * @param string $join_table
     * @param string $on
     * @param string $operation
     * @param string $to
     * @param string $aliasFirstTable
     * @return $this
     * @throws Exception
     */
    public function join(string $join_table, string $on, string $operation, string $to, string $aliasFirstTable = ''): static
    {
        if (!strpos($on, '.')) {
            $on = empty($alias) ? $this->instance->getTable() . ".$on" : "$aliasFirstTable.$on";
        }
        if (!strpos($to, '.')) {
            $to = "$join_table.$to";
        }
        $this->joins[] = [$join_table, $on, $operation, $to];

        return $this;
    }

    /**
     * Make array with selected field
     *
     * @param string $key
     * @param mixed|null $value
     * @return array
     * @throws Exception
     */
    public function map(string $key = '', mixed $value = null): array
    {
        $query = Database::query($this->stringify());
        $value = str_replace(' ', '', $value);
        $valuemap = !empty($value) ? $this->mapping($value, $query) : $query;
        if ($key) {
            $keymap = $this->mapping($key, $query);
            $valuemap = array_combine($keymap, $valuemap);
        }
        return $valuemap;
    }

    /**
     * @param string $statement
     * @return array
     * @throws Exception
     * @see \PDO::query()
     */
    public function query(string $statement): array
    {
        return Database::query($statement);
    }

    /**
     * Get request as string
     *
     * @return string
     * @throws Exception
     */
    public function stringify(): string
    {
        return $this->hasFields()
            . $this->hasFrom()
            . $this->hasJoin()
            . $this->hasConditions()
            . $this->hasGroup()
            . $this->hasOrder()
            . $this->hasLimit();
    }

    /**
     * @return string
     */
    private function hasFields(): string
    {
        if (!empty($this->fields)) {
            return 'SELECT ' . implode(', ', $this->fields);
        } else {
            return 'SELECT *';
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    private function hasFrom(): string
    {
        if (!empty($this->from)) {
            return ' FROM ' . implode(', ', $this->from);
        } else {
            return ' FROM ' . $this->instance->getTable();
        }
    }

    /**
     * @return string
     */
    private function hasJoin(): string
    {
        $syntax = '';
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $syntax .= sprintf(" LEFT JOIN %s ON %s %s %s", ...$join);
            }
        }
        return $syntax;
    }

    /**
     * @return string
     */
    private function hasConditions(): string
    {
        if (!empty($this->conditions))
            return ' WHERE ' . implode(' AND ', $this->conditions);
        else
            return '';
    }

    /**
     * @return string
     */
    private function hasGroup(): string
    {
        if (!empty($this->group))
            return ' GROUP BY ' . implode(', ', $this->group);
        else
            return '';
    }

    /**
     * @return string
     */
    private function hasOrder(): string
    {
        if (!empty($this->order))
            return ' ORDER BY ' . implode(', ', $this->order);
        else
            return '';
    }

    /**
     * @return string
     */
    private function hasLimit(): string
    {
        if (!empty($this->limit))
            return ' LIMIT ' . implode(' ', $this->limit);
        else
            return '';
    }

    /**
     * Get all key column data
     *
     * @param mixed $key
     * @param array $query
     * @return array
     */
    private function mapping(mixed $key, array $query = []): array
    {
        return array_map(function ($km) use ($key) {
            if (is_string($key)) {
                return $km[$key];
            }
            $map = [];
            foreach ($key as $k => $v) {
                $map[$v] = $km[$v];
            }
            return $map;
        }, $query);
    }

    /**
     * Get all records
     *
     * @return array|bool
     * @throws Exception
     */
    public function get(): bool|array
    {
        return Database::query($this->stringify(), $this->instance->getEntity());
    }

    /**
     * Get all records as array
     *
     * @param bool $one
     * @return array|bool
     * @throws Exception
     */
    public function toArray(bool $one = false): bool|array
    {
        return Database::query($this->stringify(), null, $one);
    }

    /**
     * Get the first record
     *
     * @return Entity|bool
     * @throws Exception
     */
    public function first(): bool|Entity
    {
        $this->limit(1);
        return Database::query($this->stringify(), $this->instance->getEntity(), true);
    }

    /**
     * @return $this
     */
    public function limit(int $limit, int $offset = null): static
    {
        if (!is_null($offset)) {
            $limit .= " OFFSET $offset";
        }
        $this->limit[] = $limit;
        return $this;
    }

    /**
     * Get the last record
     *
     * @return Entity|bool
     * @throws Exception
     */
    public function last(): bool|Entity
    {
        $this->limit(1);
        $this->orderBy($this->instance->getPrimaryKey(), 'DESC');
        return Database::query($this->stringify(), $this->instance->getEntity(), true);
    }

    /**
     * @return $this
     */
    public function orderBy(string $column, string $order = 'ASC'): static
    {
        $this->order[] = "$column $order";
        return $this;
    }
}
