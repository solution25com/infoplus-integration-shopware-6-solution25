<?php

namespace InfoPlusCommerce\Core\Content\InfoplusFieldDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class InfoplusFieldDefinitionEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;
    protected string $label;
    protected string $type;
    protected bool $isRequired;
    protected $options;
    protected int $position = 0;
    protected bool $active = true;
    protected bool $showInStorefront = false;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }
    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getIsRequired(): bool
    {
        return $this->isRequired;
    }
    public function setIsRequired(bool $isRequired): void
    {
        $this->isRequired = $isRequired;
    }
    public function getOptions()
    {
        return $this->options;
    }
    public function setOptions($options): void
    {
        $this->options = $options;
    }
    public function getPosition(): int
    {
        return $this->position;
    }
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
    public function isActive(): bool
    {
        return $this->active;
    }
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getShowInStorefront(): bool
    {
        return $this->showInStorefront;
    }
    public function setShowInStorefront(bool $showInStorefront): void
    {
        $this->showInStorefront = $showInStorefront;
    }
}
