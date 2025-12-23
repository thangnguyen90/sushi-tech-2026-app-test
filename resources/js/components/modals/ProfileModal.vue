<template>
    <vue-final-modal
        v-model="showModal"
        class="common-modal"
        overlay-class="profile-modal__overlay"
        content-class="profile-modal__content"
        :lock-scroll="true"
        :click-to-close="true"
        :esc-to-close="true"
    >
        <div class="common-modal-content">
            <!-- Header -->
            <div class="modal-header">
                <img
                    class="cover"
                    src="@/assets/images/profile_bg.png"
                    alt=""
                />
                <!-- Avatar -->
                <div class="avatar-wrapper">
                    <img class="avatar" :src="userInfo.avatar" alt="" />
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <h3 class="name">{{ userInfo.name }}</h3>
                <div class="company">{{ userInfo.company }}</div>

                <span class="user-id">ID: {{ userInfo.userId }}</span>

                <div class="message">
                    {{ userInfo.message }}
                </div>

                <div class="section">
                    <div class="section-title">{{ $t('profile.title') }}</div>

                    <div
                        v-for="item in profileRows"
                        :key="item.label"
                        class="row"
                    >
                        <span class="label">{{ $t(item.label) }}</span>
                        <a v-if="item.isUrl" class="" href="" target="">
                            {{ item.value }}
                        </a>
                        <div class="section-category" v-else-if="item.isCategory">
                            <span
                                v-for="category in item.value"
                                :key="category.id"
                            >
                                {{ category.name ? `#${category.name}` : "" }}
                            </span>
                        </div>
                        <span v-else class="label-info">{{ item.value }}</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer -->
        <div class="modal-footer">
            <button class="action-btn" @click="handleConfirm">{{ $t('actions.talk') }}</button>
        </div>
    </vue-final-modal>
</template>
<script setup lang="ts">
import { UserMatchingInfo } from "@/shared/interfaces/matching";
import { formatDate } from "@/utils/useDate";
import { computed } from "vue";
import { VueFinalModal } from "vue-final-modal";

interface Props {
    modelValue: boolean;
    userInfo: UserMatchingInfo;
}

interface Emits {
    (e: "update:modelValue", v: Props["modelValue"]): void;
    (e: "update:confirm"): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const showModal = computed({
    get() {
        return props.modelValue;
    },
    set(value) {
        emit("update:modelValue", value);
    },
});

const profileRows = computed(() => [
    { label: "profile.department", value: props.userInfo.department },
    { label: "profile.position", value: props.userInfo.position },
    { label: "profile.email", value: props.userInfo.email },
    { label: "profile.phone", value: props.userInfo.phone },
    { label: "profile.gender", value: props.userInfo.gender ? "男性" : "女性" },
    {
        label: "profile.birthday",
        value: formatDate(props.userInfo.birthday, "yyyy/MM/dd"),
    },
    { label: "profile.snsUrl", value: props.userInfo.url, isUrl: true },
    { label: "profile.purpose", value: props.userInfo.purpose },
    { label: "profile.category", value: props.userInfo.category, isCategory: true },
]);

const close = () => emit("update:modelValue", false);
const handleConfirm = () => {
    emit("update:confirm");
    close();
};
</script>
<style lang="scss">
.profile-modal__overlay {
    background: rgba(0, 0, 0, 0.25);
}

.profile-modal__content {
    height: 85%;
    width: 90%;
    .common-modal-content {
        width: 100%;
        height: 90%;
        padding: 0;
        overflow: auto;
        border-radius: 16px 16px 0 0;
    }
}

.modal-header {
    position: relative;
    width: 100%;

    .cover {
        width: 100%;
        height: 160px;
        object-fit: cover;
    }
}

.avatar-wrapper {
    display: flex;
    justify-content: center;
    position: absolute;
    bottom: -40px;
    left: 0;
    right: 0;

    .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 1px solid #d9d9d9;
        object-fit: cover;
        background: black;
        aspect-ratio: 1/1;
        z-index: 1;
    }
}

.modal-body {
    padding: 16px 12px;
    max-width: 100%;
    width: 100%;
    padding-top: 28px;

    .name {
        text-align: center;
        margin: 8px 0 4px;
    }

    .company {
        text-align: center;
        color: #888;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .user-id {
        display: block;
        margin: 8px auto;
        background: #eee;
        padding: 4px 12px;
        border-radius: 999px;
        width: fit-content;
        font-size: 12px;
    }

    .message {
        margin: 12px 0;
        font-size: 14px;
        margin-top: 8px;
    }

    .section {
        margin-top: 24px;

        .section-title {
            color: #404446;
            font-family: Inter;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: normal;
            padding-bottom: 16px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 6px 0;
            color: #404446;
            font-family: Inter;
            font-size: 14px;
            font-style: normal;
            line-height: normal;
            padding-bottom: 24px;
            padding-top: 0;
            &:last-child {
                padding-bottom: 0;
            }
            .label {
                font-weight: 700;
            }
            .label-info {
                font-weight: 500;
            }
        }
        .section-category {
            color: #e60013;
            font-family: Inter;
            font-size: 14px;
            font-style: normal;
            font-weight: 600;
            line-height: normal;
            >span {
                margin-right: 2px;
                &:last-child {
                    margin-right: 0;
                }
            }
        }
    }
}

.modal-footer {
    padding: 12px;
    width: 100%;
    justify-content: center;
    background: #fff;
    box-shadow: 0 -2px 4px 0 rgba(0, 0, 0, 0.25);

    .action-btn {
        min-width: 145px;
        padding: 10px;
        border-radius: 8px;
        background: var(--brand_red, #e60013);
        color: #fff;
        border: none;
        text-align: center;
        font-family: Arial;
        font-size: 14px;
        font-style: normal;
        font-weight: 700;
        line-height: normal;
    }
}
</style>
