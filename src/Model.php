<?php

namespace ShopGenerator;

class Model
{
    /** @var array */
    protected $entityModel;

    /** @var string unique id */
    protected $id = 'id';

    /** @var string primary key */
    protected $primary = '';

    /** @var bool */
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

    /** @var string entity xml element name */
    protected $entityElementName;

    /** @var array mapping of existing relations (entities already created) */
    protected $relations;

    /** @var array list of existing relations for each entities */
    protected $relationList;

    /** @var array list of already generated values for the entity relations */
    protected $relationValues = [];

    /** @var array the info on the parent entities which will be used to generate the current entity */
    protected $parentEntities = [];

    /** @var array list of values associated with each fields for a given entity */
    protected $fieldValues = [];

    /** @var array the list of dependencies between entities */
    protected $dependencies = [];

    /** @var array Array of already generated img to be reused to speed up the generation process */
    protected $generatedImgs = [];

    public function __construct(
        string $entityElementName,
        array $entityModel
    ) {
        $this->entityModel = $entityModel;
        $fields = $this->entityModel['fields'];

        $this->extractParentEntities($entityModel);

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
        if (array_key_exists('fields_lang', $this->entityModel)) {
            $this->hasLang = true;
        }
    }

    private function extractParentEntities($entityModel)
    {
        $parentEntities = [];

        foreach ($entityModel['fields']['columns'] as $fieldDescription) {
            if (array_key_exists('generate_all', $fieldDescription)) {
                $relation = $fieldDescription['relation'];
                $parentEntities[] = $relation;
            }
        }

        $this->parentEntities = $parentEntities;
    }

    /**
     * @return array
     */
    public function getEntityModel(): array
    {
        return $this->entityModel;
    }

    /**
     * @return mixed|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed|string
     */
    public function getPrimary()
    {
        return $this->primary;
    }

    /**
     * @return bool
     */
    public function isHasLang(): bool
    {
        return $this->hasLang;
    }

    /**
     * @return mixed|string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return mixed|string
     */
    public function getImgPath()
    {
        return $this->imgPath;
    }

    /**
     * @return int|mixed
     */
    public function getImgWidth()
    {
        return $this->imgWidth;
    }

    /**
     * @return int|mixed
     */
    public function getImgHeight()
    {
        return $this->imgHeight;
    }

    /**
     * @return mixed|string|null
     */
    public function getImgCategory()
    {
        return $this->imgCategory;
    }

    /**
     * @return mixed|string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @return string
     */
    public function getEntityElementName(): string
    {
        return $this->entityElementName;
    }

    /**
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @return array
     */
    public function getRelationList(): array
    {
        return $this->relationList;
    }

    /**
     * @return array
     */
    public function getRelationValues(): array
    {
        return $this->relationValues;
    }

    /**
     * @return array
     */
    public function getParentEntities(): array
    {
        return $this->parentEntities;
    }

    /**
     * @return array
     */
    public function getFieldValues(): array
    {
        return $this->fieldValues;
    }

    /**
     * @return array
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return array
     */
    public function getGeneratedImgs(): array
    {
        return $this->generatedImgs;
    }

    public function getColumns()
    {
    }
}
