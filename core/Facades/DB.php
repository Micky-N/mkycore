<?php

namespace MkyCore\Facades;


use MkyCore\Abstracts\Facade;
use MkyCore\Database;
use PDO;

/**
 * @method static PDO getConnection()
 * @method static mixed query($statement, $class_name = null, bool $one = false)
 * @method static mixed prepare($statement, $attribute, $class_name = null, bool $one = false)
 * @method static string getDatabase()
 * @see \MkyCore\Database
 */
class DB extends Facade
{
    protected static string $accessor = Database::class;
}
