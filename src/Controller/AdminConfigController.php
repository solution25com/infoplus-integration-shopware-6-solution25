<?php

namespace InfoPlusCommerce\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use InfoPlusCommerce\Service\ConfigService;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
class AdminConfigController extends AbstractController
{
    public function __construct(private readonly ConfigService $configService, private readonly TranslatorInterface $translator)
    {
    }

    #[Route(path: '/api/_action/infoplus/config', name: 'api.infoplus.config.get', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        return new JsonResponse($this->configService->getAll());
    }

    #[Route(path: '/api/_action/infoplus/config', name: 'api.infoplus.config.save', methods: ['POST'])]
    public function saveConfig(Request $request): JsonResponse
    {
        try {
            $data = $request->request->all();

            if (isset($data['order']) && $data['order'] === 'true') {
                if (!isset($data['customer']) || $data['customer'] !== 'true') {
                    throw new InvalidArgumentException($this->translator->trans('infoplus.api.validation.orderRequiresCustomer'));
                }
                if (!isset($data['product']) || $data['product'] !== 'true') {
                    throw new InvalidArgumentException($this->translator->trans('infoplus.api.validation.orderRequiresProduct'));
                }
                if (!isset($data['category']) || $data['category'] !== 'true') {
                    throw new InvalidArgumentException($this->translator->trans('infoplus.api.validation.orderRequiresCategory'));
                }
            }

            if (isset($data['inventory']) && $data['inventory'] === 'true') {
                if (!isset($data['product']) || $data['product'] !== 'true') {
                    throw new InvalidArgumentException($this->translator->trans('infoplus.api.validation.inventoryRequiresProduct'));
                }
            }

            foreach ($data as $key => $value) {
                $this->configService->set($key, $value);
            }
            return new JsonResponse(['status' => $this->translator->trans('infoplus.api.status.configurationSaved')]);
        } catch (InvalidArgumentException $e) {
            throw new HttpException(400, $this->translator->trans('infoplus.api.errors.configurationValidationFailed') . ' ' . $this->translator->trans($e->getMessage()));
        } catch (\Exception $e) {
            throw new HttpException(500, $this->translator->trans('infoplus.api.errors.configurationSaveFailed') . ' ' . $e->getMessage());
        }
    }
}