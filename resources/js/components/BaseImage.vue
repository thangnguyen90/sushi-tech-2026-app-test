<template>
    <div class="base-image" :class="[customClass, { 'is-fallback': !isLoaded }]">
        <img v-if="src" :src="src" :alt="alt" @load="onLoad" @error="onError" />

        <!-- Fallback -->
        <div v-if="!isLoaded" class="fallback">
            <slot name="fallback">
                <span>{{ alt || '' }}</span>
            </slot>
        </div>
    </div>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
    src?: string
    alt?: string
    customClass?: string
}

const props = withDefaults(defineProps<Props>(), {
    alt: '',
    customClass: '',
})

const isLoaded = ref(false)

const onLoad = () => {
    isLoaded.value = true
}

const onError = () => {
    isLoaded.value = false
}

// reset khi src thay đổi
watch(
    () => props.src,
    () => {
        isLoaded.value = false
    },
    { immediate: true }
)
</script>
<style scoped lang="scss">
.base-image {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .fallback {
        position: absolute;
        inset: 0;
        background: #eef2f7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 14px;
    }
}
</style>
