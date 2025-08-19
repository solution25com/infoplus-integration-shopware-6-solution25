import template from './infoplus-new-category.html.twig';

const { Component, Mixin } = Shopware;

Component.register('infoplus-new-category', {
    template,
    inject: ['notification'],
    mixins: [
        Mixin.getByName('notification'),
    ],
    data() {
        return {
            categoryName: '',
            isSubCategory: false,
            isLoading: false,
        };
    },
    created() {
        this.isSubCategory = this.$route.params.isSubCategory || false;
    },
    methods: {
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
                const response = await fetch('/api/_action/infoplus/createCategory', {
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
                    throw new Error('Failed to create category');
                }
                const data = await response.json();
                if(data.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('infoplus.notifications.successTitle'),
                        message: data.message || this.$tc('infoplus.notifications.success.categoryCreated'),
                    });
                    this.$router.back();
                }
                else {
                    this.createNotificationError({
                        title: this.$tc('infoplus.notifications.errorTitle'),
                        message: data.message || this.$tc('infoplus.notifications.error.failedToCreateCategory', 0, { message: '' }),
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.failedToCreateCategory', 0, { message: error.message }),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});