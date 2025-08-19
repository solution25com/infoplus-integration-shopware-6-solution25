import template from './infoplus-log-detail.html.twig';

Shopware.Component.register('infoplus-log-detail', {
    template,
    inject: ['infoplusLogApiService'],
    props: {
        file: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            lines: [],
            isLoading: true,
            error: null,
            pagination: {
                page: 1,
                limit: 50
            },
            totalLines: 0
        };
    },
    created() {
        this.loadLogContent();
    },
    methods: {
        async loadLogContent() {
            this.isLoading = true;
            this.error = null;
            try {
                const base = this.infoplusLogApiService.getBasicHeaders
                    ? this.infoplusLogApiService.getBasicHeaders()
                    : {};

                const headers = {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                    'sw-context-token': Shopware.Context.api.contextToken,
                    ...base,
                };
                const url = `/api/_action/infoplus/logs/${encodeURIComponent(this.file)}/content?page=${this.pagination.page}&limit=${this.pagination.limit}`;
                const requestOptions = {
                    method: 'GET',
                    headers: headers,
                    Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                };
                const response = await fetch(url, requestOptions);
                if (!response.ok) {
                    throw new Error(`Failed to load log content: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                this.lines = data.lines || [];
                this.totalLines = data.total || 0;
            } catch (e) {
                this.error = this.$tc('infoplus.logs.errors.failedToLoadLog', 0, { details: e.message }) || this.$tc('infoplus.logs.errors.failedToLoadLogGeneric');
            } finally {
                this.isLoading = false;
            }
        },
        onPageChange(page) {
            this.pagination.page = page.page;
            this.pagination.limit = page.limit;
            this.loadLogContent();
        },
        lineIndex(line) {
            return `${this.pagination.page}-${line}`;
        },
        async downloadLog() {
            try {
                const headers = this.infoplusLogApiService.getBasicHeaders ? this.infoplusLogApiService.getBasicHeaders() : {};
                const url = `/api/_action/infoplus/logs/${encodeURIComponent(this.file)}/download`;
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
                link.download = this.file;
                link.click();
                window.URL.revokeObjectURL(link.href);
            } catch (e) {
                this.error = this.$tc('infoplus.logs.errors.downloadFailed', 0, { message: e.message });
            }
        }
    }
});