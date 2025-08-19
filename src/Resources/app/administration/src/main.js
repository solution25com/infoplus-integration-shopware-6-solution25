import './component/sync-button';
import './component/infoplus-switch';
import './component/infoplus-categories';
import './component/log-viewer';
import './component/infoplus-sync-settings';
import './module/infoplus-settings/page/infoplus-new-category';
import './module/infoplus-settings/page/infoplus-edit-category';
import './module/infoplus-settings/page/infoplus-log-detail';
import './module/sw-order/view/sw-order-detail-general';
import './module/sw-product/view/sw-product-detail-base';
import './module/sw-customer/view/sw-customer-detail-base';
import './style/shared.scss';
import './module/infoplus-settings';
import InfoplusLogApiService from './service/infoplus-log.api.service'
Shopware.Application.addServiceProvider('infoplusLogApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new InfoplusLogApiService(initContainer.httpClient, container.loginService);
});