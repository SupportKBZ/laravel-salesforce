<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:sobject')]
class MakeSObjectCommand extends GeneratorCommand
{
    /** @var string */
    protected $name = 'make:sobject';

    /** @var string */
    protected $description = 'Create a new Salesforce SObject class';

    /** @var string */
    protected $type = 'SObject';

    /**
     * Retourne le chemin du stub de generation.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/sobject.stub';
    }

    /**
     * Retourne le namespace par defaut pour la classe generee.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Salesforce';
    }

    /**
     * Remplace les placeholders du stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        $class = class_basename($name);
        $table = $this->option('table');
        $tableName = is_string($table) && $table !== '' ? $table : $class;

        return str_replace(['{{ table }}', '{{table}}'], $tableName, $stub);
    }

    /**
     * Options de la commande.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['table', 't', InputOption::VALUE_OPTIONAL, 'The Salesforce sObject API name'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
