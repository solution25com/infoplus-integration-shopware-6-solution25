const {Criteria} = Shopware.Data;
import template from './sw-customer-detail-base.html.twig';

Shopware.Component.override('sw-customer-detail-base', {
    template,
    inject: ['repositoryFactory'],
    mixins: ['notification'],

    data() {
        return {
            isSynced: false,
            syncedDate: null,
            message: this.$tc('infoplus.sync.neverSynchronized'),
            isInProgress: false,
        };
    },
    created() {
        this.loadIInfoplusInformation();
    },
    methods: {
        resolveMessage(value) {
            if (typeof value === 'string' && value.startsWith('infoplus.')) {
                return this.$tc(value);
            }
            return value;
        },
        loadIInfoplusInformation() {
            const customerId = this.$route.params.id || this.customer?.id;

            const repository = this.repositoryFactory.create('infoplus_id_mapping');
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('entityType', 'customer'));
            criteria.addFilter(Criteria.equals('shopwareId', customerId));
            repository.search(criteria, Shopware.Context.api)
                .then((result) => {
                    if (result && result.length > 0) {
                        const infoplusData = result[0];
                        this.isSynced = true;
                        this.syncedDate = new Date(infoplusData.updatedAt || infoplusData.createdAt);
                        this.message = `${this.$tc('infoplus.customer.syncedAtPrefix')} ${this.syncedDate.toLocaleDateString()} ${this.syncedDate.toLocaleTimeString()}`;
                    } else {
                        this.isSynced = false;
                        this.syncedDate = null;
                        this.message = this.$tc('infoplus.sync.neverSynchronized');
                    }
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$tc('infoplus.common.syncErrorTitle'),
                        message: `${this.$tc('infoplus.customer.errors.failedToGetData')} ${error.message}`,
                    });
                });
        },
        async syncCustomer() {
            const customerId = this.$route.params.id || this.customer?.id;
            if (!customerId) {
                this.createNotificationError({
                    title: this.$tc('infoplus.common.syncErrorTitle'),
                    message: this.$tc('infoplus.customer.errors.missingId'),
                });
                return;
            }
            this.isInProgress = true;
            this.message = this.$tc('infoplus.customer.syncing');
            fetch(`/api/_action/infoplus/sync/customer/${customerId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${Shopware.Context.api.authToken.access}`
                }
            })
                .then((response) => {
                    if (!response.ok) {
                        this.isInProgress = false;
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        this.isSynced = true;
                        this.syncedDate = new Date();
                        this.message = `${this.$tc('infoplus.customer.syncedAtPrefix')} ${this.syncedDate.toLocaleDateString() + ' ' + this.syncedDate.toLocaleTimeString()}`;
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
                            message: this.$tc('infoplus.customer.errors.failedToRetrieveInfo'),
                        });
                    }

                })
                .catch(error => {
                    this.createNotificationError({
                        title: this.$tc('infoplus.common.syncErrorTitle'),
                        message: `${this.$tc('infoplus.customer.errors.failedToSync')} ${error.message}`,
                    });
                })
                .finally(() => {
                    this.isInProgress = false;
                });
        }
    }
});
