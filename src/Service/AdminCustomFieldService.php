<?php

namespace InfoPlusCommerce\Service;

use InfoPlusCommerce\Core\Content\InfoplusFieldDefinition\InfoplusFieldDefinitionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class AdminCustomFieldService
{
    private EntityRepository $customFieldRepository;

    public function __construct(EntityRepository $customFieldRepository)
    {
        $this->customFieldRepository = $customFieldRepository;
    }

    public function list(bool $all, Context $context): array
    {
        $criteria = new Criteria();
        if (!$all) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }
        $criteria->addSorting(new FieldSorting('position', 'ASC'));
        $result = $this->customFieldRepository->search($criteria, $context);

        $data = [];
        foreach ($result->getEntities() as $entity) {
            /** @var InfoplusFieldDefinitionEntity $entity */
            $data[] = [
                'id' => $entity->getId(),
                'technicalName' => $entity->getTechnicalName(),
                'label' => $entity->getLabel(),
                'type' => $entity->getType(),
                'isRequired' => $entity->getIsRequired(),
                'options' => $entity->getOptions(),
                'position' => $entity->getPosition(),
                'active' => $entity->isActive(),
                'showInStorefront' => $entity->getShowInStorefront(),
            ];
        }

        return $data;
    }

    public function get(string $id, Context $context): ?array
    {
        $criteria = new Criteria([$id]);
        $result = $this->customFieldRepository->search($criteria, $context);
        /** @var InfoplusFieldDefinitionEntity|null $entity */
        $entity = $result->get($id);

        if (!$entity) {
            return null;
        }

        return [
            'id' => $entity->getId(),
            'technicalName' => $entity->getTechnicalName(),
            'label' => $entity->getLabel(),
            'type' => $entity->getType(),
            'isRequired' => $entity->getIsRequired(),
            'options' => $entity->getOptions(),
            'position' => $entity->getPosition(),
            'active' => $entity->isActive(),
            'showInStorefront' => $entity->getShowInStorefront(),
        ];
    }

    public function create(array $data, Context $context): void
    {
        $allowedTypes = ['text', 'textarea', 'number', 'money', 'boolean', 'select'];
        if (!isset($data['type']) || !in_array($data['type'], $allowedTypes, true)) {
            throw new \InvalidArgumentException('Invalid type. Allowed values: ' . implode(', ', $allowedTypes));
        }

        $data['id'] = $data['id'] ?? Uuid::randomHex();
        $data['createdAt'] = (new \DateTime())->format('Y-m-d H:i:s');

        if (isset($data['options'])) {
            $data['options'] = $this->normalizeOptions($data['options']);
        }

        $data['showInStorefront'] = $data['showInStorefront'] ?? false;
        $this->customFieldRepository->create([$data], $context);
    }

    public function update(string $id, array $data, Context $context): void
    {
        $allowedTypes = ['text', 'textarea', 'number', 'money', 'boolean', 'select'];
        if (!isset($data['type']) || !in_array($data['type'], $allowedTypes, true)) {
            throw new \InvalidArgumentException('Invalid type. Allowed values: ' . implode(', ', $allowedTypes));
        }

        if (isset($data['options'])) {
            $data['options'] = $this->normalizeOptions($data['options']);
        }

        $data['id'] = $id;
        $data['showInStorefront'] = $data['showInStorefront'] ?? false;
        $this->customFieldRepository->update([$data], $context);
    }

    public function delete(string $id, Context $context): void
    {
        $this->customFieldRepository->delete([[ 'id' => $id ]], $context);
    }

    private function normalizeOptions($options): array
    {
        if (is_string($options)) {
            $normalized = array_map('trim', explode("\n", $options));
        } elseif (is_array($options)) {
            $normalized = array_map(static function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $options);
        } else {
            return [];
        }

        return array_values(array_filter($normalized, static function ($v) {
            return $v !== null && $v !== '';
        }));
    }
}
