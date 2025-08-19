import template from './infoplus-sync-settings.html.twig';
import  '../sync-button';
import '../infoplus-switch';

const { Component, Mixin } = Shopware;

Component.register('infoplus-sync-settings', {
    template,
    mixins: [Mixin.getByName('notification')],
    data() {
        return {
            isLoading: false,
            activeTab: 'sync-settings',
            config: {
                apiKey: '',
                baseDomain: '',
                warehouseId: ''
            }
        };
    },
    created() {
        this.systemConfigApiService = Shopware.Service('systemConfigApiService');
        this.loadConfig();
    },
    methods: {
        loadConfig() {
            this.isLoading = true;
            this.systemConfigApiService.getValues('InfoPlusCommerce').then(values => {
                this.config.apiKey = values['InfoPlusCommerce.config.apiKey'] || '';
                this.config.baseDomain = values['InfoPlusCommerce.config.baseDomain'] || '';
                this.config.warehouseId = values['InfoPlusCommerce.config.warehouseId'] || '';
                this.isLoading = false;
            });
        },
        saveConfig() {
            this.isLoading = true;
            const configValues = {
                'InfoPlusCommerce.config.apiKey': this.config.apiKey,
                'InfoPlusCommerce.config.baseDomain': this.config.baseDomain,
                'InfoPlusCommerce.config.warehouseId': this.config.warehouseId
            };
            this.systemConfigApiService.saveValues(configValues).finally(() => {
                this.isLoading = false;
                this.createNotificationSuccess({
                    title: this.$tc('infoplus.sync.success'),
                    message: this.$tc('infoplus.sync.configurationSaved')
                });
            });
        }
    }
});