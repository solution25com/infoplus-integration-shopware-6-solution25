const { ApiService } = Shopware.Classes;

export default class InfoplusLogApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'infoplus');
        this.name = 'infoplusLogApiService';
    }

    getLogs() {
        const headers = this.getBasicHeaders(); 
        return this.httpClient
            .get('_action/infoplus/logs', { headers })
            .then(ApiService.handleResponse);
    }

    getLog(fileName) {
        const headers = this.getBasicHeaders();
        return this.httpClient
            .get(`/_action/infoplus/logs/${encodeURIComponent(fileName)}`, { headers })
            .then(ApiService.handleResponse);
    }
}
