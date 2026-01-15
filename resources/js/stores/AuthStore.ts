import { LOCALE_CODE } from '@/shared/constants/variables'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        uuid: localStorage.getItem('uuid') ? localStorage.getItem('uuid') : null,
        user: localStorage.getItem('user') ? localStorage.getItem('user') : null,
        loading: false,
        notLoading: false,
        languageCode: localStorage.getItem('languageCode') ? localStorage.getItem('languageCode') : LOCALE_CODE.JPN,
    }),
    getters: {
        isLoading: (state) => state.loading,
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
        clearAuthToken() {
            localStorage.clear();
        },
    }
})
