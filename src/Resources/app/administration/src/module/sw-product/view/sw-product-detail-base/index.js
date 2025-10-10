const { Criteria } = Shopware.Data;
import template from './sw-product-detail-base.html.twig';

Shopware.Component.override('sw-product-detail-base', {
    template,
    inject: ['repositoryFactory'],
    mixins: ['notification'],

    data() {
        return {
            isSynced: false,
            syncedDate: null,
            message: this.$tc('infoplus.sync.neverSynchronized'),
            isInProgress: false,
            selectedCategory: null,
            selectedSubCategory: null,
            selectedCategoryInternalId: null,
            selectedSubCategoryInternalId: null,
            infoplusCustomFields: [],
            expandedFields: {},
            cachedFieldValues: {},
            fieldsVisibilityInitialized: false,
            initialInfoplusKeys: new Set(),
        };
    },

    async created() {
        await this.loadProduct();
        await this.loadInfoplusCustomFields();
        this.tryInitFieldVisibility();
        this.loadInfoplusInformation();
    },

    computed: {
        categoryCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('isSubCategory', 0));
            criteria.setLimit(50);
            return criteria;
        },

        subCategoryCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('isSubCategory', 1));
            criteria.setLimit(50);
            return criteria;
        },
    },

    methods: {
        resolveMessage(value) {
            if (typeof value === 'string' && value.startsWith('infoplus.')) {
                return this.$tc(value);
            }
            return value;
        },
        async loadProduct() {
            const productId = this.$route.params.id || this.product?.id;
            if (!productId) {
                console.error('Product ID not found');
                return;
            }
            const productRepository = this.repositoryFactory.create('product');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('id', productId));
            try {
                this.product = await productRepository.get(productId, Shopware.Context.api, criteria);
                this.product.customFields = this.product.customFields || {};
                const keys = Object.keys(this.product.customFields || {}).filter(k => k.startsWith('infoplus_'));
                this.initialInfoplusKeys = new Set(keys);
                await this.loadCustomFields();
            } catch (error) {
                console.error('Load Product Error:', error);
                this.createNotificationError({
                    title: this.$tc('infoplus.common.syncErrorTitle'),
                    message: `${this.$tc('infoplus.product.errors.failedToLoad')} ${error.message}`,
                });
            }
        },

        async loadInfoplusInformation() {
            const productId = this.$route.params.id || this.product?.id;
            if (!productId) {
                console.error('Product ID not found for Infoplus information');
                return;
            }
            const repository = this.repositoryFactory.create('infoplus_id_mapping');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('entityType', 'item'));
            criteria.addFilter(Criteria.equals('shopwareId', productId));
            try {
                const result = await repository.search(criteria, Shopware.Context.api);
                if (result && result.length > 0) {
                    const infoplusData = result[0];
                    this.isSynced = true;
                    this.syncedDate = new Date(infoplusData.updatedAt || infoplusData.createdAt);
                    this.message = `${this.$tc('infoplus.product.syncedAtPrefix')} ${this.syncedDate.toLocaleDateString()} ${this.syncedDate.toLocaleTimeString()}`;
                } else {
                    this.isSynced = false;
                    this.syncedDate = null;
                    this.message = this.$tc('infoplus.sync.neverSynchronized');
                }
            } catch (error) {
                console.error('Infoplus Information Error:', error);
                this.createNotificationError({
                    title: this.$tc('infoplus.common.syncErrorTitle'),
                    message: `${this.$tc('infoplus.product.errors.failedToGetData')} ${error.message}`,
                });
            }
        },

        async loadCustomFields() {
            if (!this.product?.customFields) {
                this.selectedCategory = null;
                this.selectedSubCategory = null;
                this.selectedCategoryInternalId = null;
                this.selectedSubCategoryInternalId = null;
                console.warn('No product_infoplus_data custom fields found');
                return;
            }

            const categoryRepository = this.repositoryFactory.create('infoplus_category');
            const majorGroupId = this.product.customFields.infoplus_major_group_id;
            const subGroupId = this.product.customFields.infoplus_sub_group_id;

            if (majorGroupId) {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('internalId', majorGroupId));
                try {
                    const result = await categoryRepository.search(criteria, Shopware.Context.api);
                    if (result && result.length > 0) {
                        this.selectedCategory = result[0].id;
                        this.selectedCategoryInternalId = result[0].internalId;
                    } else {
                        this.selectedCategory = null;
                        this.selectedCategoryInternalId = null;
                    }
                } catch (error) {
                    console.error('Load Category Error:', error);
                }
            } else {
                this.selectedCategory = null;
                this.selectedCategoryInternalId = null;
            }

            if (subGroupId) {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('internalId', subGroupId));
                try {
                    const result = await categoryRepository.search(criteria, Shopware.Context.api);
                    if (result && result.length > 0) {
                        this.selectedSubCategory = result[0].id;
                        this.selectedSubCategoryInternalId = result[0].internalId;
                    } else {
                        this.selectedSubCategory = null;
                        this.selectedSubCategoryInternalId = null;
                    }
                } catch (error) {
                    console.error('Load SubCategory Error:', error);
                }
            } else {
                this.selectedSubCategory = null;
                this.selectedSubCategoryInternalId = null;
            }
        },

        async loadInfoplusCustomFields() {
            try {
                const response = await fetch('/api/_action/infoplus/customfields', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    }
                });
                if (!response.ok) throw new Error('Failed to load custom fields');
                const apiFields = await response.json();
                this.infoplusCustomFields = apiFields.map(field => {
                    let options = field.options || [];

                    if (field.type === 'select') {
                        if (typeof options === 'string') {
                            try {
                                const parsed = JSON.parse(options);
                                options = Array.isArray(parsed) ? parsed : [];
                            } catch {
                                options = options.split(',').map(v => v.trim()).filter(Boolean);
                            }
                        }

                        if (Array.isArray(options) && options.length > 0 && typeof options[0] === 'object') {
                            options = options.map(opt => {
                                const value = opt.value != null ? opt.value : opt.id != null ? opt.id : String(opt);
                                const label = opt.label != null ? opt.label : opt.name != null ? opt.name : String(opt);
                                return { value, label, name: label };
                            });
                        } else if (Array.isArray(options)) {
                            options = options.map(opt => {
                                const v = String(opt);
                                return { value: v, label: v, name: v };
                            });
                        } else {
                            options = [];
                        }

                        const seen = new Set();
                        options = options.filter(opt => {
                            const key = `${opt.value}`;
                            if (seen.has(key)) return false;
                            seen.add(key);
                            return true;
                        });
                    }

                    return {
                        technical_name: field.technicalName,
                        label: field.label,
                        type: field.type,
                        is_required: field.isRequired,
                        options
                    };
                });
            } catch (e) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.errorTitle'),
                    message: e.message
                });
            }
        },

        tryInitFieldVisibility() {
            if (this.fieldsVisibilityInitialized) {
                return;
            }
            if (!this.product || !Array.isArray(this.infoplusCustomFields) || this.infoplusCustomFields.length === 0) {
                return;
            }
            const expanded = {};
            this.infoplusCustomFields.forEach((field) => {
                const key = 'infoplus_' + field.technical_name;
                const value = this.product.customFields ? this.product.customFields[key] : undefined;
                if (value === null && this.product && this.product.customFields) {
                    this.$delete(this.product.customFields, key);
                }
                const effectiveValue = this.product.customFields ? this.product.customFields[key] : undefined;
                const hasMeaningfulValue = (
                    effectiveValue !== undefined && effectiveValue !== null && (
                        typeof effectiveValue === 'boolean' ||
                        (typeof effectiveValue === 'number' && !Number.isNaN(effectiveValue)) ||
                        (typeof effectiveValue === 'string' && effectiveValue.trim().length > 0)
                    )
                );
                expanded[field.technical_name] = hasMeaningfulValue;
            });
            this.expandedFields = expanded;
            this.fieldsVisibilityInitialized = true;
        },

        toggleField(technicalName) {
            const currentlyExpanded = !!this.expandedFields[technicalName];
            this.$set(this.expandedFields, technicalName, !currentlyExpanded);
            const key = 'infoplus_' + technicalName;
            if (currentlyExpanded) {
                const currentValue = this.product?.customFields ? this.product.customFields[key] : undefined;
                if (currentValue !== undefined) {
                    this.$set(this.cachedFieldValues, technicalName, currentValue);
                }
                const hasMeaningful = (
                    currentValue !== undefined && currentValue !== null && (
                        typeof currentValue === 'boolean' ||
                        (typeof currentValue === 'number' && !Number.isNaN(currentValue)) ||
                        (typeof currentValue === 'string' && currentValue.trim().length > 0)
                    )
                );
                const hadPreviously = this.initialInfoplusKeys && this.initialInfoplusKeys.has(key);
                if (hadPreviously || hasMeaningful) {
                    if (this.product && this.product.customFields) {
                        this.$set(this.product.customFields, key, null);
                    }
                } else {
                    if (this.product && this.product.customFields && Object.prototype.hasOwnProperty.call(this.product.customFields, key)) {
                        this.$delete(this.product.customFields, key);
                    }
                }
            } else {
                if (Object.prototype.hasOwnProperty.call(this.cachedFieldValues, technicalName)) {
                    const cached = this.cachedFieldValues[technicalName];
                    if (this.product && this.product.customFields) {
                        this.$set(this.product.customFields, key, cached);
                    }
                } else if (this.product && this.product.customFields && this.product.customFields[key] === null) {
                    this.$delete(this.product.customFields, key);
                }
            }
        },

        async onCategoryChange(id) {
            this.selectedCategory = id;
            if (!this.product) {
                console.error('Product not loaded');
                return;
            }
            if (!this.product.customFields) {
                this.product.customFields = {};
            }
            if (id) {
                try {
                    const repo = this.repositoryFactory.create('infoplus_category');
                    const category = await repo.get(id, Shopware.Context.api);
                    this.selectedCategoryInternalId = category ? category.internalId : null;
                } catch (e) {
                    console.error('Failed to load selected category', e);
                    this.selectedCategoryInternalId = null;
                }
            } else {
                this.selectedCategoryInternalId = null;
            }
            this.product.customFields.infoplus_major_group_id = this.selectedCategoryInternalId;
        },

        async onSubCategoryChange(id) {
            this.selectedSubCategory = id;
            if (!this.product) {
                console.error('Product not loaded');
                return;
            }
            if (!this.product.customFields) {
                this.product.customFields = {};
            }
            if (id) {
                try {
                    const repo = this.repositoryFactory.create('infoplus_category');
                    const sub = await repo.get(id, Shopware.Context.api);
                    this.selectedSubCategoryInternalId = sub ? sub.internalId : null;
                } catch (e) {
                    console.error('Failed to load selected subcategory', e);
                    this.selectedSubCategoryInternalId = null;
                }
            } else {
                this.selectedSubCategoryInternalId = null;
            }
            this.product.customFields.infoplus_sub_group_id = this.selectedSubCategoryInternalId;
        },

        async syncProduct() {
            const productId = this.$route.params.id || this.product?.id;
            if (!productId) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.syncErrorTitle'),
                    message: this.$tc('infoplus.product.errors.missingId'),
                });
                return;
            }
            if (!this.product.customFields?.infoplus_major_group_id ||
                !this.product.customFields?.infoplus_sub_group_id) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.syncErrorTitle'),
                    message: this.$tc('infoplus.product.errors.categoryRequired'),
                });
                return;
            }
            this.message = this.$tc('infoplus.product.syncing');
            this.isInProgress = true;
            try {
                const response = await fetch(`/api/_action/infoplus/sync/product/${productId}`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": `Bearer ${Shopware.Context.api.authToken.access}`
                    }
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (data && data.success) {
                    this.isSynced = true;
                    this.syncedDate = new Date();
                    this.message = `${this.$tc('infoplus.product.syncedAtPrefix')} ${this.syncedDate.toLocaleDateString() + ' ' + this.syncedDate.toLocaleTimeString()}`;
                    this.createNotificationSuccess({
                        title: this.$tc('infoplus.common.syncSuccessTitle'),
                        message: this.message,
                    });
                } else if (data && data.error) {
                    this.createNotificationError({
                        title: this.$tc('infoplus.common.syncErrorTitle'),
                        message: this.resolveMessage(data.error),
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('infoplus.common.errorTitle'),
                        message: this.$tc('infoplus.product.errors.failedToRetrieveInfo'),
                    });
                }
            } catch (error) {
                console.error('Sync Product Error:', error);
                this.createNotificationError({
                    title: this.$tc('infoplus.common.syncErrorTitle'),
                    message: `${this.$tc('infoplus.product.errors.failedToSync')} ${error.message}`,
                });
            } finally {
                this.isInProgress = false;
            }
        }
    }
});
