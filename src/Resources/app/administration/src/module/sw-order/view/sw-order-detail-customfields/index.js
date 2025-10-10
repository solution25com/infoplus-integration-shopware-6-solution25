import template from './sw-order-detail-customfields.html.twig';
import './sw-order-detail-customfields.css';
const { Criteria } = Shopware.Data;

Shopware.Component.register('sw-order-detail-customfields', {
    template,
    inject: ['repositoryFactory'],
    data() {
        return {
            order: null,
            lineItems: [],
            infoplusCustomFields: []
        };
    },
    metaInfo() {
        return {
            title: 'Custom Fields',
            meta: [{
                name: 'description',
                content: 'Custom Fields'
            }]
        };
    },
    created() {
        this.loadOrder();
        this.loadInfoplusCustomFields();
    },
    computed: {
        mappedLineItems() {
            return this.lineItems.map(item => {
                const customFields = item.payload && item.payload.infoplus_customfields ? item.payload.infoplus_customfields : {};
                const displayCustomFields = Object.entries(customFields).map(([key, value]) => {
                    let technicalName = key.startsWith('infoplus_') ? key.replace('infoplus_', '') : key;
                    let fieldDef = this.infoplusCustomFields.find(f => f.technicalName === technicalName);
                    let label = fieldDef ? fieldDef.label : technicalName;
                    return { label, value };
                });
                return {
                    ...item,
                    displayCustomFields
                };
            });
        }
    },
    methods: {
        loadOrder() {
            const orderId = this.$route.params.id;
            const orderRepository = this.repositoryFactory.create('order');
            const criteria = new Criteria();
            criteria.addAssociation('lineItems');

            criteria.addFilter(Criteria.equals('id', orderId));

            orderRepository.search(criteria, Shopware.Context.api)
                .then((result) => {
                    if (result && result.length > 0) {
                        this.order = result[0];
                        this.lineItems = this.order.lineItems || [];
                    } else {
                        this.order = null;
                        this.lineItems = [];
                    }
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: 'Order fetch error',
                        message: error.message,
                    });
                });
        },
        loadInfoplusCustomFields() {
            const fieldRepository = this.repositoryFactory.create('infoplus_field_definition');
            const criteria = new Criteria();
            fieldRepository.search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.infoplusCustomFields = result || [];
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: 'Custom field fetch error',
                        message: error.message,
                    });
                });
        }
    }
});