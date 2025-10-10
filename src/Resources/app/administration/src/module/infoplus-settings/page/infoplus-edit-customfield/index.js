import template from './infoplus-edit-customfield.html.twig';

const { Component, Mixin } = Shopware;

Component.register('infoplus-edit-customfield', {
    template,
    mixins: [Mixin.getByName('notification')],
    data() {
        return {
            customField: {
                technicalName: '',
                label: '',
                type: 'text',
                isRequired: false,
                optionsString: '',
                position: 0,
                showInStorefront: false,
                active: true,
            },
            isLoading: false,
            typeOptions: [
                { value: 'text', label: 'Text' },
                { value: 'textarea', label: 'Textarea' },
                { value: 'number', label: 'Number' },
                { value: 'money', label: 'Money' },
                { value: 'boolean', label: 'Boolean' },
                { value: 'select', label: 'Select' }
            ]
        };
    },
    created() {
        this.loadCustomField();
    },
    methods: {
        async loadCustomField() {
            this.isLoading = true;
            try {
                const response = await fetch(`/api/_action/infoplus/customfields/${this.$route.params.id}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    }
                });
                if (!response.ok) throw new Error('Failed to load custom field');
                const field = await response.json();
                this.customField = {
                    technicalName: field.technicalName || '',
                    label: field.label || '',
                    type: field.type || 'text',
                    isRequired: field.isRequired || false,
                    optionsString: Array.isArray(field.options) ? field.options.join(', ') : '',
                    position: typeof field.position === 'number' ? field.position : 0,
                    showInStorefront: field.showInStorefront || false,
                    active: typeof field.active === 'boolean' ? field.active : true,
                };
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.errorTitle'),
                    message: e.message
                });
            } finally {
                this.isLoading = false;
            }
        },
        async saveCustomField() {
            this.isLoading = true;
            try {
                if (!this.customField.technicalName.trim()) {
                    this.createNotificationError({
                        title: this.$tc('infoplus.common.errorTitle'),
                        message: 'Technical name is required'
                    });
                    return;
                }
                if (!this.customField.label.trim()) {
                    this.createNotificationError({
                        title: this.$tc('infoplus.common.errorTitle'),
                        message: 'Label is required'
                    });
                    return;
                }
                if (this.customField.type === 'select') {
                    this.customField.options = this.customField.optionsString
                        .split(',')
                        .map(o => o.trim())
                        .filter(Boolean);
                } else {
                    this.customField.options = [];
                }
                const payload = { ...this.customField };
                delete payload.optionsString;
                const response = await fetch(`/api/_action/infoplus/customfields/${this.$route.params.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) throw new Error('Failed to update custom field');
                this.createNotificationSuccess({
                    title: this.$tc('infoplus.customfield.save'),
                    message: this.$tc('infoplus.customfield.saved')
                });
                this.$router.push({ name: 'infoplus.settings.index.customfields' });
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.errorTitle'),
                    message: e.message
                });
            } finally {
                this.isLoading = false;
            }
        }
    }
});
