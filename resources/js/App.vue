<template>
    <div class="">
        <LoadingComponent :is-loading="storeAuth.loading" />
        <router-view />
    </div>
</template>
<script setup lang="ts">
import { watch } from 'vue';
import LoadingComponent from './components/LoadingComponent.vue';
import { useAuthStore } from './stores/AuthStore';
import { useErrorStore } from './stores/ErrorStore';

const storeAuth = useAuthStore();
const storeError = useErrorStore();

watch(
    () => [storeError.errorModal, storeAuth.loading, storeError.errorQRCode],
    ([errorModal, loading, errorQRCode]) => {
        if (errorModal || loading || errorQRCode) {
            document.body.style.overflow = "hidden";
        } else {
            document.body.style.overflow = "auto";
        }
    },
    { immediate: true },
)
</script>
