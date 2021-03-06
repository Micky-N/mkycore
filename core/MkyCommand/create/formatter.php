<?php

require_once 'vendor/autoload.php';

use MkyCore\MKYCommand\MickyCLI;
use MkyCore\MkyCommand\MkyCommandException;

if(php_sapi_name() === "cli"){
    $cli = getopt('', MickyCLI::cliLongOptions());
    $option = $cli['create'];
    $name = ucfirst($cli['name']);
    $format = $cli['format'];
    $path = isset($cli['path']) ? ucfirst($cli['path']) : null;
    $namespace = sprintf("App\\MkyFormatters%s", $path ? "\\" . $path : '');
    if(!strpos($name, 'Formatter')){
        throw new MkyCommandException("$name must be suffixed by Formatter");
    }
    $template = file_get_contents(__DIR__ . "/templates/$option." . MickyCLI::EXTENSION);
    $template = str_replace('!name', $name, $template);
    $template = str_replace('!format', $format, $template);
    $template = str_replace('!path', $namespace, $template);

    $dir = sprintf("app/MkyFormatters%s", ($path ? "/" . $path : ''));
    if(file_exists("$dir/$name.php")){
        throw new MkyCommandException("$name formatter already exist");
    }
    if(!is_dir($dir)){
        mkdir($dir, 0777, true);
    }
    $formatter = fopen("$dir/$name.php", "w") or die("Unable to open file $name");
    $start = "<" . "?" . "php\n\n";
    fwrite($formatter, $start . $template);
    $mkyServiceProviderFile = getcwd() . "/app/Providers/MkyServiceProvider.php";
    $arr = explode("\n", file_get_contents($mkyServiceProviderFile));
    $formatterLine = array_keys(preg_grep("/'formatters' => \[/i", $arr))[0];
    array_splice($arr, $formatterLine + 1, 0, "\t    new \\$namespace\\$name(),");
    $arr = array_values($arr);
    $arr = implode("\n", $arr);
    $ptr = fopen($mkyServiceProviderFile, "w");
    fwrite($ptr, $arr);
    print("$name formatter created");
}
