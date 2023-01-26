<?php

namespace MkyCore\Tests;

use Exception;
use MkyCommand\Console;
use MkyCommand\Exceptions\CommandException;
use MkyCommand\HelpCommand;
use MkyCore\Tests\Commands\Arguments\GreetingCommand;
use MkyCore\Tests\Commands\Arguments\NoSettingsCommand;
use MkyCore\Tests\Commands\Optional\TestOptionalCommand;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    public function testAddCommand()
    {
        $console = new Console();
        $console->addCommand('test', new NoSettingsCommand())
            ->addCommand('greeting', new GreetingCommand());
        $this->assertCount(2, $console->getCommands());
    }

    public function testFindCommandBySignature()
    {
        $console = new Console();
        $console->addCommand('test', NoSettingsCommand::class)
            ->addCommand('greeting', GreetingCommand::class);
        $console->execute(['mky', 'greeting', 'Micky']);
        $this->assertInstanceOf(GreetingCommand::class, $console->getCurrentCommand());
    }

    public function testNotFindCommandBySignature()
    {
        $console = new Console();
        $console->addCommand('test', NoSettingsCommand::class)
            ->addCommand('greeting', GreetingCommand::class);
        try {
            $console->execute(['mky', 'wrong']);
        } catch (Exception $exception) {
            $this->assertInstanceOf(CommandException::class, $exception);
        }
    }

    public function testExecuteCommand()
    {
        $console = new Console();
        $console->addCommand('test', NoSettingsCommand::class)
            ->addCommand('greeting', GreetingCommand::class);
        $this->assertEquals('Micky', $console->execute(['mky', 'greeting', 'Micky']));
    }

    public function testHelpAllCommands()
    {
        $console = new Console();
        $console->addCommand('help', new HelpCommand($console))
            ->addCommand('test:command', NoSettingsCommand::class)
            ->addCommand('greet:someone', GreetingCommand::class)
            ->addCommand('greet:optional', TestOptionalCommand::class);
        echo $console->execute(['mky', 'help']);
        $this->assertIsString($console->execute(['mky', 'help']));
    }

    public function testHelpCommand()
    {
        $console = new Console();
        $console->addCommand('test', NoSettingsCommand::class)
            ->addCommand('greeting', GreetingCommand::class);
        echo $console->execute(['mky', 'greeting', '-h']);
        $this->assertIsString($console->execute(['mky', 'greeting', '-h']));
    }
}