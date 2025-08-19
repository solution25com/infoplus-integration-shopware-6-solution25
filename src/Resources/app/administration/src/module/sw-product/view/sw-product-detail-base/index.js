const { Criteria, EntityCollection } = Shopware.Data;
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
        };
    },

    created() {
        this.loadProduct();
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
                await this.loadCustomFields();
            } catch (error) {
                console.error('Load Product Error:', error);
                this.createNotificationError({
                    title: this.$tc('infoplus.common.errorTitle'),
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
                    title: this.$tc('infoplus.syncErrorTitle'),
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
                console.log('No product_infoplus_data custom fields found');
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

        async onCategoryChange(id, item) {
            this.selectedCategory = id;
            this.selectedCategoryInternalId = item ? item.internalId : null;
            if (!this.product) {
                console.error('Product not loaded');
                return;
            }
            if (!this.product.customFields) {
                this.product.customFields = {};
            }
            this.product.customFields.infoplus_major_group_id = this.selectedCategoryInternalId;
        },

        async onSubCategoryChange(id, item) {
            this.selectedSubCategory = id;
            this.selectedSubCategoryInternalId = item ? item.internalId : null;
            if (!this.product) {
                console.error('Product not loaded');
                return;
            }
            if (!this.product.customFields) {
                this.product.customFields = {};
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