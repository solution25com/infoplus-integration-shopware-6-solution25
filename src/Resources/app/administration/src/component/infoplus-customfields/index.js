import template from './infoplus-customfields.html.twig';

const { Component, Mixin } = Shopware;

Component.register('infoplus-customfields', {
    template,
    mixins: [Mixin.getByName('notification')],
    data() {
        return {
            isLoading: false,
            customFields: [],
        };
    },
    created() {
        this.loadCustomFields();
    },
    methods: {
        async loadCustomFields() {
            this.isLoading = true;
            try {
                const response = await fetch('/api/_action/infoplus/customfields/all',{
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                });
                if (!response.ok) throw new Error('Failed to fetch custom fields');
                this.customFields = await response.json();
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.errorTitle'),
                    message: e.message
                });
            } finally {
                this.isLoading = false;
            }
        },
        addNewCustomField() {
            this.$router.push({ name: 'infoplus.settings.addCustomField' });
        },
        editCustomField(item) {
            this.$router.push({ name: 'infoplus.settings.editCustomField', params: { id: item.id } });
        },
        async deleteCustomField(item) {
            try {
                const response = await fetch(`/api/_action/infoplus/customfields/${item.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                        }
                    }
                );
                if (!response.ok) throw new Error('Failed to delete custom field');
                this.createNotificationSuccess({
                    title: this.$tc('infoplus.customfield.delete'),
                    message: this.$tc('infoplus.customfield.deleted')
                });
                this.loadCustomFields();
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.errorTitle'),
                    message: e.message
                });
            }
        }
    }
});
