<?php

namespace InfoPlusCommerce\Service;

use InfoPlusCommerce\Core\Content\InfoplusFieldDefinition\InfoplusFieldDefinitionCollection;
use InfoPlusCommerce\Core\Content\InfoplusFieldDefinition\InfoplusFieldDefinitionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class AdminCustomFieldService
{
    /**
     * @var EntityRepository<InfoplusFieldDefinitionCollection>
     */
    private EntityRepository $customFieldRepository;

    /**
     * @param EntityRepository<InfoplusFieldDefinitionCollection> $customFieldRepository
     */
    public function __construct(EntityRepository $customFieldRepository)
    {
        $this->customFieldRepository = $customFieldRepository;
    }

    /**
     * @param bool $all
     * @param Context $context
     * @return array<int,array<string,mixed>>
     */
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

    /**
     * @param string $id
     * @param Context $context
     * @return array<string,mixed>|null
     */
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

    /**
     * @param array<string,mixed> $data
     * @param Context $context
     * @return void
     */
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

    /**
     * @param string $id
     * @param array<string,mixed> $data
     * @param Context $context
     * @return void
     */
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

    /**
     * @param string $id
     * @param Context $context
     * @return void
     */
    public function delete(string $id, Context $context): void
    {
        $this->customFieldRepository->delete([[ 'id' => $id ]], $context);
    }

    /**
     * @param string|array<mixed> $options
     * @return array<int,mixed>
     */
    private function normalizeOptions($options): array
    {
        if (is_string($options)) {
            $normalized = array_map('trim', explode("\n", $options));
        } else {
            $arrayOptions = (array)$options;
            $normalized = array_map(static function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $arrayOptions);
        }

        return array_values(array_filter($normalized, static function ($v) {
            return $v !== null && $v !== '';
        }));
    }
}
