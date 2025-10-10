<?php

namespace InfoPlusCommerce\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InfoplusLineItemCustomFieldController extends AbstractController
{
    public function __construct(private readonly CartService $cartService) {}

    #[Route(path: '/store-api/infoplus/cart/line-item/custom-fields', name: 'store-api.infoplus.cart.line-item.custom-fields', methods: ['POST'], defaults: ['_routeScope' => ['store-api']])]
    public function upsertCustomFields(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $items = $data['items'] ?? [];
        if (!\is_array($items) || empty($items)) {
            return new JsonResponse(['success' => true, 'updated' => 0]);
        }
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
        $updated = 0;
        foreach ($items as $row) {
            if (!isset($row['id']) || !\is_string($row['id'])) {
                continue;
            }
            $li = $cart->getLineItems()->get($row['id']);
            if (!$li) {
                continue;
            }
            $custom = $row['customFields'] ?? [];
            if (!\is_array($custom) || empty($custom)) {
                continue;
            }
            $existingPayloadCustom = $li->getPayload()['infoplus_customfields'] ?? [];
            if (!\is_array($existingPayloadCustom)) {
                $existingPayloadCustom = [];
            }
            $mergedPayload = array_merge($existingPayloadCustom, $custom);
            $li->setPayloadValue('infoplus_customfields', $mergedPayload);

            $generic = $li->getPayload()['customFields'] ?? [];
            if (!\is_array($generic)) {
                $generic = [];
            }
            $li->setPayloadValue('customFields', array_merge($generic, $custom));
            $updated++;
        }

        $this->cartService->recalculate($cart, $salesChannelContext);

        return new JsonResponse(['success' => true, 'updated' => $updated]);
    }
}
