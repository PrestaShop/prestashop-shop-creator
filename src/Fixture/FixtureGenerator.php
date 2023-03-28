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
    private $importedDefinitions = [];

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

        // for each entities inside xml data, we hardcode the same values for all langs
        foreach ($this->getLangs() as $lang) {

            foreach ($this->entitiesByDefinition as $definition => $data) {
                $multilang = false;
                try {
                    $multilang = !empty($this->definitions->getDefinition($definition)->getLocalizedColumns());
                } catch (\RuntimeException $e) {
                    // definition does not exist, so no multilang
                }
                if (!$multilang) {
                    continue;
                }

                foreach ($data as $id => $datum) {
                    $values = [];

                    foreach ($datum as $column => $value) {
//                        dump($value, $column, $id); die;
                        if (str_starts_with('@', $column)) {
                            continue;
                        }

                        $values[$column] = $value;
                    }

                    $this->entitiesLang[$definition][$id][$lang] = $values;
                }
            }
        }

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
        $fixtureClass = $definition->getFixtureClass();

        if (!array_key_exists($fixtureClass, $this->entitiesByDefinition)) {
            $this->entitiesByDefinition[$fixtureClass] = [];
        }

        $quantity = $this->configuration[$fixtureClass] ?? 0;

        for ($i = 0; $i < $quantity; ++$i) {
            [$fixtureId, $data, $translations] = $this->generateRow($definition, $i);
            if ($definition->hasLang()) {
                $this->entitiesLang[$fixtureClass][$fixtureId] = $translations;
            }
            $this->entitiesByDefinition[$fixtureClass][$fixtureId] = $data;
        }
    }

    private function generateRow(FixtureDefinition $definition, int $i): array
    {
        $data = [];
        $langs = [];
        foreach ($definition->getColumns() as $column => $columnDescription) {
            $data['@' . $column] = $this->processField($column, $columnDescription, $definition, $this->faker);

//
//            foreach ($definition->getLocalizedColumns() as $column2 => $defaultDataForColumn) {
////                dump($data, $column);
//                $data[$column2] = $this->processField($column, $columnDescription, $definition, $this->faker);
//            }
        }

        if ($definition->hasLang()) {
            foreach ($this->getLangs() as $lang) {
                foreach ($definition->getLocalizedColumns() as $column => $columnDescription) {
                    $langs[$lang][$column] = $this->processField($column, $columnDescription, $definition, $this->fakersLang[$lang]);
                }
            }
        }

        if ($definition->getId() !== null) {
            $id = $data['@' . $definition->getId()];
            $fixtureId = sprintf('%s_%d_%s',$definition->getFixtureClass(), $i, $id);

            $data['@id'] = $fixtureId;
        } else {
            $fixtureId =  sprintf('%s_%d',$definition->getFixtureClass(), $i);
        }

        return [$fixtureId, $data, $langs];
    }

    private function processField(string $fieldName, array $fieldDescription, FixtureDefinition $definition, Generator $generator): mixed
    {
        // hard-coded-value
        if (array_key_exists('value', $fieldDescription)) {
            return $fieldDescription['value'];
        }

        // relation to another entity
        if (array_key_exists('relation', $fieldDescription)) {
            return $this->resolveRelation($fieldDescription['relation']);
        }

        $type = $fieldDescription['type'] ?? null;

        if (null === $type) {
            throw new \RuntimeException(sprintf('Unknow type %s', $fieldName));
        }

        return $this->generateValue($type, $fieldName, $fieldDescription, $definition, $generator);
    }

    private function resolveRelation(string $model): ?string
    {
        $resolvedDefinition = $this->definitions->getDefinitionByModel($model);
        // Import data, unless some data already exists to avoid some conflicts
        if (!array_key_exists($resolvedDefinition->getFixtureClass(), $this->importedDefinitions)) {
            $this->importedDefinitions[$resolvedDefinition->getFixtureClass()] = true;
            $this->generateForDefinition($resolvedDefinition);
        }

        if (empty($this->entitiesByDefinition[$resolvedDefinition->getFixtureClass()])) {
            throw new \RuntimeException(sprintf('"%s" has no fixture', $model));
        }

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
