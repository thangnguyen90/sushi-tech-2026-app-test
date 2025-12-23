<template>
    <div class="matching-select" ref="wrapper">
        <!-- Trigger -->
        <div class="select-trigger" @click="toggle">
            <div class="red-pill">
                {{ selectedOption?.label }}
            </div>
            <img class="arrow" :class="{ open: isOpen }" src="@/assets/icons/arrow_dropdown.svg" alt="">
        </div>

        <!-- Dropdown -->
        <div v-if="isOpen" class="dropdown">
            <div v-for="item in options" :key="item.value" class="dropdown-item"
                :class="{ active: modelValue === item.value }" @click="select(item)">
                {{ item.label }}
            </div>
        </div>
    </div>
</template>


<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

export interface Option {
    label: string
    value: string
}

interface Props {
    options: Option[]
    modelValue?: string
}

interface Emits {
    (e: 'update:modelValue', value: string): void
    (e: 'change', option: Option): void
    (e: 'ready'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isOpen = ref(false)
const wrapper = ref<HTMLElement | null>(null)

const selectedOption = computed(() => {
    return props.options.find(o => o.value === props.modelValue) || props.options[0]
})

const toggle = () => {
    isOpen.value = !isOpen.value
}

const select = (item: Option) => {
    emit('update:modelValue', item.value)
    emit('change', item)
    isOpen.value = false
}

const handleClickOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) {
        isOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    emit('ready')
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style lang="scss" scoped>
.matching-select {
    position: relative;
    width: 100%;
}

.select-trigger {
    background: #f2f5fa;
    border-radius: 999px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.red-pill {
    flex: 1;
    background: #e60012;
    color: #fff;
    height: 40px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.arrow {
    margin-left: 12px;
    font-size: 18px;
    transition: transform 0.2s ease;

    &.open {
        transform: rotate(180deg);
    }
}

.dropdown {
    position: absolute;
    top: 56px;
    left: 0;
    width: 100%;
    background: #EEF2FA;
    border-radius: 8px;
    padding: 10px 8px;
    border: 1px solid #CDCFD0;
    box-shadow: 0 4px 4px 0 rgba(0, 0, 0, 0.25);
    z-index: 10;
}

.dropdown-item {
    padding: 13px;
    font-weight: 500;
    cursor: pointer;
    color: #3D4F67;
    text-align: center;
    font-family: Roboto;
    font-size: 14px;
    font-style: normal;
    font-weight: 700;
    line-height: normal;

    &.active {
        color: #FFF;
        border-radius: var(--lv99_999, 999px);
        border-bottom: 1px solid #DADADA;
        background: var(--brand_red, #E60013);
    }

    &:hover {
        color: #FFF;
        border-radius: var(--lv99_999, 999px);
        background: var(--brand_red, #E60013);
    }
}
</style>
