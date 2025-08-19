import template from './log-viewer.html.twig';
import './log-viewer.scss';

Shopware.Component.register('log-viewer', {
    template,

    inject: ['infoplusLogApiService'],

    data() {
        return {
            logs: [],
            isLoading: true,
            error: null,
            pagination: {
                page: 1,
                limit: 10
            },
            limitOptions: [
                { value: 5, label: '5' },
                { value: 10, label: '10' },
                { value: 25, label: '25' },
                { value: 50, label: '50' }
            ],
            columns: [
                { property: 'file', label: this.$tc('infoplus.logs.columns.file') }
            ],
            rowActions: [
                {
                    label: this.$tc('infoplus.logs.actions.view'),
                    action: 'view',
                    icon: 'default-eye',
                },
                {
                    label: this.$tc('infoplus.logs.actions.download'),
                    action: 'download',
                    icon: 'default-arrow-down',
                }
            ]
        };
    },

    computed: {
        paginatedLogs() {
            if (!this.logs || this.logs.length === 0) return [];
            const start = (this.pagination.page - 1) * this.pagination.limit;
            return this.logs.slice(start, start + this.pagination.limit);
        }
    },

    created() {
        this.loadData();
    },

    methods: {
        async loadData() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await this.infoplusLogApiService.getLogs();
                this.logs = response.logs || [];
                const maxPage = Math.max(1, Math.ceil(this.logs.length / this.pagination.limit));
                if (this.pagination.page > maxPage) {
                    this.pagination.page = maxPage;
                }
            } catch (e) {
                this.error = e?.response?.data?.errors?.[0]?.detail || this.$tc('infoplus.logs.errorLoadLogs');
            } finally {
                this.isLoading = false;
            }
        },
        onPageChange(page) {
            this.pagination.page = page.page;
            this.pagination.limit = page.limit;
        },
        onViewLog(log) {
            this.$router.push({ name: 'infoplus.settings.logDetail', params: { file: log.file } });
        },
        async onDownloadLog(log) {
            try {
                const headers = this.infoplusLogApiService.getBasicHeaders ? this.infoplusLogApiService.getBasicHeaders() : {};
                const url = `/api/_action/infoplus/logs/${encodeURIComponent(log.file)}/download`;
                const response = await fetch(url, {
                    method: 'GET',
                    headers: headers,
                });
                if (!response.ok) {
                    throw new Error('Download failed: ' + response.statusText);
                }
                const blob = await response.blob();
                const link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = log.file;
                link.click();
                window.URL.revokeObjectURL(link.href);
            } catch (e) {
                this.error = this.$tc('infoplus.notifications.error.downloadFailed', 0, { message: e.message });
            }
        },
        onRowAction(action, log) {
            if (action === 'view') {
                this.onViewLog(log);
            } else if (action === 'download') {
                this.onDownloadLog(log);
            }
        }
    },
});