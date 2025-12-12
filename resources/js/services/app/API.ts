import axios from 'axios';

import { convertKeysToSnakeCase } from '@/utils/useConverter';
import { useAuthStore } from '@/stores/AuthStore';
import { useErrorStore } from '@/stores/ErrorStore';

import type {
    AxiosError,
    AxiosResponse,
    AxiosResponseHeaders,
    InternalAxiosRequestConfig,
} from 'axios';
import { getLanguage } from '@/utils/commonFunction';
import { useI18n } from 'vue-i18n';

const apiClient = axios.create({
    baseURL: `/api`,
    withCredentials: true,
});

const toggleLoading = () => {
    useAuthStore().toggleLoading();
};

apiClient.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        toggleLoading();
        config.headers['user-uuid'] = useAuthStore().uuid;
        config.headers['language'] = getLanguage();
        config.data = convertKeysToSnakeCase(config.data);
        config.params = convertKeysToSnakeCase(config.params);
        return config;
    },
    (error: AxiosError) => {
        toggleLoading();
        return Promise.reject(error);
    },
);

apiClient.interceptors.response.use(
    async (response: AxiosResponse) => {
        const storeAuth = useAuthStore();
        const serverDate = response.headers["date"];
        if (serverDate) storeAuth.setServerDate(serverDate);
        toggleLoading();
        return response;
    },
    async (error: AxiosError | AxiosResponseHeaders) => {
        const storeError = useErrorStore();
        toggleLoading();
        if (error?.response?.data) {
            if (error?.response?.data?.result?.code === TicketErrorCode.Order) {
                const { t: translate } = useI18n();
                storeError.error.message = translate('errorMessages.ticketError');
                return;
            }
            storeError.error = error?.response?.data?.result
                ? error?.response?.data?.result
                : error?.response?.data;
        }
        return Promise.reject(error);
    },
);

export default apiClient;
