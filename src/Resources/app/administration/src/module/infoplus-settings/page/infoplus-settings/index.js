import template from './infoplus-settings.html.twig';
import  '../../../../component/sync-button';
import '../../../../component/infoplus-switch';
import '../../../../component/infoplus-categories';
import '../../../../component/log-viewer'
import '../../../../component/infoplus-sync-settings'


const { Component, Mixin } = Shopware;

Component.register('infoplus-settings', {
    template,
    mixins: [Mixin.getByName('notification')],
    data() {
        return {
            isLoading: false,
            activeTab: 'sync-settings',
        };
    }
});