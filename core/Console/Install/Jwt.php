<?php

namespace MkyCore\Console\Install;

use MkyCore\Console\Create\Create;

class Jwt extends Create
{
    public function process(): bool|string
    {
        $configAR = false;
        $migrationAR = false;
        $configModel = file_get_contents(dirname(__DIR__) . '/models/install/jwt/config.model');
        $migrationModel = file_get_contents(dirname(__DIR__) . '/models/install/jwt/migration.model');
        $configPath = $this->app->get('path:config');
        if (!file_exists($configPath . DIRECTORY_SEPARATOR . 'jwt.php')) {
            file_put_contents($configPath . DIRECTORY_SEPARATOR . 'jwt.php', $configModel);
        } else {
            $configAR = true;
        }
        $databasePath = $this->app->get('path:base') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        if(!is_dir($databasePath)){
            mkdir($databasePath, '0777', true);
        }
        if (!glob($databasePath . DIRECTORY_SEPARATOR . '*create_json_web_tokens_table.php')) {
            $migrationFile = $databasePath . DIRECTORY_SEPARATOR . time() .'_create_json_web_tokens_table.php';
            file_put_contents($migrationFile, $migrationModel);
        } else {
            $migrationAR = true;
        }
        if ($migrationAR && $configAR) {
            echo $this->getColoredString('jwt config file and migration file already exists', 'red', 'bold');
        } elseif ($migrationAR) {
            echo $this->getColoredString('jwt config file created successfully', 'green', 'bold');
        } elseif ($configAR) {
            echo $this->getColoredString('migration file created successfully', 'green', 'bold')."\n";
            echo $this->getColoredString('run php mky migration:run to migrate the Jwt table', 'green', 'bold');
        } else {
            echo $this->getColoredString('jwt config file and migration file created successfully', 'green', 'bold')."\n";
            echo '> run ' . $this->getColoredString('php mky migration:run', 'yellow') . ' to migrate the Jwt table';
        }
        return true;
    }
}