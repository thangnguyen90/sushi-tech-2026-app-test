<template>
    <Transition name="fade">
        <div v-if="props.isLoading" class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
    </Transition>
</template>

<script setup>
import { defineProps } from "vue";

const props = defineProps({
    isLoading: {
        type: Boolean,
        default: false,
    },
});
</script>

<style lang="css" scoped>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100lvh;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-overlay .loading-spinner {
    width: 30px;
    height: 30px;
    background: conic-gradient(
        from 180deg at 50% 50%,
        #59a8fd 0deg,
        rgba(89, 168, 253, 0) 360deg
    );
    mask-image: radial-gradient(transparent 50%, black 51%);
    -webkit-mask-image: radial-gradient(transparent 50%, black 51%);
    border-radius: 30px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(360deg);
    }

    100% {
        transform: rotate(0);
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
