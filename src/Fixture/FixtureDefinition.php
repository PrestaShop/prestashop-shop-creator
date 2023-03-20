<?php

namespace ShopGenerator\Fixture;

class FixtureDefinition
{
    private string $model;
    private string $fixtureClass;
    private array $columns;
    private array $localizedColumns;
    private array $relations;
    private ?int $imgWidth;
    private ?int $imgHeight;
    private ?string $class;
    private ?string $sql;
    private ?string $primary;
    private bool $hasLang = true;
    private ?string $id;
    private ?string $imageDirectory;
    private ?string $imageCategory;

    public function __construct(
        string $model,
        string $classIdentifier,
        array $columns,
        array $localizedColumns,
        array $relations,
        ?string $id,
        ?string $class,
        ?string $sql,
        ?string $primary,
        ?string $imageDirectory = null,
        ?string $imageCategory = null,
        int $imgWidth = 200,
        int $imgHeight = 200,
    ) {
        $this->fixtureClass = $classIdentifier;
        $this->columns = $columns;
        $this->localizedColumns = $localizedColumns;
        if ([] === $this->localizedColumns) {
            $this->hasLang = false;
        }

        $this->relations = $relations;
        $this->imgWidth = $imgWidth;
        $this->imgHeight = $imgHeight;
        $this->id = $id;
        $this->sql = $sql;
        $this->primary = $primary;
        $this->class = $class;
        $this->imageDirectory = $imageDirectory;
        $this->imageCategory = $imageCategory;
        $this->model = $model;
    }

    /**
     * @return string
     */
    public function getFixtureClass(): string
    {
        return $this->fixtureClass;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
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
    public function getLocalizedColumns(): array
    {
        return $this->localizedColumns;
    }

    /**
     * @return int
     */
    public function getImgWidth(): int
    {
        return $this->imgWidth;
    }

    /**
     * @return int
     */
    public function getImgHeight(): int
    {
        return $this->imgHeight;
    }

    /**
     * @return bool
     */
    public function hasLang(): bool
    {
        return $this->hasLang;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }

    /**
     * @return string|null
     */
    public function getPrimary(): ?string
    {
        return $this->primary;
    }

    /**
     * @return string|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @return string|null
     */
    public function getImageDirectory(): ?string
    {
        return $this->imageDirectory;
    }

    /**
     * @return string|null
     */
    public function getImageCategory(): ?string
    {
        return $this->imageCategory;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
