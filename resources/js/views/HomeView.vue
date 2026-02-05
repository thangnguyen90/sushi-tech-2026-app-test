<template>
    <div class="top">
        <img class="top__ai-logo" src="@/assets/images/sushi_ai_chat.png" alt="">
        <div class="d-flex justify-content-between align-items-center top__header">
            <img class="main-logo" src="@/assets/images/sushi_logo.png" alt="" />
            <!-- <div class="d-flex flex-column align-items-center">
                <img class="qr-logo" src="@/assets/images/qr_display.png" alt="" />
                <div class="text-qr">
                    {{ $t('top.header.scanDisplay') }}
                </div>
            </div> -->
        </div>
        <div class="top__list-content mb-3 mt-2">
            <div class="top__list-content-items head">
                <div class="top__list-content-item qr">
                    <img class="icon-item" src="@/assets/images/qr_code_title.png" alt="">
                    <div class="top__list-content-item-details">
                        <div class="label">
                            {{ $t('top.menu.qr.label') }}
                        </div>
                        <div class="note">
                            {{ $t('top.menu.qr.note') }}
                        </div>
                    </div>
                </div>
                <div class="top__list-content-item qr-list">
                    <div class="top__list-content-item-child" @click="viewQrUser">
                        <img class="icon-item" src="@/assets/icons/qr_code.png" alt="">
                        <div class="top__list-content-item-child__text">
                            {{ $t('top.menu.qr.details.view') }}
                        </div>
                    </div>
                    <div class="top__list-content-item-child disabled">
                        <img class="icon-item reading" src="@/assets/icons/reading.png" alt="">
                        <div class="top__list-content-item-child__text">
                            {{ $t('top.menu.qr.details.read') }}
                        </div>
                    </div>
                    <div class="top__list-content-item-child disabled">
                        <img class="icon-item exchange" src="@/assets/icons/exchange.png" alt="">
                        <div class="top__list-content-item-child__text">
                            {{ $t('top.menu.qr.details.history') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="top__title">
            <div class="top__title-text">
                {{ $t('top.title.business') }}
            </div>
            <div class="top__title-undeline"></div>
        </div>
        <div class="top__list-content mb-3">

            <div class="top__list-content-items mt-1" @click="toMatchingList">
                <div class="top__list-content-item">
                    <img src="@/assets/images/matching_list.png" alt="">
                    <div class="top__list-content-item-details">
                        <div class="label">
                            {{ $t('top.menu.appointment.label') }}
                        </div>
                        <div class="note">
                            {{ $t('top.menu.matchingList.note') }}
                        </div>
                    </div>
                    <img class="arrow-icon" src="@/assets/icons/arrow_white_right.svg" alt="">
                </div>
            </div>
            <div class="top__list-content-items" @click="toChatList">
                <div class="top__list-content-item">
                    <img src="@/assets/images/calendar_apointment.png" alt="">
                    <div class="top__list-content-item-details">
                        <div class="label">
                            {{ $t('top.menu.matchingList.label') }}
                        </div>
                        <div class="note">
                            {{ $t('top.menu.appointment.note') }}
                        </div>
                    </div>
                    <img class="arrow-icon" src="@/assets/icons/arrow_white_right.svg" alt="">
                </div>
            </div>
            <div class="top__list-content-items" @click="toNegotiateManagement">
                <div class="top__list-content-item">
                    <img src="@/assets/images/calendar_apointment.png" alt="">
                    <div class="top__list-content-item-details">
                        <div class="label">
                            {{ $t('top.menu.negotiateManagement.label') }}
                        </div>
                        <div class="note">
                            {{ $t('top.menu.negotiateManagement.note') }}
                        </div>
                    </div>
                    <img class="arrow-icon" src="@/assets/icons/arrow_white_right.svg" alt="">
                </div>
            </div>
        </div>
        <div class="top__title">
            <div class="top__title-text">
                {{ $t('tabs.exhibitor') }}
            </div>
            <div class="top__title-undeline"></div>
        </div>
        <div class="top__list-content">
            <div class="top__list-content-items" @click="toExhibitor">
                <div class="top__list-content-item">
                    <img src="@/assets/images/matching_list.png" alt="">
                    <div class="top__list-content-item-details">
                        <div class="label">
                            {{ $t('top.menu.exhibitor.label') }}
                        </div>
                        <div class="note">
                            {{ $t('top.menu.exhibitor.note') }}
                        </div>
                    </div>
                    <img class="arrow-icon" src="@/assets/icons/arrow_white_right.svg" alt="">
                </div>
            </div>
        </div>
        <!-- <div class="top__title">
            <div class="top__title-text">
                {{ $t('top.title.notice') }}
            </div>
            <div class="top__title-undeline"></div>
        </div>
        <div class="top__notice">
            <div class="top__notice-item" v-for="notice in notices" :key="notice.id">
                <div class="top__notice-item-date">
                    {{ formatDate(notice.date, 'yyyy.MM.dd') }}
                </div>
                <div class="top__notice-item-text">
                    {{ notice.title }}
                </div>
            </div>
        </div> -->
        <ContractMatchingModal
            v-model="contractModal"
            :description="$t('contract.description')"
            @update:model-value="(v) => (contractModal = v)"
            @update:confirm="confirmPolicy"
        />
        <QRCodeModal
            v-model="qrModal"
            :uuid="storeAuth.uuid || ''"
            @update:model-value="(v) => (qrModal = v)"
        />
    </div>
</template>
<script setup lang="ts">
import QRCodeModal from "@/components/modals/QRCodeModal.vue";
import {
    useAgreePolicyMutation,
    useUserPolicyStatus,
} from "@/composables/auth";
import { EVENTOS_MODULE_CHAT, EVENTOS_MODULE_CHAT_WEB_LINK, EVENTOS_MODULE_MATCHING, EVENTOS_MODULE_MATCHING_WEB_LINK } from "@/shared/constants/env";
import { useAuthStore } from "@/stores/AuthStore";
import { BusinessWebLink, ExhibitorWebLink, LiveChatRedirect } from "@/utils/constantUrl";
import { defineAsyncComponent, ref, watch } from "vue";

const storeAuth = useAuthStore();
const contractModal = ref<boolean>(false);
const qrModal = ref<boolean>(false);
const moduleMatchingId = EVENTOS_MODULE_MATCHING;
const matchingWebLinkId = EVENTOS_MODULE_MATCHING_WEB_LINK;
const moduleChatId = EVENTOS_MODULE_CHAT;
const chatWebLinkId = EVENTOS_MODULE_CHAT_WEB_LINK;
const ContractMatchingModal = defineAsyncComponent(
    () => import("@/components/modals/ContractMatchingModal.vue"),
);
// const HeroSlider = defineAsyncComponent(
//     () => import("@/components/carousel/HeroSlider.vue"),
// );

const { mutate } = useAgreePolicyMutation();
useUserPolicyStatus();

const confirmPolicy = () => {
    mutate(undefined, {
        onSuccess: () => {
            contractModal.value = false;
            window.location.href = LiveChatRedirect(moduleMatchingId, matchingWebLinkId);
        },
        onError: (error) => {
            console.error(error);
            contractModal.value = false;
        },
    });
};

// const notices = ref([
//     {
//         id: 1,
//         title: "【会場変更】「基調講演」の会場がAホールへ変更になりました",
//         date: new Date().toISOString(),
//     },
//     {
//         id: 2,
//         title: "【ネットワーキング満席】14:00回は予約上限に達しました",
//         date: new Date().toISOString(),
//     },
//     {
//         id: 3,
//         title: "【名刺交換ブース】混雑緩和のため待機列を2列に変更しました",
//         date: new Date().toISOString(),
//     },
// ])

const viewQrUser= () => {
    qrModal.value = true;
}

const toMatchingList = () => {
    if (storeAuth.user?.policy_agreed) {
        window.location.href = LiveChatRedirect(moduleMatchingId, matchingWebLinkId);
        return
    }
    contractModal.value = true;
}

const toChatList = () => {
    window.location.href = LiveChatRedirect(moduleChatId, chatWebLinkId);
}

const toNegotiateManagement = () => {
    window.location.href = BusinessWebLink();
}

const toExhibitor = () => {
    window.location.href = ExhibitorWebLink();
}

watch(
    () => storeAuth.user,
    (user) => {
        if (user) contractModal.value = !user.policy_agreed;
    },
    { immediate: true },
);
</script>
<style lang="scss" scoped>
.top {
    position: relative;
    background-image:
        url("@/assets/images/sushi_bg.png"),
        url("@/assets/images/sushi_bg2.png");
    background-size: 60%;
    background-repeat: no-repeat;
    background-position:
        left top 90px,
        right top 520px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 16px 0 31px;
    >div {
        padding: 0 16px;
        &.slider-container {
            padding: 0;
        }
    }
    &__ai-logo {
        position: fixed;
        bottom: 10%;
        right: 0;
        width: 113px;
        z-index: 1;
    }
    &__header {
        .main-logo {
            width: 222px;
            padding-top: 15px;
        }
        .qr-logo {
            width: 36px;
        }
        .text-qr {
            color: #FFF;
            font-size: 12px;
            font-style: normal;
            font-weight: 700;
            line-height: 24px;
        }
    }
    &__title {
        display: flex;
        flex-direction: column;
        align-items: center;
        &-text {
            padding-bottom: 8px;
            color: #FFF;
            font-size: 20px;
            font-style: normal;
            font-weight: 700;
            line-height: 24px;
        }
        &-undeline {
            height: 4px;
            border-radius: 0.5px;
            width: 40px;
            background-color: #E60013;
        }
    }
    &__list-content {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 12px;
        --bs-gutter-x: 0px;
        --bs-gutter-y: 31px;
        .col-6 {
            --bs-gutter-x: 20px;
        }
        &-items {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 12px;
            padding: 16px 24px;
            align-self: center;
            border-radius: 8px;
            background: var(--Zinc-800, #202325);
            color: #FFF;
            text-align: center;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: 24px;
            &.head {
                padding: 8px;
                gap: 12px;
                background: #FFF;
                padding: 12px;
            }
        }
        &-item {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
            align-self: center;
            border-radius: 8px;
            background: var(--Zinc-800, #202325);
            color: #FFF;
            text-align: center;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: 24px;
            width: 100%;
            .arrow-icon {
                width: 24px;
                margin-left: auto;
            }
            &.qr-list {
                display: flex;
                justify-content: center;
                gap: 8px;
                background: unset;
                .top__list-content-item-child {
                    min-height: 82px;
                }
            }
            &-child {
                padding: 16px 8px 8px;
                border-radius: 8px;
                background: var(--Zinc-800, #202325);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                flex: 1;
                &.disabled {
                    background: var(--Zinc-300, #CDCFD0);
                }
                .icon-item {
                    max-width: 26px;
                    &.reading {
                        max-width: 18px;
                    }
                    &.exchange {
                        max-width: 27px;
                    }
                }
            }
            &-details {
                text-align: left;
                .label {
                    color: var(--White, #FFF);
                    font-size: 18px;
                    font-style: normal;
                    font-weight: 700;
                    line-height: 24px;
                }
                .note {
                    color: var(--White, #FFF);
                    font-size: 12px;
                    font-style: normal;
                    font-weight: 400;
                    line-height: 160%;
                }
            }
            >img {
                width: 45px;
            }
            &.qr {
                background: #ffffff;
                color: #FFF;
                font-size: 18px;
                font-style: normal;
                font-weight: 700;
                line-height: 24px;
                padding: 4px 12px;
                .top__list-content-item-details {
                    font-size: 18px;
                    font-style: normal;
                    font-weight: 700;
                    line-height: 24px;
                    .label {
                        color: #000;
                    }
                    .note {
                        color: #000;
                    }
                }
            }
            &.business-card {
                padding: 4px 12px;
                background: #FFF;
                color: #000;
                .top__list-content-item-details {
                    .label {
                        color: #000;
                    }
                    .note {
                        color: #000;
                    }
                }
            }
            &.qr,&.business-card {
                .icon-item {
                    width: 45px;
                }
            }
            &.matching {
                >img {
                    width: 55px;
                }
            }
        }
    }
    &__notice {
        &-item {
            &:first-child {
                padding-top: 0;
            }
            padding-top: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #666;
            &-text {
                color: #FFF;
                font-size: 14px;
                font-style: normal;
                font-weight: 700;
                line-height: 160%;
            }
            &-date {
                color: #FFF;
                font-size: 14px;
                font-style: normal;
                font-weight: 400;
                line-height: 160%;
                padding-bottom: 8px;
            }
        }
    }
}
</style>
