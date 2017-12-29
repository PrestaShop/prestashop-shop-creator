<?php

namespace ShopGenerator\Generator;

use Faker\Factory;
use MathParser\Interpreting\Evaluator;
use MathParser\StdMathParser;
use RuntimeException;
use Doctrine\Common\Inflector\Inflector;

class EntityGenerator
{
    /** @var \Faker\Generator */
    protected $fakerGenerator;

    /** @var int */
    protected $nbEntities;

    /** @var array */
    protected $entityModel;

    /** @var \SimpleXMLElement */
    protected $xml;

    /** @var int */
    protected $increment;

    /** @var string unique id */
    protected $id = 'id';

    /** @var string primary key */
    protected $primary = '';

    /** @var boolean return true if an element contains an id */
    protected $hasId;

    /** @var string related class name */
    protected $class = '';

    /** @var string img path */
    protected $imgPath = '';

    /** @var string sql conditions to add to debug ouput */
    protected $sql = '';

    /** @var string  entity xml element name */
    protected $entityElementName;

    /** @var array mapping of existing relations (entities already created) */
    protected $relations;

    /** @var array list of existing relations for each entities */
    protected $relationList;

    /** @var array list of already generated values for the entity relations */
    protected $relationValues = [];

    /** @var array list of values associated with each fields for a given entity */
    protected $fieldValues = [];

    /**
     * EntityGenerator constructor.
     *
     * @param array $entityModel
     * @param int $nbEntities
     * @param array $relations
     * @param array $relationList
     */
    public function __construct($entityElementName, $entityModel, $nbEntities, $relations, $relationList)
    {
        echo 'Generating '.$entityElementName." entities\n";
        $this->fakerGenerator = Factory::create('fr_FR');
        $this->nbEntities = $nbEntities;
        $this->entityModel = $entityModel;
        $this->relationList = $relationList;
        $fields = $this->entityModel['fields'];
        if (isset($fields['primary'])) {
            $this->primary = $fields['primary'];
        }
        if (isset($fields['id'])) {
            $this->id = $fields['id'];
        }
        if (isset($fields['class'])) {
            $this->class = $fields['class'];
        }
        if (isset($fields['sql'])) {
            $this->sql = $fields['sql'];
        }
        if (isset($fields['image'])) {
            $this->imgPath = $fields['image'];
        }
        $this->entityElementName = $entityElementName;
        $this->relations = $relations;
        $this->increment = 1;
        $this->xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<entity_'.$this->entityElementName.'></entity_'.$this->entityElementName.'>'
        );
    }

    /**
     * @return array
     */
    public function getRelationList()
    {
        return $this->relationList;
    }

    /**
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * @throws RuntimeException
     */
    public function create()
    {
        $this->addFieldsDescription();
        $this->addEntityData();
    }

    public function save($outputPath)
    {
        // beautify the output
        $dom = dom_import_simplexml($this->xml)->ownerDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $output = $dom->saveXML();
        file_put_contents($outputPath.'/'.$this->entityElementName.'.xml', $output);
    }

    /**
     * @throws RuntimeException
     */
    private function addEntityData()
    {
        $child = $this->xml->addChild('entities');
        $this->addDefaultEntityData($child);
        if ($this->primary) {
            $primaryFields = explode(',', $this->primary);
            foreach ($primaryFields as $primaryField) {
                $primaryField = trim($primaryField);
                $fieldInfos = $this->entityModel['fields']['columns'][$primaryField];
                if (isset($fieldInfos['relation'])) {
                    $relationName = Inflector::tableize($fieldInfos['relation']);
                    if (array_key_exists('nullable', $fieldInfos) && $fieldInfos['nullable'] == true) {
                        $nullable = true;
                    } else {
                        $nullable = false;
                    }

                    $relations[] = ['name' => $relationName, 'nullable' => $nullable];
                }
            }
            $this->walkOnRelations($child, $relations);
        } else {
            for ($i = 1; $i <= $this->nbEntities; $i++) {
                $this->generateEntityData($child);
            }
        }
    }

    /**
     * Iterate recursively on every existing relations
     *
     * @param \SimpleXMLElement $child
     * @param array             $relations
     * @param array             $relationValues
     *
     * @throws RuntimeException
     */
    private function walkOnRelations(\SimpleXMLElement $child, $relations, $relationValues = array())
    {
        $relationInfos = array_pop($relations);
        $relationName = $relationInfos['name'];
        $this->checkRelation($relationName);

        $currentRelations = $this->relations[$relationName];
        if ($relationInfos['nullable']) {
            $currentRelations[] = array('id' => 0);
        }
        foreach ($currentRelations as $relation) {
            $relationValues[$relationName] = $relation;

            if (!empty($relations)) {
                $this->walkOnRelations($child, $relations, $relationValues);
            } else {
                $this->generateEntityData($child, $relationValues);
            }
        }
    }

    /**
     * @param string $relationName
     *
     * @throws RuntimeException
     */
    private function checkRelation($relationName)
    {
        if (empty($this->relations[$relationName])) {
            if ($relationName === $this->entityElementName) {
                throw new RuntimeException(
                    'You first need to define a default entry before using' .
                    ' a self-referenced relation with ' . $this->entityElementName
                );
            } else {
                throw new RuntimeException(
                    'You first need to define an entry before using' .
                    ' a relation with ' . $relationName
                );
            }
        }
    }

    /**
     * @param \SimpleXMLElement $element
     * @param array             $relationValues set of values used for relation, instead of a random one
     *
     * @throws RuntimeException
     */
    private function generateEntityData(\SimpleXMLElement $element, $relationValues = null)
    {
        $this->fieldValues = [];
        $child = $element->addChild($this->entityElementName);
        $this->relationList[$this->entityElementName] = [];
        foreach ($this->entityModel['fields']['columns'] as $fieldName => $fieldDescription) {
            if ($fieldName === 'exclusive_fields') {
                // select randomly one of the field
                $fieldName = array_rand($fieldDescription);
                $fieldDescription = $fieldDescription[$fieldName];
            }
            if (array_key_exists('relation', $fieldDescription)) {
                $this->relationList[$this->entityElementName][$fieldName] = Inflector::tableize($fieldDescription['relation']);
            } elseif (array_key_exists('value', $fieldDescription)) {
                $this->addValueAttribute($child, $fieldName, $fieldDescription['value']);
            } elseif (array_key_exists('type', $fieldDescription)) {
                $this->addFakeAttribute($child, $fieldName, $fieldDescription);
            }
        }
        // add all the relations
        foreach ($this->relationList[$this->entityElementName] as $fieldName => $relation) {
            $this->addRelation(
                $child,
                $fieldName,
                $relation,
                $relationValues,
                $this->entityElementName,
                (array_key_exists('nullable', $fieldDescription) && $fieldDescription['nullable'] == true)
            );
        }

        if ($this->hasId) {
            $this->relations[$this->entityElementName][] = $child;
        }
        $this->hasId = null;
    }

    /**
     * Add & evaluate value attribute
     *
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param string            $value
     */
    private function addValueAttribute(\SimpleXMLElement $element, $fieldName, $value)
    {
        if (preg_match_all('/\{.+?\}/uis', $value, $matches)) {
            $savedValue = $value;
            foreach ($matches[0] as $match) {
                $match = str_replace(array('{','}'), '', $match);
                if (array_key_exists($match, $this->fieldValues)) {
                    $value = str_replace('{'.$match.'}', $this->fieldValues[$match], $value);
                }
            }
            if ($savedValue != $value) {
                $parser = new StdMathParser();
                $AST = $parser->parse($value);
                $evaluator = new Evaluator();

                $value = $AST->accept($evaluator);
            }
        }
        $this->addAttribute($element, $fieldName, $value);
    }

    /**
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param array             $fieldDescription
     */
    private function addFakeAttribute(\SimpleXMLElement $element, $fieldName, $fieldDescription)
    {
        if ($fieldName === $this->id) {
            // since it's an id, set it as unique
            $fieldDescription['unique'] = true;
        }
        $fakeType = $fieldDescription['type'];
        if ($fakeType === 'increment') {
            $value = $this->increment++;
        } elseif (array_key_exists('args', $fieldDescription)) {
            $argsFunctions = $fieldDescription['args'];
            $value = call_user_func_array(array($this->fakerGenerator, $fakeType), $argsFunctions);
            if (is_array($value)) { // in case of words
                $value = implode(' ', $value);
            }
            if ($fakeType === 'boolean') {
                $value = (int)$value;
            }
        } else {
            if (array_key_exists('unique', $fieldDescription)) {
                $value = $this->fakerGenerator->unique()->{$fakeType};
            } else {
                $value = $this->fakerGenerator->{$fakeType};
            }
            if ($fakeType === 'boolean') {
                $value = (int)$value;
            }
        }

        $this->addAttribute($element, $fieldName, $value);

        if ($fieldName === $this->id) {
            if ($fieldName !== 'id') {
                $this->addAttribute($element, 'id', $value);
            }
            $this->hasId = true;
        }
    }

    /**
     * The function takes care of choosing a random value from a relation, handling dependencies as well
     *
     * @param string $relationName
     * @param string $entityName
     *
     * @return mixed
     */
    private function getRandomRelationId($relationName, $entityName)
    {
        $relations = $this->relationList[$entityName];
        foreach ($relations as $relation) {
            if (array_key_exists($relation, $this->relationList)) {
                $relatedRelations = $this->relationList[$relation];
                $dependencies = array_intersect($relations, $relatedRelations);
            }
        }
        while (1) {
            $randomRelation = $this->relations[$relationName][array_rand($this->relations[$relationName])];
            // if there's no dependencies, any random value is ok
            if (empty($dependencies) || !in_array($relationName, $dependencies)) {
                break;
            }

            // but if we do have dependencies, check the value we have chosen match the random one
            foreach ($dependencies as $dependency) {
                if (array_key_exists($dependency, $this->relationValues)) {
                    if ($randomRelation['id'] != $this->relationValues[$relationName]) {
                        // no match, try again!
                        continue 2;
                    }
                }
            }
            break;
        }

        $id = $randomRelation['id'];
        $this->relationValues[$relationName] = $id;
        return $id;
    }

    /**
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param string            $relationName
     * @param array             $relationValues
     * @param string            $entityName
     * @param bool              $canBeNull
     *
     * @throws RuntimeException
     */
    private function addRelation(
        \SimpleXMLElement $element,
        $fieldName,
        $relationName,
        $relationValues,
        $entityName,
        $canBeNull = false
    ) {
        $this->checkRelation($relationName);

        if ($relationValues && array_key_exists($relationName, $relationValues)) {
            $value = (string)$relationValues[$relationName]['id'];
        } else {
            if ($canBeNull && mt_rand(0, 1)) {
                $value = 0;
            } else {
                $value = (string)$this->getRandomRelationId($relationName, $entityName);
            }
        }
        $this->addAttribute($element, $fieldName, $value);
    }

    /**
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param string            $value
     */
    private function addAttribute(\SimpleXMLElement $element, $fieldName, $value)
    {
        $this->fieldValues[$fieldName] = $value;
        $element->addAttribute($fieldName, $value);
    }

    /**
     * @param \SimpleXMLElement $element
     */
    private function addDefaultEntityData(\SimpleXMLElement $element)
    {
        if (array_key_exists('entities', $this->entityModel)) {
            foreach ($this->entityModel['entities'] as $id => $fields) {
                $child = $element->addChild($this->entityElementName);
                $this->addAttribute($child, 'id', $id);
                foreach ($fields as $fieldName => $value) {
                    $this->addAttribute($child, $fieldName, $value);
                }
                $this->relations[$this->entityElementName][] = $child;
            }
        }
    }

    /**
     *
     */
    private function addFieldsDescription()
    {
        $child = $this->xml->addChild('fields');
        if ($this->id) {
            $child->addAttribute('id', $this->id);
        } else {
            $child->addAttribute('primary', $this->primary);
        }
        $child->addAttribute('class', $this->class);
        if ($this->sql) {
            $child->addAttribute('sql', $this->sql);
        }
        if ($this->imgPath) {
            $child->addAttribute('image', $this->imgPath);
        }

        foreach ($this->entityModel['fields']['columns'] as $key => $value) {
            if ($key === 'exclusive_fields') {
                foreach ($value as $subkey => $subvalue) {
                    $this->addField($child, $subkey, $subvalue);
                }
            } else {
                $this->addField($child, $key, $value);
            }
        }
    }

    private function addField(\SimpleXMLElement $element, $key, $value)
    {
        $field = $element->addChild('field');
        $field->addAttribute('name', $key);
        if (array_key_exists('relation', $value)) {
            $field->addAttribute('relation', strtolower($value['relation']));
        }
    }
}
