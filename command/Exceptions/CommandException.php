<?php

namespace MkyCommand\Exceptions;

use Exception;

class CommandException extends Exception
{
    public static function CommandNotFound(string $signature, string $command = ''): static
    {
        $command = $command ? "\nrun $command to see the list of commands" : $command;
        return new static("Command not found with signature \"$signature\"$command");
    }

    public static function ArgumentNotFound(string $name): static
    {
        return new static("Argument \"$name\" not found");
    }

    public static function OptionNotFound(string $name): static
    {
        return new static("Option \"$name\" not found");
    }
}