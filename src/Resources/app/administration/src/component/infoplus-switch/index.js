import template from './infoplus-switch.html.twig';
const { Component, Mixin } = Shopware;

Component.register('infoplus-switch', {
    template,
    inject: [
        'repositoryFactory',
        'systemConfigApiService',
    ],
    mixins: [
        Mixin.getByName('notification'),
    ],
    props: {
        label: String,
        name: String,
    },
    data() {
        return {
            isChecked: false,
            inProgress: false,
        };
    },
    created() {
        this.loadConfig();
    },
    methods: {
        async loadConfig() {
            try {
                const response = await this.systemConfigApiService.getValues('InfoPlusCommerce.config');
                const key = response[this.name];
                this.isChecked = !!key;
            } catch (error) {
                console.error('Config load error:', error);
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.configLoadFailed'),
                });
            }
        },
        async saveConfig(key, value) {
            try {
                await this.systemConfigApiService.saveValues({ [key]: value });
                this.createNotificationSuccess({
                    title: this.$tc('infoplus.notifications.successTitle'),
                    message: this.$tc('infoplus.notifications.success.configSaved'),
                });
            } catch (error) {
                console.error('Config save error:', error);
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.configSaveFailed'),
                });
            }
        },
        async handleClick() {
            if (this.inProgress) return;
            this.inProgress = true;

            const currentElement = document.getElementById(this.name);
            this.isChecked = currentElement.checked;

            const syncProduct = document.getElementById('InfoPlusCommerce.config.syncProducts');
            const syncCategory = document.getElementById('InfoPlusCommerce.config.syncCategories');
            const syncOrder = document.getElementById('InfoPlusCommerce.config.syncOrders');
            const syncCustomer = document.getElementById('InfoPlusCommerce.config.syncCustomers');
            const syncInventory = document.getElementById('InfoPlusCommerce.config.syncInventory');

            if (!syncProduct || !syncCategory || !syncOrder || !syncCustomer || !syncInventory) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.configLoadFailed'),
                });
                this.inProgress = false;
                return;
            }

            const configUpdates = [];

            if (syncOrder.checked && (!syncProduct.checked || !syncCategory.checked || !syncCustomer.checked)) {
                this.createNotificationInfo({
                    title: this.$tc('infoplus.sync.syncRequirementNotice'),
                    message: this.$tc('infoplus.notifications.info.syncRequirementOrders'),
                });
                currentElement.checked = !currentElement.checked;
                this.isChecked = !this.isChecked;
            } else if (syncInventory.checked && !syncProduct.checked) {
                this.createNotificationInfo({
                    title: this.$tc('infoplus.sync.syncRequirementNotice'),
                    message: this.$tc('infoplus.notifications.info.syncRequirementInventory'),
                });
                currentElement.checked = !currentElement.checked;
                this.isChecked = !this.isChecked;
            } else if (syncProduct.checked && !syncCategory.checked) {
                this.createNotificationInfo({
                    title: this.$tc('infoplus.sync.syncRequirementNotice'),
                    message: this.$tc('infoplus.notifications.info.syncRequirementProducts'),
                });
                currentElement.checked = !currentElement.checked;
                this.isChecked = !this.isChecked;
            } else {
                configUpdates.push(this.saveConfig(this.name, this.isChecked));
            }

            await Promise.all(configUpdates).finally(() => {
                this.inProgress = false;
                this.$emit('input', this.isChecked);
            });
        },
    },
});