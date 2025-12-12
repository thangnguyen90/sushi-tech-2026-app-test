import { defineStore } from 'pinia'

export const useErrorStore = defineStore('error', {
    state: () => ({
        error: {},
        errorModal: false,
        errorQRCode: false,
    }),
    getters: {},
    actions: {}
})
