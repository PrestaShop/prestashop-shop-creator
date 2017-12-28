<?php

namespace ShopGenerator\Generator;

use Faker\Factory;
use RuntimeException;
use Doctrine\Common\Inflector\Inflector;

class EntityGenerator {
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

    /**
     * EntityGenerator constructor.
     *
     * @param array $entityModel
     * @param int $nbEntities
     * @param array $relations
     */
    public function __construct($entityElementName, $entityModel, $nbEntities, $relations)
    {
        $this->fakerGenerator = Factory::create('fr_FR');
        $this->nbEntities = $nbEntities;
        $this->entityModel = $entityModel;
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
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'.
            '<entity_'.$this->entityElementName.'></entity_'.$this->entityElementName.'>'
        );
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
            foreach($primaryFields as $primaryField) {
                $primaryField = trim($primaryField);
                $fieldInfos = $this->entityModel['fields']['columns'][$primaryField];
                if (isset($fieldInfos['relation'])) {
                    $relationName = Inflector::tableize($fieldInfos['relation']);
                    $relations[] = $relationName;
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
    private function walkOnRelations(\SimpleXMLElement $child, $relations, $relationValues = array()) {
        $relationName = array_pop($relations);
        $this->checkRelation($relationName);

        foreach($this->relations[$relationName] as $relation) {
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
                    ' a self-referenced relation with ' . $this->entityElementName);
            } else {
                throw new RuntimeException(
                    'You first need to define an entry before using' .
                    ' a relation with ' . $relationName);
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
        $child = $element->addChild($this->entityElementName);
        foreach ($this->entityModel['fields']['columns'] as $fieldName => $fieldDescription) {
            if ($fieldName === 'exclusive_fields') {
                // select randomly one of the field
                $fieldName = array_rand($fieldDescription);
                $fieldDescription = $fieldDescription[$fieldName];
            }
            if (array_key_exists('relation', $fieldDescription)) {
                $this->addRelation(
                    $child,
                    $fieldName,
                    Inflector::tableize($fieldDescription['relation']),
                    $relationValues
                );
            } elseif (array_key_exists('value', $fieldDescription)) {
                $child->addAttribute($fieldName, $fieldDescription['value']);
            } elseif (array_key_exists('type', $fieldDescription)) {
                $this->addFakeAttribute($child, $fieldName, $fieldDescription);
            }
        }
        if ($this->hasId) {
            $this->relations[$this->entityElementName][] = $child;
        }
        $this->hasId = null;
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
            $element->addAttribute($fieldName, $value);
        } elseif (array_key_exists('args', $fieldDescription)) {
            $argsFunctions = $fieldDescription['args'];
            $value = call_user_func_array(array($this->fakerGenerator, $fakeType), $argsFunctions);
            if (is_array($value)) { // in case of words
                $value = implode(' ', $value);
            }
            if ($fakeType === 'boolean') {
                $value = (int)$value;
            }
            $element->addAttribute($fieldName, $value);
        } else {
            if (array_key_exists('unique', $fieldDescription)) {
                $value = $this->fakerGenerator->unique()->{$fakeType};
            } else {
                $value = $this->fakerGenerator->{$fakeType};
            }
            if ($fakeType === 'boolean') {
                $value = (int)$value;
            }
            $element->addAttribute($fieldName, $value);
        }
        if ($fieldName === $this->id) {
            if ($fieldName !== 'id') {
                $element->addAttribute('id', $value);
            }
            $this->hasId = true;
        }
    }

    /**
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param string            $relationName
     * @param array             $relationValues
     *
     * @throws RuntimeException
     */
    private function addRelation(\SimpleXMLElement $element, $fieldName, $relationName, $relationValues)
    {
        $this->checkRelation($relationName);

        if ($relationValues && array_key_exists($relationName, $relationValues)) {
            $value = $relationValues[$relationName]['id'];
        } else {
            $value = $this->relations[$relationName][array_rand($this->relations[$relationName])]['id'];
        }
        $element->addAttribute($fieldName, (string)$value);
    }

    /**
     * @param \SimpleXMLElement $element
     */
    private function addDefaultEntityData(\SimpleXMLElement $element)
    {
        if (array_key_exists('entities', $this->entityModel)) {
            foreach ($this->entityModel['entities'] as $id => $fields) {
                $child = $element->addChild($this->entityElementName);
                $child->addAttribute('id', $id);
                foreach ($fields as $fieldName => $value) {
                    $child->addAttribute($fieldName, $value);
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