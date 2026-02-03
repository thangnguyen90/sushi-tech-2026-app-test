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
        <!-- <img class="icon-close" src="@/assets/icons/close-modal.png" alt="" @click="closeModal" /> -->
        <div class="title">
            {{ $t("contract.title") }}
        </div>
        <div class="description">
            {{ description }}
        </div>
        <div class="details-contract">
            <div class="label">
                {{ $t("contract.publicInfo.label") }}
            </div>
            <div class="description">
                {{ $t("contract.publicInfo.description") }}
            </div>
        </div>
        <div class="details-contract">
            <div class="label">
                {{ $t("contract.publicScope.label") }}
            </div>
            <div class="description">
                {{ $t("contract.publicScope.description") }}
            </div>
        </div>
        <div class="details-contract">
            <div class="label">
                {{ $t("contract.purpose.label") }}
            </div>
            <div class="description">
                {{ $t("contract.purpose.description") }}
            </div>
        </div>
        <div class="contract-note">
            {{ $t("contract.note") }}
        </div>
        <button class="common-btn mt-1" @click="confirmPolicy">
            {{ $t("contract.confirm") }}
        </button>
        <div class="common-btn cancel" @click="closeModal">
            {{ $t("contract.deny") }}
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
    padding: 24px;
    border-radius: 8px;
    max-height: 90%;
    overflow: auto;

    .title {
        color: #000;
        text-align: center;
        font-size: 20px;
        font-style: normal;
        font-weight: 700;
        text-align: left;
        line-height: 160%;
        white-space: pre-line;
    }

    .description {
        color: #000;
        font-size: 14px;
        font-style: normal;
        font-weight: 400;
        line-height: 24px;
        white-space: pre-line;
    }

    .contract-note {
        color: var(--Black, #000);
        font-size: 12px;
        font-style: normal;
        font-weight: 400;
        line-height: 24px;
    }

    .details-contract {
        .label {
            color: var(--Black, #000);
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: 24px;
            margin-bottom: 8px;
        }
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
        text-align: center;
        font-size: 16px;
        min-height: 48px;
    }

    .refuse-btn {
        color: var(--Ink-500, #979c9e);
        text-align: center;
        font-size: 12px;
        font-style: normal;
        font-weight: 400;
        line-height: normal;
        border-bottom: 1px solid var(--Ink-500, #979c9e);
        padding: 2px;
    }
}
</style>
