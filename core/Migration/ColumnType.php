<?php

namespace MkyCore\Migration;

use MkyCore\Exceptions\Migration\MethodTypeException;
use MkyCore\Exceptions\Migration\MigrationException;
use MkyCore\Facades\DB;
use ReflectionClass;
use ReflectionMethod;

class ColumnType
{

    private string $query = '';

    public function __construct(private readonly string $table, string $column, string $type, ...$options)
    {
        $this->{$type}($column, ...$options);
    }

    /**
     * Get database table
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Set column as a primary key column
     *
     * @return $this
     */
    public function primaryKey(): static
    {
        $this->query .= "PRIMARY KEY";
        return $this;
    }

    /**
     * Set column as a not null column
     *
     * @return $this
     */
    public function notNull(): static
    {
        $this->query .= " NOT NULL ";
        return $this;
    }

    /**
     * Set column as an unsigned column
     *
     * @return $this
     */
    public function unsigned(): static
    {
        $this->query .= ' unsigned ';
        return $this;
    }

    /**
     * Make an integer type column
     *
     * @param string $name
     * @return $this
     */
    public function integer(string $name): static
    {
        $this->query = "`$name` INT";
        return $this;
    }

    /**
     * Make a big integer type column
     *
     * @param string $name
     * @return $this
     */
    public function bigInt(string $name): static
    {
        $this->query = "`$name` BIGINT";
        return $this;
    }

    /**
     * Make a small integer type column
     *
     * @param string $name
     * @return $this
     */
    public function smallInt(string $name): static
    {
        $this->query = "`$name` SMALLINT";
        return $this;
    }

    /**
     * Make a tiny integer type column
     *
     * @param string $name
     * @return $this
     */
    public function tinyInt(string $name): static
    {
        $this->query = "`$name` TINYINT";
        return $this;
    }

    /**
     * Make a varchar type column
     *
     * @param string $name
     * @param int $limit
     * @return $this
     */
    public function string(string $name, int $limit = 255): static
    {
        $this->query = "`$name` varchar(" . $limit . ")";
        return $this;
    }

    /**
     * Make a datetime type column
     *
     * @param string $name
     * @return $this
     */
    public function datetime(string $name): static
    {
        $this->query = "`$name` datetime";
        return $this;
    }

    /**
     * Set default value
     *
     * @param mixed|null $value
     * @return $this
     */
    public function default(mixed $value = null): static
    {
        $this->query .= " DEFAULT " . (is_bool($value) ? (int) $value : $value);
        return $this;
    }

    /**
     * Make a timestamp type column
     *
     * @param string $name
     * @return $this
     */
    public function timestamp(string $name): static
    {
        $this->query = "`$name` timestamp";
        return $this;
    }

    /**
     * Set column as unique
     *
     * @return $this
     */
    public function unique(): static
    {
        $this->query .= ' UNIQUE ';
        return $this;
    }

    /**
     * Set reference foreign key
     *
     * @param string $name
     * @param string $row
     * @return $this
     */
    public function references(string $name, string $row = 'id'): static
    {
        $this->query .= " REFERENCES `$name` (`$row`)";
        return $this;
    }

    /**
     * Set cascade on delete and on update
     *
     * @return $this
     */
    public function cascade(): static
    {
        $this->cascadeDelete();
        $this->cascadeUpdate();
        return $this;
    }

    /**
     * Set cascade on delete
     *
     * @return $this
     */
    public function cascadeDelete(): static
    {
        $this->query .= " ON DELETE CASCADE ";
        return $this;
    }

    /**
     * Set cascade on update
     * @return $this
     */
    public function cascadeUpdate(): static
    {
        $this->query .= " ON UPDATE CASCADE ";
        return $this;
    }

    /**
     * Set no action on delete and on update
     *
     * @return $this
     */
    public function noAction(): static
    {
        $this->noActionDelete();
        $this->noActionUpdate();
        return $this;
    }

    /**
     * Set no action on delete
     *
     * @return $this
     */
    public function noActionDelete(): static
    {
        $this->query .= " ON DELETE NO ACTION ";
        return $this;
    }

    /**
     * Set no action on update
     *
     * @return $this
     */
    public function noActionUpdate(): static
    {
        $this->query .= " ON UPDATE NO ACTION ";
        return $this;
    }

    /**
     * Make a float type column
     *
     * @param string $name
     * @param array $precision
     * @return $this
     */
    public function float(string $name, array $precision = []): static
    {
        $this->query = "`$name` FLOAT";
        if($precision && count($precision) <= 2){
            $this->query .= '('.join(',', $precision).')';
        }
        return $this;
    }

    /**
     * Make a text type column
     *
     * @param string $name
     * @return $this
     */
    public function text(string $name): static
    {
        $this->query = "`$name` TEXT";
        return $this;
    }

    /**
     * Set column as auto increment
     *
     * @return $this
     */
    public function autoIncrement(): static
    {
        $this->query .= " AUTO_INCREMENT ";
        return $this;
    }

    /**
     * Drop column and all foreign keys linked
     *
     * @param string $foreignKey
     * @return $this
     */
    public function dropColumnAndForeignKey(string $foreignKey): static
    {
        $foreignKeysDb = $this->getForeignKeysDb($foreignKey);
        $queries = [];
        for ($i = 0; $i < count($foreignKeysDb); $i++) {
            $fkDb = $foreignKeysDb[$i];
            $this->dropForeignKey($fkDb);
            $queries[] = $this->query;
        }
        $this->dropColumn($foreignKey);
        $queries[] = $this->query;
        $this->query = join(", ", $queries);
        return $this;
    }

    /**
     * Get all foreign keys linked with column name
     *
     * @param string $foreignKey
     * @return array
     */
    private function getForeignKeysDb(string $foreignKey): array
    {
        $FKs = DB::prepare("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
WHERE information_schema.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'FOREIGN KEY' 
AND information_schema.TABLE_CONSTRAINTS.TABLE_SCHEMA = :schema
AND information_schema.TABLE_CONSTRAINTS.TABLE_NAME = :table
AND CONSTRAINT_NAME LIKE :fk", ['table' => $this->table, 'schema' => DB::getDatabase(), 'fk' => "FK_{$foreignKey}%"]);

        return array_map(function (array $fk) {
            return array_shift($fk);
        }, $FKs);
    }

    /**
     * Drop foreign key
     *
     * @param string $foreignKey
     * @return $this
     */
    public function dropForeignKey(string $foreignKey): static
    {
        $this->query = "DROP FOREIGN KEY $foreignKey";
        return $this;
    }

    /**
     * Drop column
     *
     * @param string $column
     * @return $this
     */
    public function dropColumn(string $column): static
    {
        $this->query = "DROP COLUMN `$column`";
        return $this;
    }

    /**
     * Drop table
     *
     * @return $this
     */
    public function dropTable(): static
    {
        $this->query = "DROP TABLE IF EXISTS `$this->table`";
        return $this;
    }

    /**
     * Modify column type
     *
     * @throws MethodTypeException
     */
    public function modify(string $column, string $type, array $options = []): static
    {
        $this->useMethod($type, $column, $options);
        $query = 'MODIFY ';
        $this->query = $query . $this->query;
        return $this;
    }

    /**
     * Call a method dynamically
     *
     * @param string $method
     * @param string $column
     * @param array $options
     * @return mixed
     * @throws MethodTypeException
     */
    private function useMethod(string $method, string $column, array $options = []): mixed
    {
        $reflectionClass = new ReflectionClass($this);
        $methods = array_map(fn(ReflectionMethod $meth) => $meth->getName(), $reflectionClass->getMethods());
        if (!in_array($method, $methods)) {
            throw new MethodTypeException("Method $method not found or implement");
        }
        return $this->{$method}($column, ...$options);
    }

    /**
     * Rename column
     * parameter new type to change the type
     *
     * @throws MethodTypeException
     */
    public function rename(string $column, string $name, string $newType = null, array $options = []): static
    {
        if (!$newType) {
            $res = $this->getColumnType($column);
            $res = $res ?: 'varchar(255)';
            $type = " $res";
            
        } else {
            $type = $this->useMethod($newType, $column, $options);
            $type = str_replace("`$column`", '', $type->getQuery());
            $type = " ".trim($type);
        }
        $this->query = "CHANGE `$column` `$name`$type";
        return $this;
    }

    /**
     * Get column type
     *
     * @param string $column
     * @return string|bool
     */
    private function getColumnType(string $column): string|bool
    {
        $res = DB::prepare("
SELECT COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = :schema
AND TABLE_NAME = :table 
AND COLUMN_NAME = :column", ['table' => $this->table, 'column' => $column, 'schema' => DB::getDatabase()], null, true);
        return $res ? array_shift($res) : $res;
    }

    /**
     * Set a column as foreign key
     *
     * @param string $name
     * @return $this
     */
    public function foreignKey(string $name): static
    {
        $fk = "FK_{$name}_" . rand();
        $constrain = "CONSTRAINT `$fk`";
        $foreignKey = "FOREIGN KEY (`$name`)";
        $query = $this->query;
        $this->query = "$constrain $foreignKey";
        return $this;
    }

    /**
     * get Query statement
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }
}