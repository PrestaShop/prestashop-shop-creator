<?php

namespace ShopGenerator\Generator;

use Faker\Factory;
use Faker\Generator;
use MathParser\Interpreting\Evaluator;
use MathParser\StdMathParser;
use RuntimeException;
use Doctrine\Common\Inflector\Inflector;

class EntityGenerator
{
    /** @var \Faker\Generator */
    protected $fakerGenerator;

    /** @var \Faker\Generator[] */
    protected $localeFakerGenerator;

    /** @var int */
    protected $nbEntities;

    /** @var array */
    protected $entityModel;

    /** @var \SimpleXMLElement */
    protected $xml;

    /** @var \SimpleXMLElement[] */
    protected $xmlLang = null;

    /** @var int */
    protected $increment;

    /** @var string unique id */
    protected $id = 'id';

    /** @var string primary key */
    protected $primary = '';

    /** @var boolean */
    protected $hasLang = false;

    /** @var string related class name */
    protected $class = '';

    /** @var string img path */
    protected $imgPath = '';

    /** @var int */
    protected $imgWidth = 200;

    /** @var int */
    protected $imgHeight = 200;

    /** @var string the category of image we want to generate */
    protected $imgCategory = null;

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
    public function __construct($entityElementName, $entityModel, $nbEntities, $relations, $relationList, $langs)
    {
        echo 'Generating '.$entityElementName." entities\n";
        $this->fakerGenerator = Factory::create();
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
        if (isset($fields['image_width'])) {
            $this->imgWidth = $fields['image_width'];
        }
        if (isset($fields['image_height'])) {
            $this->imgHeight = $fields['image_height'];
        }
        if (isset($fields['image_category'])) {
            $this->imgCategory = $fields['image_category'];
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
        if (array_key_exists('fields_lang', $this->entityModel)) {
            $this->hasLang = true;
            foreach($langs as $lang) {
                $langLocale = explode('_', $lang);
                $this->localeFakerGenerator[$langLocale[0]] = Factory::create($lang);
                $this->xmlLang[$langLocale[0]] = new \SimpleXMLElement(
                    '<?xml version="1.0" encoding="UTF-8"?>' .
                    '<entity_' . $this->entityElementName . '></entity_' . $this->entityElementName . '>'
                );
            }
        }
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

    public function save()
    {
        $outputPath = __DIR__.'/../../generated_data';
        // beautify the output
        $dom = dom_import_simplexml($this->xml)->ownerDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $output = $dom->saveXML();
        @mkdir($outputPath.'/data/', 0777, true);
        file_put_contents($outputPath.'/data/'.$this->entityElementName.'.xml', $output);
        if ($this->hasLang) {
            foreach($this->xmlLang as $lang => $xmlLang) {
                $domLang = dom_import_simplexml($xmlLang)->ownerDocument;
                $domLang->preserveWhiteSpace = false;
                $domLang->formatOutput = true;
                $outputLang = $domLang->saveXML();
                @mkdir($outputPath . '/langs/' . $lang . '/data/', 0777, true);
                file_put_contents($outputPath . '/langs/' . $lang . '/data/' . $this->entityElementName . '.xml', $outputLang);
            }
        }
    }

    /**
     * Fill entities data for the main xml file
     *
     * @throws RuntimeException
     */
    private function addEntityData()
    {
        $child = $this->xml->addChild('entities');
        $this->addCustomEntityData($child, $this->xmlLang);
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
                $idValue = $this->generateEntityData($child);
                if ($this->hasLang) {
                    $this->generateLangEntityData($idValue);
                }
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
                $idValue = $this->generateEntityData($child, $relationValues);
                if ($this->hasLang) {
                    $this->generateLangEntityData($idValue);
                }
            }
        }
    }

    /**
     * Check a relation is properly filled
     *
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
     * Generate main xml files
     *
     * @param \SimpleXMLElement $element
     * @param array             $relationValues set of values used for relation, instead of a random one
     *
     * @return string
     * @throws RuntimeException
     */
    private function generateEntityData(\SimpleXMLElement $element, $relationValues = null)
    {
        $this->fieldValues = [];
        $child = $element->addChild($this->entityElementName);
        $this->relationList[$this->entityElementName] = [];
        $idValue = null;
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
                $value = $this->addFakerAttribute($child, $fieldName, $fieldDescription);
                if ($idValue === null && $value !== null) {
                    $idValue = $value;
                }
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

        if ($idValue !== null) {
            $this->relations[$this->entityElementName][] = $child;
            if ($this->imgPath) {
                @mkdir(__DIR__.'/../../generated_data/img/'.$this->imgPath, 0777, true);
                $this->fakerGenerator->image(
                    __DIR__.'/../../generated_data/img/'.$this->imgPath,
                    $this->imgWidth,
                    $this->imgHeight,
                    $this->imgCategory
                );
            }
        }

        return $idValue;
    }

    /**
     * Generate the lang xml files
     *
     * @param string $idValue the id value generated in the main xml file
     */
    private function generateLangEntityData($idValue)
    {
        $childLangs = [];
        foreach($this->xmlLang as $lang => $xmlLang) {
            $childLangs[$lang] = $xmlLang->addChild($this->entityElementName);
            if (array_key_exists('id_shop', $this->entityModel['fields_lang'])) {
                $childLangs[$lang]->addAttribute('id_shop', $this->entityModel['fields_lang']['id_shop']);
            }
        }

        foreach($childLangs as $locale => $child) {
            $this->addValueAttribute($child, 'id', $idValue);
            foreach ($this->entityModel['fields_lang']['columns'] as $fieldName => $fieldDescription) {
                if (array_key_exists('value', $fieldDescription)) {
                    $this->addValueAttribute($child, $fieldName, $fieldDescription['value']);
                } elseif (array_key_exists('type', $fieldDescription)) {
                    $this->addFakerAttribute(
                        $child,
                        $fieldName,
                        $fieldDescription,
                        $this->localeFakerGenerator[$locale]
                    );
                }
            }
        }
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
     * Add an attribute generated by the corresponding faker rule
     *
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param array             $fieldDescription
     * @param Generator         $fakerGenerator
     *
     * @return string
     */
    private function addFakerAttribute(
        \SimpleXMLElement $element,
        $fieldName,
        $fieldDescription,
        $fakerGenerator = null
    )
    {
        $idValue = null;
        // no custom generator, use the default one
        if ($fakerGenerator === null) {
            $fakerGenerator = $this->fakerGenerator;
            $inLangFile = false;
        } else {
            $inLangFile = true;
        }
        if ($fieldName === $this->id) {
            // since it's an id, set it as unique
            $fieldDescription['unique'] = true;
        }
        $fakeType = $fieldDescription['type'];
        if ($fakeType === 'increment') {
            $value = $this->increment++;
        } elseif (array_key_exists('args', $fieldDescription)) {
            $argsFunctions = $fieldDescription['args'];
            $value = call_user_func_array(array($fakerGenerator, $fakeType), $argsFunctions);
            if (is_array($value)) { // in case of words
                $value = implode(' ', $value);
            }
            if ($fakeType === 'boolean') {
                $value = (int)$value;
            }
        } else {
            if (array_key_exists('unique', $fieldDescription)) {
                $value = $fakerGenerator->unique()->{$fakeType};
            } else {
                $value = $fakerGenerator->{$fakeType};
            }
            if ($fakeType === 'boolean') {
                $value = (int)$value;
            }
        }

        if (!$inLangFile) {
            if ($fieldName === $this->id) {
                if ($fieldName !== 'id') {
                    $this->addAttribute($element, 'id', $value);
                }
                $idValue = $value;
            }
        }
        if (!array_key_exists('hidden', $fieldDescription) || $fieldDescription['hidden'] == false) {
            $this->addAttribute($element, $fieldName, $value);
        }

        return $idValue;
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
            if (empty($dependencies) || !in_array($relationName, $dependencies) || $relationName == $entityName) {
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
     * Add the attribute corresponding to the given relation
     * If relationValues is set, use those values to fill the attribute, otherwise choose a random attribute from
     * the relation
     *
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
     * Add an attribute in the main xml file
     *
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
     * Add an attribute in each xml lang file
     *
     * @param \SimpleXMLElement[] $elements
     * @param string              $fieldName
     * @param string              $value
     */
    private function addLangAttribute($elements, $fieldName, $value)
    {
        foreach($elements as $key => $element) {
            $element->addAttribute($fieldName, $value);
        }
    }

    /**
     * Add manually set entity data from the entities section of the yaml file
     *
     * @param \SimpleXMLElement $element
     * @param \SimpleXMLElement[] $langElements
     */
    private function addCustomEntityData(\SimpleXMLElement $element, $langElements)
    {
        if (array_key_exists('entities', $this->entityModel)) {
            foreach ($this->entityModel['entities'] as $id => $fields) {
                if (!empty($fields) && !array_key_exists('hidden', $fields)) {
                    $child = $element->addChild($this->entityElementName);
                } else {
                    $child = new \SimpleXMLElement('<'.$this->entityElementName.'></'.$this->entityElementName.'>');
                }

                $this->addAttribute($child, 'id', $id);
                if (array_key_exists('fields', $fields)) {
                    foreach ($fields['fields'] as $fieldName => $value) {
                        if ($fieldName !== 'id') {
                            $this->addAttribute($child, $fieldName, $value);
                        }
                    }
                }
                $this->relations[$this->entityElementName][] = $child;

                if ($this->hasLang) {
                    $this->addCustomLangEntityData($id, $fields, $langElements);
                }
            }
        }
    }

    /**
     * Add manually set entity data from the fields_lang of the entities section of the yaml file
     *
     * @param string $id
     * @param array $fields
     * @param \SimpleXMLElement[] $langElements
     */
    private function addCustomLangEntityData($id, $fields, $langElements)
    {
        if (empty($fields) || array_key_exists('hidden', $fields)) {
            return;
        }

        foreach($langElements as $key => $langElement) {
            $langElements[$key] = $langElement->addChild($this->entityElementName);
        }

        $this->addLangAttribute($langElements, 'id', $id);

        foreach ($fields['fields_lang'] as $fieldName => $value) {
            if ($fieldName !== 'id') {
                $this->addLangAttribute($langElements, $fieldName, $value);
            }
        }
    }

    /**
     *  Fill the fields section of the xml file
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

        foreach ($this->entityModel['fields']['columns'] as $fieldName => $fieldDescription) {
            if ($fieldName === 'exclusive_fields') {
                foreach ($fieldDescription as $subkey => $subvalue) {
                    $this->addField($child, $subkey, $subvalue);
                }
            } else {
                if (!array_key_exists('hidden', $fieldDescription) || $fieldDescription['hidden'] == false) {
                    $this->addField($child, $fieldName, $fieldDescription);
                }
            }
        }
    }

    /**
     * Add a field element in the fields section of the xml file
     *
     * @param \SimpleXMLElement $element
     * @param string            $fieldName
     * @param array             $fieldDescription
     */
    private function addField(\SimpleXMLElement $element, $fieldName, $fieldDescription)
    {
        $field = $element->addChild('field');
        $field->addAttribute('name', $fieldName);
        if (array_key_exists('relation', $fieldDescription)) {
            $field->addAttribute('relation', Inflector::tableize($fieldDescription['relation']));
        }
    }
}
