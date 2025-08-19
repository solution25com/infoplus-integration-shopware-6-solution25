<?php declare(strict_types=1);

namespace InfoPlusCommerce;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class InfoPlusCommerce extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->createCustomFields($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->deleteCustomFields($uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->createCustomFields($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->createCustomFields($updateContext->getContext());
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    private function createCustomFields(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        /** @var EntityRepository $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');
        /** @var EntityRepository $relationRepository */
        $relationRepository = $this->container->get('custom_field_set_relation.repository');

        $setName = 'product_infoplus_data';

        // Ensure the Custom Field Set exists (search by name, reuse ID if found)
        $setCriteria = (new Criteria())->addFilter(new EqualsFilter('name', $setName));
        $existingSet = $customFieldSetRepository->search($setCriteria, $context)->first();
        $setId = $existingSet ? $existingSet->getId() : Uuid::randomHex();

        $setPayload = [
            'id' => $setId,
            'name' => $setName,
            'config' => [
                'label' => [
                    'en-GB' => 'InfoPlus Product Data',
                    'de-DE' => 'InfoPlus Produktdaten',
                ],
            ],
        ];
        if (!$existingSet) {
            $setPayload['relations'] = [
                [
                    'entityName' => 'product',
                ],
            ];
        }

        $customFieldSetRepository->upsert([$setPayload], $context);

        // If set already existed, ensure the relation to 'product' exists
        if ($existingSet) {
            $relCriteria = (new Criteria())
                ->addFilter(new EqualsFilter('customFieldSetId', $setId))
                ->addFilter(new EqualsFilter('entityName', 'product'));
            $existingRel = $relationRepository->search($relCriteria, $context)->first();
            if (!$existingRel) {
                $relationRepository->create([[
                    'id' => Uuid::randomHex(),
                    'customFieldSetId' => $setId,
                    'entityName' => 'product',
                ]], $context);
            }
        }

        // Define custom fields to create/update and always include the set ID and a stable ID
        $fields = [
            'infoplus_major_group_id' => [
                'type' => CustomFieldTypes::INT,
                'config' => [
                    'label' => [
                        'en-GB' => 'InfoPlus Major Group ID',
                        'de-DE' => 'InfoPlus Hauptkategorie ID',
                    ],
                    'type' => 'number',
                    'min' => 0,
                    'placeholder' => [
                        'en-GB' => 'Enter major group ID...',
                        'de-DE' => 'Hauptkategorie ID eingeben...',
                    ],
                ],
            ],
            'infoplus_sub_group_id' => [
                'type' => CustomFieldTypes::INT,
                'config' => [
                    'label' => [
                        'en-GB' => 'InfoPlus Sub Group ID',
                        'de-DE' => 'InfoPlus Unterkategorie ID',
                    ],
                    'type' => 'number',
                    'min' => 0,
                    'placeholder' => [
                        'en-GB' => 'Enter sub group ID...',
                        'de-DE' => 'Unterkategorie ID eingeben...',
                    ],
                ],
            ],
        ];

        $upserts = [];
        foreach ($fields as $name => $def) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $name));
            $existing = $customFieldRepository->search($criteria, $context)->first();

            $upserts[] = array_merge(
                [
                    'id' => $existing ? $existing->getId() : Uuid::randomHex(),
                    'name' => $name,
                    'type' => $def['type'],
                    'customFieldSetId' => $setId,
                ],
                ['config' => $def['config']]
            );
        }

        // Upsert fields individually to respect the unique constraint on name
        $customFieldRepository->upsert($upserts, $context);
    }

    private function deleteCustomFields(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        /** @var EntityRepository $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');

        $setName = 'product_infoplus_data';

        // Find set ID by name
        $setCriteria = (new Criteria())->addFilter(new EqualsFilter('name', $setName));
        $existingSet = $customFieldSetRepository->search($setCriteria, $context)->first();

        if (!$existingSet) {
            return; // Nothing to delete
        }

        $setId = $existingSet->getId();

        // Delete the two custom fields if they exist (by ID)
        foreach (['infoplus_major_group_id', 'infoplus_sub_group_id'] as $name) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $name));
            $existing = $customFieldRepository->search($criteria, $context)->first();
            if ($existing) {
                $customFieldRepository->delete([
                    ['id' => $existing->getId()],
                ], $context);
            }
        }

        // Finally, delete the set itself by ID
        $customFieldSetRepository->delete([
            ['id' => $setId],
        ], $context);
    }
}