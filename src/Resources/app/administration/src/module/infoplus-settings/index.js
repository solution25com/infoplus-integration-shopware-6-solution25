import './page/infoplus-settings';

const { Module } = Shopware;

Module.register('infoplus-settings', {
    type: 'plugin',
    name: 'infoplus-settings',
    title: 'infoplus.settings.title',
    description: 'infoplus.settings.description',
    color: '#000000',
    icon: 'regular-cog',

    routes: {
        index: {
            component: 'infoplus-settings',
            path: 'index',
            children: {
                sync: {
                    component: 'infoplus-sync-settings',
                    path: 'sync',
                },
                categories: {
                    component: 'infoplus-categories',
                    path: 'categories',
                    parameters: {
                        isSubCategory: false
                    }
                },
                subCategories: {
                    component: 'infoplus-categories',
                    path: 'subCategories',
                    parameters: {
                        isSubCategory: true
                    }
                },
                logs: {
                    component: 'log-viewer',
                    path: 'logs',
                }
            }
        },
        addCategory: {
            component: 'infoplus-new-category',
            path: 'addCategory/:isSubCategory',
        },
        editCategory: {
            component: 'infoplus-edit-category',
            path: 'editCategory/:isSubCategory/:id',
        },
        logDetail: {
            component: 'infoplus-log-detail',
            path: 'logDetail/:file',
            props: {
                default: true
            }
        }
    },

    settingsItem: {
        group: 'system',
        to: 'infoplus.settings.index.sync',
        icon: 'regular-cog'
    }
});

