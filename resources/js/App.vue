<template>
    <div class="">
        <LoadingComponent :is-loading="storeAuth.loading" />
        <router-view />
    </div>
</template>
<script setup lang="ts">
import { defineAsyncComponent, watch } from 'vue';
import { useAuthStore } from './stores/AuthStore';
import { useErrorStore } from './stores/ErrorStore';

// Components
const LoadingComponent = defineAsyncComponent(() => import('@/components/LoadingComponent.vue'));

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
