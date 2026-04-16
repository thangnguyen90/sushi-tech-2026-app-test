<template>
    <vue-final-modal
        v-model="showModal"
        class="common-modal qr-modal-shell"
        content-class="common-modal-content qr-modal-content"
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
        :focusTrap="false"
        :clickToClose="true"
    >
        <div class="qr-modal-layout">
            <div class="qr-modal-action qr-modal-action--left" aria-hidden="true">
                <img src="@/assets/icons/qr_code.png" alt="" />
            </div>

            <button type="button" class="qr-modal-action qr-modal-action--right" @click="close">
                <span class="visually-hidden">{{ $t('common.close') }}</span>
            </button>

            <div class="qr-phone">
                <div class="qr-phone__frame">
                    <div class="qr-phone__screen">
                        <div class="qr-phone__status">
                            <span class="qr-phone__status-time">9:41</span>
                            <div class="qr-phone__status-icons">
                                <span class="cellular"></span>
                                <span class="wifi"></span>
                                <span class="battery">
                                    <span class="battery-level"></span>
                                </span>
                            </div>
                        </div>

                        <div class="qr-phone__notch"></div>

                        <div class="qr-phone__content">
                            <div class="qr-phone__event-title">SUSHI TECH TOKYO 2026</div>

                            <h2 class="qr-phone__heading">商談場所の予約</h2>

                            <div class="qr-phone__copy">
                                <p>SusHi Tech Tokyo 2026で会場内にある商談場所を予約することができます。</p>
                                <p>
                                    商談場所を予約するには商談場所をご指定の場所までいって上、
                                    当日会場内でスムーズに商談を始めることができます。
                                </p>
                                <p>
                                    商談場所の利用には事前に商談場所を予約するか、
                                    当日会場内でスムーズに商談を始めることができます。
                                </p>
                            </div>

                            <div class="qr-phone__qr-wrapper">
                                <div class="qr-phone__qr-rail qr-phone__qr-rail--left"></div>
                                <div class="qr-phone__qr-rail qr-phone__qr-rail--right"></div>

                                <div class="qr-phone__qr-card">
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
                                        myclass="qr-phone__qr"
                                        imgclass="qr-phone__qr-image"
                                    />
                                </div>
                            </div>

                            <div class="qr-phone__map-link">マップを表示する</div>
                        </div>

                        <div class="qr-phone__nav">
                            <div class="qr-phone__nav-item is-active">
                                <span class="icon icon-home"></span>
                                <span>Home</span>
                            </div>
                            <div class="qr-phone__nav-item">
                                <span class="icon icon-map"></span>
                                <span>Map</span>
                            </div>
                            <div class="qr-phone__nav-item">
                                <span class="icon icon-agenda"></span>
                                <span>My Agenda</span>
                            </div>
                            <div class="qr-phone__nav-item">
                                <span class="icon icon-account"></span>
                                <span>Account</span>
                            </div>
                            <div class="qr-phone__nav-item">
                                <span class="icon icon-menu"></span>
                                <span>Menu</span>
                            </div>
                        </div>

                        <div class="qr-phone__home-indicator"></div>
                    </div>
                </div>
            </div>
        </div>
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

const close = (): void => emits("update:modelValue", false);
</script>

<style lang="scss">
.common-modal-content.qr-modal-content {
    width: 100vw;
    max-width: none;
    height: 100vh;
    border-radius: 0;
    background: #020202;
    padding: 0;
    gap: 0;
    box-shadow: none;
}

.qr-modal-layout {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at top center, rgba(38, 38, 38, 0.35), transparent 30%),
        linear-gradient(180deg, #050505 0%, #000000 100%);
    overflow: hidden;
}

.qr-modal-action {
    position: absolute;
    top: 36px;
    width: 52px;
    height: 52px;
    border-radius: 999px;
    background: rgba(194, 194, 194, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;

    &--left {
        left: 12px;

        img {
            width: 26px;
            height: 26px;
            object-fit: contain;
            filter: grayscale(1) brightness(0.25);
        }
    }

    &--right {
        right: 12px;
        border: 0;
        padding: 0;

        &::before,
        &::after {
            content: "";
            position: absolute;
            width: 28px;
            height: 3px;
            border-radius: 999px;
            background: #1a1a1a;
        }

        &::before {
            transform: rotate(45deg);
        }

        &::after {
            transform: rotate(-45deg);
        }
    }
}

.qr-phone {
    width: min(234px, 62vw);
    aspect-ratio: 234 / 454;
    display: flex;
    align-items: center;
    justify-content: center;

    &__frame {
        width: 100%;
        height: 100%;
        padding: 7px;
        border-radius: 40px;
        background:
            linear-gradient(145deg, rgba(255, 255, 255, 0.95) 0%, rgba(137, 137, 137, 0.95) 55%, rgba(255, 255, 255, 0.88) 100%);
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.65),
            0 20px 48px rgba(0, 0, 0, 0.5);
    }

    &__screen {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        border-radius: 34px;
        background: #f5f5f5;
        overflow: hidden;
    }

    &__status {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 18px 6px;
        font-family: "SF Pro Text", system-ui, sans-serif;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
    }

    &__status-icons {
        display: flex;
        align-items: center;
        gap: 5px;

        .cellular {
            width: 12px;
            height: 9px;
            background: linear-gradient(90deg, #111 16%, transparent 16% 32%, #111 32% 48%, transparent 48% 64%, #111 64% 80%, transparent 80%);
        }

        .wifi {
            position: relative;
            width: 12px;
            height: 9px;

            &::before,
            &::after {
                content: "";
                position: absolute;
                left: 50%;
                border: 1.6px solid transparent;
                border-top-color: #111;
                border-radius: 50%;
                transform: translateX(-50%);
            }

            &::before {
                top: 1px;
                width: 12px;
                height: 9px;
            }

            &::after {
                top: 4px;
                width: 6px;
                height: 5px;
            }
        }

        .battery {
            position: relative;
            width: 16px;
            height: 9px;
            border: 1px solid #111;
            border-radius: 3px;
            padding: 1px;

            &::after {
                content: "";
                position: absolute;
                top: 2px;
                right: -3px;
                width: 2px;
                height: 4px;
                border-radius: 0 2px 2px 0;
                background: #111;
            }
        }

        .battery-level {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 2px;
            background: #ffcc32;
        }
    }

    &__notch {
        position: absolute;
        top: 10px;
        left: 50%;
        transform: translateX(-50%);
        width: 76px;
        height: 17px;
        border-radius: 999px;
        background: #060606;
        z-index: 1;
    }

    &__content {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px 16px 12px;
        text-align: center;
    }

    &__event-title {
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 16px;
        text-transform: uppercase;
    }

    &__heading {
        margin-bottom: 10px;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.3;
    }

    &__copy {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 16px;
        color: #1f1f1f;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 6px;
        font-weight: 500;
        line-height: 1.55;

        p {
            margin-bottom: 0;
        }
    }

    &__qr-wrapper {
        position: relative;
        width: 144px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }

    &__qr-rail {
        position: absolute;
        top: 50%;
        width: 14px;
        height: 22px;
        border-radius: 999px;
        background: #c52626;
        transform: translateY(-50%);

        &--left {
            left: -6px;
        }

        &--right {
            right: -6px;
        }
    }

    &__qr-card {
        position: relative;
        z-index: 1;
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }

    &__qr {
        display: flex;
        align-items: center;
        justify-content: center;

        img {
            width: 100%;
            padding: 0;
            border: 0;
        }
    }

    &__map-link {
        color: #b41d1d;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 8px;
        font-weight: 700;
        line-height: 1.3;
        text-decoration: underline;
    }

    &__nav {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        align-items: end;
        gap: 4px;
        padding: 8px 10px 10px;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        background: #fff;
    }

    &__nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 3px;
        color: #8e8e93;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 5px;
        line-height: 1.2;

        .icon {
            position: relative;
            width: 14px;
            height: 12px;
            color: inherit;
        }

        &.is-active {
            color: #c52626;
            font-weight: 700;
        }
    }

    &__home-indicator {
        width: 84px;
        height: 4px;
        margin: 0 auto 6px;
        border-radius: 999px;
        background: #111;
        opacity: 0.8;
    }
}

.icon-home {
    &::before {
        content: "";
        position: absolute;
        left: 2px;
        right: 2px;
        bottom: 0;
        height: 7px;
        border-radius: 2px 2px 0 0;
        border: 1px solid currentColor;
        border-bottom: 0;
    }

    &::after {
        content: "";
        position: absolute;
        left: 1px;
        top: 1px;
        width: 12px;
        height: 12px;
        border-top: 1px solid currentColor;
        border-left: 1px solid currentColor;
        transform: rotate(45deg) scale(0.64);
        transform-origin: center;
    }
}

.icon-map {
    &::before,
    &::after {
        content: "";
        position: absolute;
        inset: 1px 3px;
        border: 1px solid currentColor;
        transform: skewY(-18deg);
    }

    &::after {
        inset: 1px 6px 1px 0;
        transform: translateX(-1px) skewY(18deg);
        opacity: 0.55;
    }
}

.icon-agenda {
    &::before,
    &::after {
        content: "";
        position: absolute;
        border: 1px solid currentColor;
        border-radius: 2px;
    }

    &::before {
        inset: 1px 2px 1px 5px;
    }

    &::after {
        inset: 2px 5px 2px 2px;
        opacity: 0.45;
    }
}

.icon-account {
    &::before,
    &::after {
        content: "";
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        border: 1px solid currentColor;
    }

    &::before {
        top: 1px;
        width: 5px;
        height: 5px;
        border-radius: 999px;
    }

    &::after {
        bottom: 1px;
        width: 9px;
        height: 5px;
        border-radius: 999px 999px 3px 3px;
    }
}

.icon-menu {
    &::before,
    &::after {
        content: "";
        position: absolute;
        left: 2px;
        right: 2px;
        height: 1px;
        background: currentColor;
        box-shadow: 0 4px 0 currentColor;
    }

    &::before {
        top: 3px;
    }

    &::after {
        top: 7px;
    }
}

@media (max-width: 480px) {
    .qr-phone {
        width: min(234px, calc(100vw - 80px));
    }
}
</style>
