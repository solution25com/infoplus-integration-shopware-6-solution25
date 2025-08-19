import template from './sync-button.html.twig';

const {Component, Mixin } = Shopware;

Component.register('sync-button', {
    template,
    inject: ['notification'],
    mixins: [
        Mixin.getByName('notification'),
    ],
    created() {
        this.fetchSyncStatus();
    },
    data() {
        return {
            lastSyncTime: "",
            syncButtonText: this.$tc('infoplus.sync.startSynchronization')
        };
    },
    methods: {
        fetchSyncStatus() {
            fetch('/api/_action/infoplus/config', {
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${Shopware.Context.api.authToken.access}`
                }
            })
                .then(res => res.json())
                .then(data => {
                    const isLoading = !!data.syncInProgress;
                    this.syncButtonText = isLoading ? this.$tc('infoplus.sync.synchronizingInBackground') : this.$tc('infoplus.sync.startSynchronization');
                    if(data.lastSyncTime){
                        const lastSyncDate = new Date(data.lastSyncTime);
                        this.lastSyncTime = this.$tc('infoplus.sync.lastSuccessfulSync') + " " + lastSyncDate.toLocaleString('en-US', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        });
                    }
                    else{
                        this.lastSyncTime = isLoading ? "" : this.$tc('infoplus.sync.neverSynchronized');
                    }
                    const syncButton = document.getElementById('infoplus-sync-btn');
                    if (syncButton) {
                        syncButton.disabled = isLoading;
                    }
                });
        },
        triggerSync() {
            fetch("/api/_action/infoplus/sync/all", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${Shopware.Context.api.authToken.access}`
                }
            })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    const syncButton = document.getElementById('infoplus-sync-btn');
                    if (syncButton) {
                        syncButton.disabled = true;
                        syncButton.textContent = this.$tc('infoplus.sync.synchronizingInBackground');
                        this.createNotificationSuccess({
                            message: this.$tc('infoplus.sync.synchronizationStarted'),
                        });
                    }
                })
                .catch(error => {
                    alert(this.$tc('infoplus.sync.synchronizationFailed') + error);
                    const syncButton = document.getElementById('infoplus-sync-btn');
                    if (syncButton) {
                        syncButton.disabled = false;
                        syncButton.textContent = this.$tc('infoplus.sync.startSynchronization');
                    }
                });
        }
    }
});