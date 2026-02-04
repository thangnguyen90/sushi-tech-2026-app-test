<template>
    <VueFinalModal
        v-model="show"
        class="offline-modal-page"
        content-class="offline-modal-container"
        :click-to-close="false"
        :esc-to-close="false"
        :lock-scroll="true"
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
    >
        <div class="offline-modal">
            <div class="offline-icon">!</div>

            <p class="offline-title">ページを開けませんでした</p>

            <p class="offline-subtitle">インターネット接続がありません</p>
            <button class="w-100 common-btn">Ok</button>
        </div>
    </VueFinalModal>
</template>

<script setup lang="ts">
import { useNetwork } from "@vueuse/core";
import { VueFinalModal } from "vue-final-modal";
import { computed, watch } from "vue";

const { isOnline } = useNetwork();

// show modal when OFFLINE
const show = computed(() => !isOnline.value);

watch(
    () => show.value,
    (value) => {
        if (value) {
            window.location.reload();
        }
    },
)
</script>

<style>
.offline-modal-container {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100svh;
}

.offline-modal {
    background: #fff;
    border-radius: 12px;
    padding: 32px 24px;
    width: 100%;
    max-width: 320px;
    text-align: center;
}

.offline-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 24px;
    border-radius: 50%;
    border: 2px solid #cfe6ea;
    color: #9ccbd3;
    font-size: 32px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.offline-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.offline-subtitle {
    font-size: 14px;
    color: #666;
}
</style>
