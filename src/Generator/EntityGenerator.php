<?php

namespace ShopGenerator\Generator;

use Faker\Factory;
use RuntimeException;

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

    /** @var string pk name */
    protected $id;

    /** @var string last generated pk name */
    protected $lastId;

    /** @var string related class name */
    protected $class;

    /** @var string sql conditions to add to debug ouput */
    protected $sql;

    /** @var string  entity xml element name */
    protected $entityElementName;

    /** @var array mapping of existing relations (entities already created) */
    protected $relations;

    public function __construct($entityModel, $nbEntities, $relations)
    {
        $this->fakerGenerator = Factory::create('fr_FR');
        $this->nbEntities = $nbEntities;
        $this->entityModel = $entityModel;
        $fields = $this->entityModel['fields'];
        $this->id = $fields['id'];
        $this->class = $fields['class'];
        $this->sql = $fields['sql'];
        $this->entityElementName = strtolower($this->class);
        $this->relations = $relations;
        $this->increment = 1;
        $this->xml = new \SimpleXMLElement('<entity_category></entity_category>');
    }

    public function getRelations()
    {
        return $this->relations;
    }

    public function create()
    {
        $this->addFieldsDescription();
        $this->addEntityData();

        $xml = $this->xml->saveXML();
        print_r($xml);
    }

    private function addEntityData()
    {
        $child = $this->xml->addChild('entities');
        $this->addDefaultEntityData($child);
        for ($i = 1; $i <= $this->nbEntities; $i++) {
            $this->generateEntityData($child);
        }
    }

    private function generateEntityData(\SimpleXMLElement $element)
    {
        $child = $element->addChild($this->entityElementName);
        foreach ($this->entityModel['fields']['columns'] as $fieldName => $fieldDescription) {
            if (array_key_exists('relation', $fieldDescription)) {
                $this->addRelation($child, $fieldName, strtolower($fieldDescription['relation']));
            } elseif (array_key_exists('type', $fieldDescription)) {
                $this->addFakeAttribute($child, $fieldName, $fieldDescription);
            }
        }
        if ($this->lastId) {
            $this->relations[$this->entityElementName][] = $this->lastId;
        }
        $this->lastId = null;
    }

    private function addFakeAttribute(\SimpleXMLElement $element, $fieldName, $fieldDescription)
    {
        if ($fieldName === $this->id) {
            $fieldName = 'id';
        }
        $fakeType = $fieldDescription['type'];
        if ($fakeType === 'increment') {
            $value = $this->increment++;
            $element->addAttribute($fieldName, $value);
        } elseif (array_key_exists('args', $fieldDescription)) {
            $argsFunctions = $fieldDescription['args'];
            $value = call_user_func_array(array($this->fakerGenerator, $fakeType), $argsFunctions);
            $element->addAttribute($fieldName, $value);
        } else {
            $value = $this->fakerGenerator->{$fakeType};
            $element->addAttribute($fieldName, $value);
        }
        if ($fieldName === 'id') {
            $this->lastId = $value;
        }
    }

    private function addRelation(\SimpleXMLElement $element, $fieldName, $relationName)
    {
        if (empty($this->relations[$relationName])) {
            if ($relationName === $this->entityElementName) {
                throw new RuntimeException(
                    'You first need to define a default entry before using' .
                    ' a self-referenced relation with ' . $this->entityElementName);
            } else {
                throw new RuntimeException(
                    'You first need to define an entry before using' .
                    ' a relation with ' . $this->entityElementName);
            }
        }
        $value = $this->relations[$relationName][array_rand($this->relations[$relationName])];
        $element->addAttribute($fieldName, $value);
    }

    private function addDefaultEntityData(\SimpleXMLElement $element)
    {
        if (array_key_exists('default_entries', $this->entityModel)) {
            foreach ($this->entityModel['default_entries'] as $id => $fields) {
                $child = $element->addChild($this->entityElementName);
                $child->addAttribute('id', $id);
                $this->relations[$this->entityElementName][] = $id;
                foreach ($fields as $fieldName => $value) {
                    $child->addAttribute($fieldName, $value);
                }
            }
        }
    }

    private function addFieldsDescription()
    {
        $child = $this->xml->addChild('fields');
        $child->addAttribute('id', $this->id);
        $child->addAttribute('class', $this->class);
        $child->addAttribute('sql', $this->sql);

        foreach ($this->entityModel['fields']['columns'] as $key => $value) {
            $field = $child->addChild('field');
            $field->addAttribute('name', $key);
            if (array_key_exists('relation', $value)) {
                $field->addAttribute('relation', strtolower($value['relation']));
            }
        }
    }
}