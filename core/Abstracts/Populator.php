<?php

namespace MkyCore\Abstracts;

use Closure;
use Exception;
use Faker\Factory;
use Faker\Generator;
use MkyCore\Console\Populator\Run;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use MkyCore\Populate\ArrayMerging;
use MkyCore\Populate\LoopMerging;
use MkyCore\Populate\RelationEntity;
use ReflectionException;

abstract class Populator
{
    protected string $manager = '';
    protected Generator $faker;
    protected int $count = 1;
    protected array $mergesData = [];
    protected array $addCallbacks = [];
    protected array $forCallbacks = [];
    protected array $addPivotCallbacks = [];
    protected array $lastSaves = [];
    protected array $order = [];
    protected int $index = 0;

    public function __construct()
    {
        $this->faker = Factory::create(config('app.locale', Factory::DEFAULT_LOCALE));
        $this->faker->setDefaultTimezone(config('app.timezone', 'Europe/Paris'));
    }

    /**
     * Set number of items to create
     *
     * @param int $count
     * @return $this
     */
    public function count(int $count): static
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Merge data to definition
     *
     * @param LoopMerging $loopMerging
     * @return $this
     */
    public function merge(LoopMerging $loopMerging): static
    {
        $loopMerging->setCount($this->count);
        for ($i = 0; $i < $this->count; $i++) {
            $oldMerge = $this->mergesData[$i] ?? [];
            $this->mergesData[$i] = array_merge($oldMerge, $loopMerging($i));
        }
        return $this;
    }

    /**
     * Add item populated by one-to-many relation
     *
     * @param callable $addCallback
     * @return $this
     */
    public function adds(callable $addCallback): static
    {
        $this->addCallbacks[] = $addCallback;
        $this->order[] = 'operationAdd';
        return $this;
    }

    /**
     * Add item populated to pivot by many-to-many relation
     *
     * @param callable $addPivotCallback
     * @return $this
     */
    public function addsOnPivot(callable $addPivotCallback): static
    {
        $this->addPivotCallbacks[] = $addPivotCallback;
        $this->order[] = 'operationPivot';
        return $this;
    }

    /**
     * Add item populated by many-to-one relation
     *
     * @param callable $forCallback
     * @return $this
     */
    public function for(callable $forCallback): static
    {
        $this->forCallbacks[] = $forCallback;
        return $this;
    }

    /**
     * Run the populator
     *
     * @return void
     * @throws Exception
     */
    public function populate(): void
    {
        $this->operationFor();
        $this->operationPopulate();
        $this->runOperation();
    }

    /**
     * Run the population for many-to-one relation
     *
     * @return void
     */
    private function operationFor(): void
    {
        if ($this->forCallbacks) {
            $forCallbacks = $this->forCallbacks;
            for ($i = 0; $i < count($forCallbacks); $i++) {
                $this->handleForCallback($forCallbacks[$i]);
            }
        }
    }

    /**
     * Populate one item to link with current population
     * Many-to-one relation
     *
     * @param Closure $forCallback
     * @return void
     */
    private function handleForCallback(Closure $forCallback): void
    {
        $forCallback(new RelationEntity($this));
    }

    /**
     * Populate database
     * @return void
     * @throws Exception
     */
    private function operationPopulate(): void
    {
        for ($i = 0; $i < $this->count; $i++) {
            $merge = $this->mergesData[$i] ?? [];
            $merged = array_merge($this->definition(), $merge);
            $this->lastSaves[] = $this->getManager()->create($merged);
            Run::$count++;
        }
    }

    /**
     * Database table schema definition
     *
     * @return array
     */
    public function definition(): array
    {
        return [];
    }

    /**
     * Get manager
     *
     * @return Manager
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     * @throws ReflectionException
     */
    public function getManager(): Manager
    {
        return app()->get($this->manager);
    }

    /**
     * Run operation
     *
     * @return void
     */
    private function runOperation(): void
    {
        $operation = $this->getCurrentOperation();
        if ($operation) {
            $this->$operation();
            $this->runOperation();
        }
    }

    /**
     * Get the current operation
     *
     * @return bool|string
     */
    private function getCurrentOperation(): bool|string
    {
        $currentOperation = $this->order[$this->index] ?? false;
        if ($currentOperation) {
            $this->index ++;
            return $currentOperation;
        }
        $this->index = 0;
        return false;
    }

    /**
     * Add operation
     *
     * @return void
     */
    private function operationAdd(): void
    {
        $lastSaves = $this->lastSaves;
        for ($i = 0; $i < count($lastSaves); $i++) {
            $last = $lastSaves[$i];
            if ($this->addCallbacks) {
                $addCallbacks = $this->addCallbacks;
                for ($j = 0; $j < count($addCallbacks); $j++) {
                    $this->handleAddCallback($addCallbacks[$j], $last);
                }
            }
        }
    }

    /**
     * Handle add callback
     *
     * @param Closure $addCallback
     * @param Entity $entity
     * @return void
     */
    private function handleAddCallback(Closure $addCallback, Entity $entity): void
    {
        $addCallback(new RelationEntity($this, $entity));
    }

    /**
     * Run adds operation
     * Many-to-many
     *
     * @return void
     */
    private function operationPivot(): void
    {
        $lastSaves = $this->lastSaves;
        for ($i = 0; $i < count($lastSaves); $i++) {
            $last = $lastSaves[$i];
            if ($this->addPivotCallbacks) {
                $addPivotCallbacks = $this->addPivotCallbacks;
                for ($k = 0; $k < count($addPivotCallbacks); $k++) {
                    $this->handleAddPivotCallback($addPivotCallbacks[$k], $last);
                }
            }
        }
    }

    /**
     * Handle addToPivot callback
     * Many-to-many
     *
     * @param Closure $addCallback
     * @param Entity $entity
     * @return void
     */
    private function handleAddPivotCallback(Closure $addCallback, Entity $entity): void
    {
        $addCallback(new RelationEntity($this, $entity));
    }

    /**
     * Get last records populated
     *
     * @return Entity[]
     */
    public function getLastSaves(): array
    {
        return $this->lastSaves;
    }

    /**
     * Get count
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }
}