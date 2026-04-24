<template>
    <vue-final-modal
        v-model="showModal"
        class="common-modal"
        content-class="common-modal-content qr-modal"
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
        :focusTrap="false"
        :clickToClose="true"
    >
        <img class="icon-close" src="@/assets/icons/close_qr_modal.png" alt="" @click="close">
        <QRCodeVue3
            :value="props.uuid"
            :qrOptions="{
                typeNumber: 0,
                mode: 'Byte',
                errorCorrectionLevel: 'H',
            }"
            :dotsOptions="{
                type: 'square',
                gradient: {
                    rotation: 0,
                    colorStops: [
                        { offset: 0, color: '#000000' },
                        { offset: 1, color: '#000000' },
                    ],
                },
            }"
            :backgroundOptions="{ color: '#ffffff' }"
            :cornersSquareOptions="{ type: 'square', color: '#000000' }"
            :cornersDotOptions="{ type: undefined, color: '#000000' }"
            fileExt="png"
            myclass="my-qr"
            imgclass="img-qr"
        />
    </vue-final-modal>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

interface Props {
    uuid: string;
    modelValue: boolean;
}

interface Emits {
    (e: "update:modelValue", v: Props["modelValue"]): void;
}

const props = defineProps<Props>();
const emits = defineEmits<Emits>();

const showModal = computed({
    get() {
        return props.modelValue;
    },
    set(value) {
        emits("update:modelValue", value);
    },
});

const close = () => emits("update:modelValue", false);
</script>

<style lang="scss">
.common-modal-content.qr-modal {
    padding: 48px;
    border-radius: var(--rounded-base, 8px);
    background: var(--White, #FFF);
    .my-qr {
        img {
            width: 100%;
            border: 6px solid #000;
            padding: 6px;
        }
    }
    .icon-close {
        position: absolute;
        right: 12px;
        top: 12px;
        width: 22px;
    }
}
</style>
