<template>
    <vue-final-modal
        v-model="showModal"
        class="common-modal"
        content-class="common-modal-content contract"
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
        :focusTrap="false"
        :clickToClose="false"
    >
        <img class="icon-close" src="@/assets/icons/close-modal.png" alt="" @click="closeModal" />
        <div class="title">
            {{ $t('contract.title') }}
        </div>
        <div class="description">
            {{ description }}
        </div>
        <button class="common-btn" @click="confirmPolicy">
            {{ $t('contract.confirm') }}
        </button>
        <div class="refuse-btn" @click="closeModal">
            {{ $t('contract.deny') }}
        </div>
    </vue-final-modal>
</template>

<script lang="ts" setup>
import { computed } from "vue";

export interface Props {
    modelValue: boolean;
    description: string;
}
interface Emits {
    (e: "update:modelValue", v: Props["modelValue"]): void;
    (e: "update:confirm"): void;
}
const emits = defineEmits<Emits>();
const props = defineProps<Props>();

const showModal = computed({
    get() {
        return props.modelValue;
    },
    set(value) {
        emits("update:modelValue", value);
    },
});

const confirmPolicy = (): void => {
    emits("update:confirm");
};

const closeModal = (): void => {
    emits("update:modelValue", false);
};
</script>

<style lang="scss">
.common-modal-content.contract {
    padding: 36px 16px;
    .title {
        color: #000;
        text-align: center;
        font-family: Roboto;
        font-size: 16px;
        font-style: normal;
        font-weight: 700;
        line-height: 19.6px;
    }
    .description {
        color: #000;
        font-family: Roboto;
        font-size: 13px;
        font-style: normal;
        font-weight: 500;
        line-height: 150%;
        padding: 10px;
        white-space: pre-line;
    }
    .icon-close {
        position: absolute;
        top: -12px;
        right: -12px;
        cursor: pointer;
        width: 37px;
    }
    .common-btn {
        width: 100%;
        color: #FFF;
        text-align: center;
        font-family: Roboto;
        font-size: 16px;
    }
    .refuse-btn {
        color: var(--Ink-500, #979C9E);
        text-align: center;
        font-family: Roboto;
        font-size: 12px;
        font-style: normal;
        font-weight: 500;
        line-height: normal;
        border-bottom: 1px solid var(--Ink-500, #979C9E);
        padding: 2px;
    }
}
</style>
