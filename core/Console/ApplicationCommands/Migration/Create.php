<?php

namespace MkyCore\Console\ApplicationCommands\Migration;

use MkyCommand\Exceptions\CommandException;
use MkyCommand\Exceptions\InputArgumentException;
use MkyCommand\Input;
use MkyCommand\Output;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use MkyCore\File;
use MkyCore\Str;
use ReflectionException;

class Create extends Migration
{

    protected string $description = 'Create a new migration file';

    public function settings(): void
    {
        $this->addArgument('name', Input\InputArgument::REQUIRED, 'Name of the migration file');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws FailedToResolveContainerException
     * @throws InputArgumentException
     * @throws NotInstantiableContainerException
     * @throws ReflectionException
     */
    public function execute(Input $input, Output $output): void
    {
        $outputDir = File::makePath([$this->application->get('path:database'), 'migrations']);
        $name = $input->argument('name');
        $nameSnaked = Str::toSnake($name);
        $nameFile = time() . "_$nameSnaked";
        $final = $outputDir . DIRECTORY_SEPARATOR . $nameFile . '.php';
        if (file_exists($final)) {
            $output->error('File already exists', 'migrations' . DIRECTORY_SEPARATOR . "$nameFile.php");
            exit();
        }
        $parsedModel = file_get_contents(dirname(__DIR__) . 'models/migration.model');
        $parsedModel = str_replace('!name', Str::classify($name), $parsedModel);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, '0777', true);
        }
        file_put_contents($final, $parsedModel);
        $output->success("Migration file created", $final);
    }
}