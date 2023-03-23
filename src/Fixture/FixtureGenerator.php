<?php

namespace ShopGenerator\Fixture;

use Faker\Factory;
use Faker\Generator;
use MathParser\Interpreting\Evaluator;
use MathParser\StdMathParser;

class FixtureGenerator
{
    private int $increment = 0;
    private \Faker\Generator $faker;
    private FixtureDefinitionCollection $definitions;

    private array $configuration;
    private array $entitiesByDefinition = [];
    private array $fakersLang;
    private array $entitiesLang = [];
    // i don't know why this is needed, but inherit from legacy code.
    private $fieldValues = [];

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
        $this->faker = Factory::create();

        foreach ($this->getLangs() as $lang) {
            $this->fakersLang[$lang] = Factory::create($lang);
        }
    }

    /**
     * Compute a value depending on specific conditions
     *
     * @param string $fieldName
     * @param string $condition
     * @param string $valueConditionTrue
     * @param string $valueConditionFalse
     *
     * @return string
     */
    private function computeConditionalValue($fieldName, $condition, $valueConditionTrue, $valueConditionFalse)
    {
        if (preg_match('/([a-zA-Z_]+)\((.+?)\)/uis', $condition, $matches)) {
            $args[] = $fieldName;
            $args[] = $matches[2];
            $condition = call_user_func_array([$this, $matches[1]], $args);
        }

        if ($this->evaluateValue($condition)) {
            return $valueConditionTrue;
        }

        return $valueConditionFalse;
    }

    /**
     * Evaluate the value by resolving fields which could be present in the value
     *
     * @param string $value
     *
     * @return string
     */
    private function evaluateValue($value)
    {
        if (preg_match_all('/\{.+?\}/uis', $value, $matches)) {
            $savedValue = $value;
            foreach ($matches[0] as $match) {
                $match = str_replace(['{', '}'], '', $match);
                if (array_key_exists($match, $this->fieldValues)) {
                    $value = str_replace('{' . $match . '}', $this->fieldValues[$match], $value);
                }
            }
            if ($savedValue != $value) {
                $parser = new StdMathParser();
                $AST = $parser->parse($value);
                $evaluator = new Evaluator();

                $value = $AST->accept($evaluator);
            }
        }

        return $value;
    }

    /**
     * Check if the evaluated $value is different from the latest one
     *
     * @param string $fieldName
     * @param string $value
     *
     * @return bool
     */
    private function isNewValue($fieldName, $value): bool
    {
        static $lastValue = [];
        $value = $this->evaluateValue($value);
        $isNewValue = false;
        if (!array_key_exists($fieldName, $lastValue) || $lastValue[$fieldName] !== $value) {
            $isNewValue = true;
        }
        $lastValue[$fieldName] = $value;

        return $isNewValue;
    }

    public function setInitialData(array $data): self
    {
        $this->entitiesByDefinition = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getEntitiesByDefinition(): array
    {
        return $this->entitiesByDefinition;
    }

    /**
     * @return array
     */
    public function getEntitiesTranslations(): array
    {
        return $this->entitiesLang;
    }

    public function generateForDefinition(FixtureDefinition $definition): void
    {
        // Import data, unless some data already exists to avoid some conflicts
        if (array_key_exists($definition->getFixtureClass(), $this->entitiesByDefinition)) {
            return;
        }

        $this->entitiesByDefinition[$definition->getFixtureClass()] = [];
        $fixtureClass = $definition->getFixtureClass();
        $quantity = $this->configuration[$fixtureClass] ?? 0;

        for ($i = 0; $i < $quantity; ++$i) {
            $fixtureId = sprintf('%s_%s', $fixtureClass, $i);
            [$data, $translations] = $this->generateRow($definition);
            if ($definition->hasLang()) {
                $this->entitiesLang[$fixtureClass][$fixtureId] = $translations;
            }
            $this->entitiesByDefinition[$fixtureClass][$fixtureId] = $data;
        }
    }

    private function generateRow(FixtureDefinition $definition): array
    {
        $data = [];
        $langs = [];
        foreach ($definition->getColumns() as $column => $columnDescription) {
            $data['@' . $column] = $this->processField($column, $columnDescription, $definition, $this->faker);

            foreach ($definition->getLocalizedColumns() as $column => $defaultDataForColumn) {
//                dump($data, $column);
                $data[$column] = $this->processField($column, $columnDescription, $definition, $this->faker);
            }
        }

        if ($definition->hasLang()) {
            foreach ($this->getLangs() as $lang) {
                foreach ($definition->getLocalizedColumns() as $column => $columnDescription) {
                    $langs[$lang][$column] = $this->processField($column, $columnDescription, $definition, $this->fakersLang[$lang]);
                }
            }
        }

        if ($definition->getId() !== null) {
            $data['@id'] = $data['@' . $definition->getId()];
        }

        return [$data, $langs];
    }

    private function processField(string $fieldName, array $fieldDescription, FixtureDefinition $definition, Generator $generator): mixed
    {
        // hard-coded-value
        if (array_key_exists('value', $fieldDescription)) {
            return $fieldDescription['value'];
        }

        // relation to another entity
        if (array_key_exists('relation', $fieldDescription)) {
            return $this->resolveRelation($definition, $fieldDescription['relation']);
        }

        $type = $fieldDescription['type'] ?? null;

        if (null === $type) {
            throw new \RuntimeException(sprintf('Unknow type %s', $fieldName));
        }

        return $this->generateValue($type, $fieldName, $fieldDescription, $definition, $generator);
    }

    private function resolveRelation(FixtureDefinition $definition, string $model): ?string
    {
        $this->generateForDefinition($this->definitions->getDefinitionByModel($model));
        $resolvedDefinition = $this->definitions->getDefinitionByModel($model);

        $randomKey = array_rand($this->entitiesByDefinition[$resolvedDefinition->getFixtureClass()]);

        return $this->entitiesByDefinition[$resolvedDefinition->getFixtureClass()][$randomKey]['@id'];
    }

    private function generateValue(string $type, string $fieldName, array $fieldDescription, FixtureDefinition $definition, Generator $generator): mixed
    {
        $value = 'null';

        if ($type === 'conditionalValue') {
//            $argsFunctions[] = $fieldName;
//            $argsFunctions = array_merge($argsFunctions, $fieldDescription['args']);
//            $value = call_user_func_array([$this, 'computeConditionalValue'], $argsFunctions);
        } elseif ($type === 'increment') {
            $value = $this->increment++;

            return sprintf('%s_%s', $definition->getFixtureClass(), $value);
        } elseif (array_key_exists('args', $fieldDescription)) {
            $argsFunctions = $fieldDescription['args'];
            $value = call_user_func_array([$generator, $type], $argsFunctions);
            if (is_array($value)) { // in case of words
                $value = implode(' ', $value);
            }
            if ($type === 'boolean') {
                $value = (int) $value;
            }
        } else {
            if (array_key_exists('unique', $fieldDescription)) {
                $value = call_user_func_array([$generator->unique(), $type], []);
            } else {
                $value = call_user_func_array([$generator, $type], []);
            }
            if ($type === 'boolean') {
                $value = (int) $value;
            }
        }

        return $value;
    }

    private function getLangs(): array
    {
        return $this->configuration['langs'];
    }

    public function setDefinitionCollection(FixtureDefinitionCollection $collection): void
    {
        $this->definitions = $collection;
    }
}
