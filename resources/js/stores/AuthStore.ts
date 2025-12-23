import Auth from '@/services/app/Auth'
import { LOCALE_CODE } from '@/shared/constants/variables'
import { UserInfo } from '@/shared/interfaces'
import { ApiResponse } from '@/shared/interfaces/response'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        headerTitle: '',
        uuid: localStorage.getItem('uuid') ? localStorage.getItem('uuid') : null,
        user: localStorage.getItem('user') ? localStorage.getItem('user') : null,
        loading: false,
        notLoading: false,
        token: localStorage.getItem('token') ? localStorage.getItem('token') : null,
        passCode: localStorage.getItem('passCode') ? localStorage.getItem('passCode') : null,
        languageCode: localStorage.getItem('languageCode') ? localStorage.getItem('languageCode') : LOCALE_CODE.JPN,
        clearPincode: false,
        serverDate: '',
    }),
    getters: {
        isLoading: (state) => state.loading,
        tokenValue: (state) => state.token,
        passCodeValue: (state) => state.passCode,
        uuidValue: state => state.uuid,
        languageCodeValue: state => state.languageCode,
    },
    actions: {
        toggleLoading() {
            if (!this.notLoading) {
                this.loading = !this.loading;
            };
        },
        toggleNotLoading() {
            this.notLoading = !this.notLoading
        },
        setUser(value: string) {
            this.user = value
        },
        setUuid(value: string) {
            this.uuid = value
            localStorage.setItem('uuid', this.uuid);
        },
        setlanguageCode(value: string) {
            this.languageCode = value
            localStorage.setItem('languageCode', value);
        },
        setToken(value: string) {
            this.token = value
        },
        setServerDate(value: string) {
            this.serverDate = value;
        },
        clearAuthToken() {
            this.token = null
            this.passCode = null
            localStorage.clear();
        },
        async getUser(): Promise<ApiResponse<UserInfo>> {
            return new Promise((resolve, reject) => {
                Auth.getUser()
                    .then(({ data }) => {
                        this.setUser(data?.result)
                        resolve(data);
                    })
                    .catch(({ response }) => reject(response));
            });
        },
    }
})
