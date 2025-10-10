import template from './infoplus-categories.html.twig';

const { Component, Mixin } = Shopware;

Component.register('infoplus-categories', {
    template,
    inject: ['notification'],
    mixins: [
        Mixin.getByName('notification'),
    ],
    props: {
        isLoading: Boolean,
    },
    data() {
        return {
            categories: [],
            isSubCategory: false,
            showDeleteModal: false,
            categoryToDelete: null,
        };
    },
    created() {
        const currentPath = this.$route.path;
        if (currentPath.includes('subCategories')) {
            this.isSubCategory = true;
        } else {
            this.isSubCategory = false;
        }
        this.fetchCategories();
    },
    watch: {
        '$route'(to) {
            const currentPath = to.path;
            if (currentPath.includes('subCategories')) {
                this.isSubCategory = true;
            } else {
                this.isSubCategory = false;
            }
            this.fetchCategories();
        }
    },
    methods: {
        async fetchCategories() {
            try {
                const response = await fetch(`/api/_action/infoplus/getAllCategories/${this.isSubCategory ? '1' : '0'}`, {
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch categories');
                }

                const data = await response.json();
                this.categories = data || [];
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.fetchCategoriesFailed', 0, { message: error.message }),
                });
            }
        },
        addNewCategory() {
            this.$router.push({
                name: `infoplus.settings.addCategory`,
                params: { isSubCategory: this.isSubCategory },
            });
        },
        editCategory(item) {
            this.$router.push({
                name: `infoplus.settings.editCategory`,
                params: {isSubCategory: this.isSubCategory, id: item.id},
            });
        },
        async deleteCategory(item) {
            this.categoryToDelete = item;
            this.showDeleteModal = true;
        },
        async confirmDeleteCategory() {
            try {
                const response = await fetch(`/api/_action/infoplus/delete/category/${this.categoryToDelete.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to delete category');
                }
                const data = await response.json();
                if(data.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('infoplus.notifications.successTitle'),
                        message: data.message || this.$tc('infoplus.notifications.success.categoryDeleted'),
                    });
                }
                else {
                    this.createNotificationError({
                        title: this.$tc('infoplus.notifications.errorTitle'),
                        message: data.message || this.$tc('infoplus.notifications.error.deleteCategoryFailed', 0, { message: '' }),
                    });
                }
                await this.fetchCategories();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('infoplus.notifications.errorTitle'),
                    message: this.$tc('infoplus.notifications.error.deleteCategoryFailed', 0, { message: error.message }),
                });
            } finally {
                this.showDeleteModal = false;
                this.categoryToDelete = null;
            }
        },
        cancelDeleteCategory() {
            this.showDeleteModal = false;
            this.categoryToDelete = null;
        },
    },
});
