import template from './infoplus-edit-category.html.twig';

const { Component, Mixin } = Shopware;

Component.register('infoplus-edit-category', {
    template,
    inject: ['notification'],
    mixins: [
        Mixin.getByName('notification'),
    ],
    data() {
        return {
            category: null,
            categoryName: '',
            isLoading: false,
        };
    },
    computed: {
        isSubCategory() {
            return this.$route.params.isSubCategory || false;
        },
    },
    created() {
        this.loadCategory();
    },
    methods: {
        async loadCategory() {
            this.isLoading = true;
            try {
                const response = await fetch(`/api/_action/infoplus/getCategory/${this.$route.params.id}`, {
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch category');
                }

                const data = await response.json();
                this.category = data;
                this.categoryName = data.name || '';
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.failedToFetchCategory', 0, { message: error.message }),
                });
                this.$router.push({ name: 'infoplus-categories' });
            } finally {
                this.isLoading = false;
            }
        },
        async saveCategory() {
            if (!this.categoryName.trim()) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.categoryNameRequired'),
                });
                return;
            }

            this.isLoading = true;
            try {
                const response = await fetch(`/api/_action/infoplus/updateCategory/${this.$route.params.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                    body: JSON.stringify({
                        name: this.categoryName,
                        isSubCategory: this.isSubCategory,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to update category');
                }
                const data = await response.json();
                if(data.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('infoplus.notifications.successTitle'),
                        message: data.message || this.$tc('infoplus.notifications.success.categoryUpdated'),
                    });
                    this.$router.back();
                }
                else {
                    this.createNotificationError({
                        title: this.$tc('infoplus.notifications.errorTitle'),
                        message: data.message || this.$tc('infoplus.notifications.error.failedToUpdateCategory', 0, { message: '' }),
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.failedToUpdateCategory', 0, { message: error.message }),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});