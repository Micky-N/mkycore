<?php

namespace MkyCore\Console\ApplicationCommands\Show;

use MkyCommand\AbstractCommand;
use MkyCommand\Exceptions\CommandException;
use MkyCommand\Input;
use MkyCommand\Output;
use MkyCore\Application;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use ReflectionException;

class Route extends AbstractCommand
{

    const HEADERS = [
        'getMethods' => 'Request Method',
        'getUrl' => 'Url',
        'getAction' => 'Controller',
        'getName' => 'Name',
        'getModule' => 'Module'
    ];

    const FILTERS = ['controller', 'module', 'name', 'url', 'methods'];

    protected string $description = 'Show all routes';

    public function __construct(private readonly Application $application)
    {
    }

    public function settings(): void
    {
        $this->addOption('print', 'p', Input\InputOption::NONE, 'Display routes in print mode');
        for($i = 0; $i < count(self::FILTERS); $i++){
            $filter = self::FILTERS[$i];
            $this->addOption($filter, null, Input\InputOption::OPTIONAL, "Filter routes by $filter");
        }
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     * @throws CommandException
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     * @throws ReflectionException
     */
    public function execute(Input $input, Output $output): int
    {
        $print = $input->option('print');
        $table = $output->table();
        $table->setHeaders(array_map(fn($header) => substr($header, 3), array_keys(self::HEADERS)));
        $filters = $this->getFilters($input);
        $routes = $this->application->get(\MkyCore\Router\Router::class)->getRoutes($filters);
        if(!$routes){
            $output->error('No route found with these filter criteria:');
            foreach ($filters as $cr => $value){
                echo "  - $cr: ".join('|', (array) $value)."\n";
            }
            return self::ERROR;
        }
        $methods = array_keys(self::HEADERS);
        for ($i = 0; $i < count($routes); $i++) {
            $route = $routes[$i];
            $array = [];
            foreach ($methods as $method) {
                if ($method == 'getAction') {
                    $array[] = join('::', $route->{$method}());
                } elseif ($method == 'getMethods') {
                    $array[] = $this->parseMethods($output, join('|', $route->{$method}()), $print);
                } elseif ($method == 'getUrl') {
                    $array[] = '/' . trim($this->parseUrl($output, $route->{$method}(), $print), '/');
                } else {
                    $array[] = $route->{$method}();
                }
            }
            $table->addRow($array);
        }
        if ($print) {
            echo "List of routes:\n";
        }

        $table->showAllBorders()
            ->display();
        return self::SUCCESS;
    }

    /**
     * @param Input $input
     * @return array
     * @throws CommandException
     */
    private function getFilters(Input $input): array
    {
        $filters = [];
        $filtersBase = self::FILTERS;
        for($i = 0; $i < count($filtersBase); $i ++){
            $filterBase = $filtersBase[$i];
            if($input->option($filterBase)){
                $value = $input->option($filterBase);
                if($filterBase == 'methods'){
                    $value = explode('!', $value);
                }
                $filters[$filterBase] = $value;
            }
        }
        return $filters;
    }

    private function parseMethods(Output $output, string $methods, bool $print = false): string
    {
        foreach (['POST' => 'green', 'GET' => 'blue', 'PUT' => 'light_purple', 'DELETE' => 'red'] as $method => $color) {
            $apply = $print ? $method : $output->coloredMessage($method, $color);
            $methods = str_replace($method, $apply, $methods);
        }
        return $methods;
    }

    private function parseUrl(Output $output, string $url, bool $print = false): string
    {
        $apply = $print ? '$1' : $output->coloredMessage('$1', 'light_yellow', 0);
        return preg_replace('/(\{.*?})/', $apply, $url);
    }
}