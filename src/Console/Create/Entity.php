<?php

namespace MkyCore\Console\Create;

class Entity extends Create
{
    protected string $outputDirectory = 'Entities';

    protected function handleQuestions(array $replaceParams, array $params = []): array
    {
        $manager = $this->moduleOptions['manager'] ?? false;
        if(!$manager){
            do{
                $confirm = true;
                $manager = trim($this->sendQuestion('Enter the name of manager, or skip'));
                if ($manager) {
                    $confirm = $this->getModuleAndClass($manager, 'managers', 'manager', $replaceParams['module'] ?? '');
                    if($confirm){
                        $manager = $confirm;
                    }
                }
            }while(!$confirm);
        }
        if($manager){
            $manager = $this->setManager($manager);
        }
        $replaceParams['manager'] = $manager;
        return $replaceParams;
    }

    private function setManager(string $manager): string
    {
        return <<<MANAGER

/**
 * @Manager('$manager')
 */
MANAGER;
    }
}