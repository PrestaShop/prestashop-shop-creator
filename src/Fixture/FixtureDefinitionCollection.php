<?php

namespace ShopGenerator\Fixture;

class FixtureDefinitionCollection
{
    /**
     * @var FixtureDefinition[]
     */
    private array $definitions;
    private array $definitionAliases;

    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;

        foreach ($this->definitions as $definition) {
            $this->definitionAliases[$definition->getModel()] = $definition;
        }
    }

    public function getDefinition(string $definition): FixtureDefinition
    {
        $definition = $this->definitions[$definition] ?? null;

        if (null === $definition) {
            throw new \RuntimeException(sprintf('"%s" does not exists in definitions', $definition));
        }

        return $definition;
    }

    public function getDefinitionByModel(string $model): FixtureDefinition
    {
        $definition = $this->definitionAliases[$model] ?? null;

        if (null === $definition) {
            throw new \RuntimeException(sprintf('"%s" does not exists in aliases', $model));
        }

        return $definition;
    }
}
