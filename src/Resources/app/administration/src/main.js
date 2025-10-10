import './component/sync-button';
import './component/infoplus-switch';
import './component/infoplus-categories';
import './component/log-viewer';
import './component/infoplus-sync-settings';
import './component/infoplus-customfields';
import './module/infoplus-settings/page/infoplus-new-category';
import './module/infoplus-settings/page/infoplus-edit-category';
import './module/infoplus-settings/page/infoplus-log-detail';
import './module/infoplus-settings/page/infoplus-new-customfield';
import './module/infoplus-settings/page/infoplus-edit-customfield';
import './module/sw-order/view/sw-order-detail-general';
import './module/sw-product/view/sw-product-detail-base';
import './module/sw-customer/view/sw-customer-detail-base';
import './style/shared.scss';
import './module/infoplus-settings';
import InfoplusLogApiService from './service/infoplus-log.api.service'
import './module/sw-order/view/sw-order-detail-customfields';
import './module/sw-order/view/sw-order-create-customfields';
import './module/sw-settings-shipping/page/sw-settings-shipping-detail';
import './module/sw-order/page/sw-order-detail';
import './module/sw-order/page/sw-order-create';
Shopware.Application.addServiceProvider('infoplusLogApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new InfoplusLogApiService(initContainer.httpClient, container.loginService);
});


Shopware.Module.register('sw-order-detail-customfields', {
    routeMiddleware(next, currentRoute) {
        const customRouteName = 'sw-order-detail-customfields';

        if (
            currentRoute.name === 'sw.order.detail'
            && currentRoute.children.every((currentRoute) => currentRoute.name !== customRouteName)
        ) {
            currentRoute.children.push({
                name: customRouteName,
                path: '/sw/order/detail/:id/customfields',
                component: 'sw-order-detail-customfields',
                meta: {
                    parentPath: 'sw.order.index'
                }
            });
        }
        next(currentRoute);
    }
});

Shopware.Module.register('sw-order-create-customfields', {
    routeMiddleware(next, currentRoute) {
        const customRouteName = 'sw-order-create-customfields';

        if (
            currentRoute.name === 'sw.order.create'
            && currentRoute.children.every((currentRoute) => currentRoute.name !== customRouteName)
        ) {
            currentRoute.children.push({
                name: customRouteName,
                path: '/sw/order/create/customfields',
                component: 'sw-order-create-customfields',
                meta: {
                    parentPath: 'sw.order.create'
                }
            });
        }
        next(currentRoute);
    }
});
