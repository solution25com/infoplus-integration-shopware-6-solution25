import template from './sw-settings-shipping-detail.html.twig';

Shopware.Component.override('sw-settings-shipping-detail', {
    template,

    data() {
        return {
            carriersLoading: false,
            carrierOptions: [],
            selectedInfoplusCarrierId: null,
        };
    },

    created() {
        this.ensureCustomFields();
        this.initSelectedFromEntity();
        this.loadCarriers();
    },

    watch: {
        selectedInfoplusCarrierId(val) {
            if (!this.shippingMethod) return;
            if (!this.shippingMethod.customFields) {
                this.$set(this.shippingMethod, 'customFields', {});
            }
            if (val === null || val === undefined || val === '') {
                this.$delete(this.shippingMethod.customFields, 'infoplus_carrier_id');
            } else {
                this.$set(this.shippingMethod.customFields, 'infoplus_carrier_id', val);
            }
        },
        shippingMethod: {
            deep: true,
            handler() {
                this.initSelectedFromEntity();
            }
        }
    },

    methods: {
        ensureCustomFields() {
            if (!this.shippingMethod) {
                return;
            }
            if (!this.shippingMethod.customFields) {
                this.$set(this.shippingMethod, 'customFields', {});
            }
        },

        initSelectedFromEntity() {
            if (!this.shippingMethod) return;
            const stored = this.shippingMethod.customFields ? this.shippingMethod.customFields['infoplus_carrier_id'] : null;
            this.selectedInfoplusCarrierId = stored ?? null;
            if (stored !== null && stored !== undefined && this.carrierOptions.length > 0) {
                const exists = this.carrierOptions.some(o => String(o.value) === String(stored));
                if (!exists) {
                    this.carrierOptions.unshift({ value: stored, label: `${this.$tc('infoplus.shipping.unknownCarrierPrefix')} ${stored}` });
                }
            }
        },

        async loadCarriers() {
            this.carriersLoading = true;
            try {
                const response = await fetch('/api/_action/infoplus/carriers', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    }
                });
                if (!response.ok) throw new Error(`Failed to load carriers (${response.status})`);
                const carriers = await response.json();
                this.carrierOptions = Array.isArray(carriers) ? carriers.map(c => ({ value: c.carrier, label: c.label })) : [];

                const stored = this.shippingMethod?.customFields?.infoplus_carrier_id;
                if (stored !== null && stored !== undefined) {
                    const exists = this.carrierOptions.some(o => String(o.value) === String(stored));
                    if (!exists) {
                        this.carrierOptions.unshift({ value: stored, label: `${this.$tc('infoplus.shipping.unknownCarrierPrefix')} ${stored}` });
                    }
                }
            } catch (e) {
                console.warn('Failed to load InfoPlus carriers', e);
            } finally {
                this.carriersLoading = false;
            }
        }
    }
});
