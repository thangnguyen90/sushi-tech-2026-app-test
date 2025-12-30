<template>
    <ProfileModal
        v-model="showProfile"
        :user-info="userInfo"
        @update:model-value="(v) => (showProfile = v)"
    />
    <div class="matching-list">
        <div ref="selectHeader" class="matching-list__header">
            <SelectBoxComponent
                v-model="status"
                :options="statusOptions"
                @ready="getHeaderHeight"
                @change="changeStatus"
            />
        </div>
        <div
            class="matching-list__body"
            :style="{ '--matching-height': headerHeight + 'px', '--matching-footer': footerHeight + 'px' }"
            :class="{ 'matching-layout': status !== MATCHING_TYPES.MATCHING }"
        >
            <div class="matching-list__body-search">
                <input
                    class="common-input"
                    v-model="searchText"
                    type="text"
                    :placeholder="$t('search.placeholder')"
                />
                <button class="common-btn" @click="handleSearch">
                    {{ $t('search.button') }}
                </button>
            </div>
            <button
                v-if="status === MATCHING_TYPES.PENDING"
                class="btn-mark-meeting"
                :class="{ 'active': isCheckinMark }"
                @click="handleCheckinMark"
            >
                <img v-if="isCheckinMark" src="@/assets/icons/mark-check-meeting-active.png" alt="">
                <img v-else src="@/assets/icons/mark-check-meeting.png" alt="">
                {{ isCheckinMark ? $t('actions.selectParticipants') : $t('actions.markAsDone') }}
            </button>
            <div class="matching-list__body-list">
                <div v-if="status === MATCHING_TYPES.MATCHING" class="matching-list__body-list-title">
                    {{ $t('tabs.networking') }}
                </div>
                <div class="row matching-items">
                    <div class="col-6" v-for="i in 7" :key="i">
                        <div class="matching-items__item" @click="handleDetail">
                            <BaseImage
                                src="/images/user.jpg"
                                alt="User avatar"
                                customClass="matching-items__item-avatar"
                            />
                            <div class="matching-items__item-details_layout">
                                <div class="matching-items__item-name">
                                    中井 颯人
                                </div>
                                <div class="matching-items__item-info">
                                    bravesoft株式会社
                                </div>
                                <div class="matching-items__item-info sm">
                                    eventosの営業部長をして
                                    おります。お気軽にお声 がけください！
                                </div>
                            </div>
                            <div class="matching-items__item-tags">
                                <div class="tag">#eventos</div>
                                <div class="tag">#EventDX</div>
                            </div>
                            <button
                                class="matching-items__item-btn"
                                @click.stop="handleTalk"
                            >
                                {{ $t('actions.talk') }}
                            </button>
                            <div
                                v-if="status !== MATCHING_TYPES.MATCHING"
                                :class="{ 'matching-items__item-notify': !isCheckinMark }"
                            >
                                <span v-if="!isCheckinMark">1</span>
                                <div class="matching-items__item-marking" v-else>
                                    <input
                                        type="checkbox"
                                        :checked="selectedMarks.has(i)"
                                        class=""
                                        @click.stop @change="toggleItem(i)"
                                    >
                                    <label class="checkbox-text" for="">
                                        {{ selectedMarks.has(i) ? $t('matching.done') : $t('matching.notYetDiscuss') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="isCheckinMark" ref="checkMarkFooter" class="matching-list__footer">
            <button class="common-btn cancel-gray" @click="cancelCheckMarking">
                {{ $t('common.cancel') }}
            </button>
            <button class="common-btn">
                {{ $t('actions.markCompleted') }}
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Option } from "@/components/SelectBoxComponent.vue";
import { MATCHING_TYPES } from "@/shared/constants/matching";
import { UserMatchingInfo } from "@/shared/interfaces/matching";
import { defineAsyncComponent, nextTick, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

const SelectBoxComponent = defineAsyncComponent(
    () => import("@/components/SelectBoxComponent.vue"),
);
const BaseImage = defineAsyncComponent(
    () => import("@/components/BaseImage.vue"),
);
const ProfileModal = defineAsyncComponent(
    () => import("@/components/modals/ProfileModal.vue"),
);

const { t: translate } = useI18n();
const selectHeader = ref<HTMLElement | null>(null);
const checkMarkFooter = ref<HTMLElement | null>(null);
const headerHeight = ref<number>(0);
const footerHeight = ref<number>(0);
const searchText = ref<string>("");
const status = ref<string>(MATCHING_TYPES.MATCHING);
const showProfile = ref<boolean>(false);
const isCheckinMark = ref<boolean>(false);
const selectedMarks = ref<Set<number>>(new Set());
const userInfo = ref<UserMatchingInfo>({
    id: 1,
    avatar: "/avatar.jpg",
    name: "中井 颯人",
    company: "bravesoft inc.",
    userId: "7Zw6tz3X",
    message: "こんにちは、よろしくお願いします！",
    department: "PMソリューション部",
    position: "一般 / その他",
    email: "s.suda@bravesoft.co.jp",
    phone: "03-6809-6030",
    gender: 1,
    birthday: new Date(),
    purpose:
        "「溶湯鍛造法」を基幹技術として、鋳巣の無いアルミ鋳造素材を製造することからスタートし既にこの分野では新幹線搭載機器の部材に採用されており高い信頼性を得ております。 　「溶湯鍛造法」　は溶融アルミを直圧100Mpaで押し固める方法です。この技術を応用して、鉄板の片面に溶融アルミを高圧でプレスすることにより鉄とアルミを接合する技術も保有しており既に量産化しております。 　今回の出展は、各種製品の中から問い合わせが増えている「複合材料」を展示させていただきます。 　複合材料の製法や特徴は弊社のホームページに記載しているので参照お願いいたします。また、製品案内にもその一部を記述、参考資料を掲載させていただきました。　詳しい資料はホームページからもダウンロード可能です。 　※セラミック＋アルミ複合材料の「AC-Albolon」は従来半導体後工程での採用が進んでおりましたが、ここ数年は高速回転部材に効果（性能、省エネ、小型化・・・）があると判断されいくつかのプロジェクトが国内外で進行中です。今回の展示会ではパワートレイン部材への拡販をターゲットとしております。 　※グラファイト＋アルミ複合材料の「ACMシリーズ」は熱マネジメント全般に利用可能な材料です。熱拡散性高い点に注目していただくと、ヒートスポットが生まれやすい熱源の放熱性向上（熱点を熱面に展開）可能です。",
    url: "https://x.com/home ",
    category: [
        { id: 1, name: "AI" },
        { id: 2, name: "バイオエネルギー" },
    ],
});

const statusOptions = [
    { label: translate('matching.findPartner'), value: MATCHING_TYPES.MATCHING },
    { label: translate('matching.pending'), value: MATCHING_TYPES.PENDING },
    { label: translate('matching.request'), value: MATCHING_TYPES.REQUEST },
    { label: translate('matching.matched'), value: MATCHING_TYPES.MATCHED },
    { label: translate('matching.done'), value: MATCHING_TYPES.DONE },
];

const getHeaderHeight = async () => {
    await nextTick();
    if (selectHeader.value) headerHeight.value = selectHeader.value.offsetHeight;
};

const changeStatus = (option: Option) => {
    isCheckinMark.value = false;
    status.value = option.value;
};

const handleSearch = () => {
    console.log("handleSearch");
};

const handleDetail = () => {
    console.log("handleDetail");
    showProfile.value = true;
};

const handleTalk = () => {
    console.log("handleTalk");
};

const handleCheckinMark = async () => {
    isCheckinMark.value = !isCheckinMark.value;
};

const cancelCheckMarking = async () => {
    selectedMarks.value = new Set();
    handleCheckinMark();
};

const toggleItem = (id: number) => {
    if (selectedMarks.value.has(id)) {
        selectedMarks.value.delete(id)
    } else {
        selectedMarks.value.add(id)
    }
}

watch(
    () => isCheckinMark.value,
    async (newValue) => {
        await nextTick();
        footerHeight.value = checkMarkFooter.value && newValue ? checkMarkFooter.value.offsetHeight : 0;
    },
)

onMounted(async () => {});
</script>
<style lang="scss" scoped>
.matching-list {
    &__header {
        padding: 12px 9px;
        background: #fff;
    }

    &__body {
        height: calc(100svh - (var(--matching-height) + var(--matching-footer)));
        overflow: auto;
        background: #eef2fa;

        .btn-mark-meeting {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: auto;
            margin-right: 8px;
            border: unset;
            padding: 7px;
            border-radius: 6px;
            background: var(--brand_red, #E60013);
            color: #FFF;
            font-family: Roboto;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: normal;
            &.active {
                background: #F7F9FA;
                color: var(--Ink-300, #404446);
            }
            >img {
                width: 16px;
            }
        }

        &-search {
            padding: 16px 8px;
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 12px;
        }

        &-list {
            padding: 16px 12px;

            &-title {
                color: #222;
                font-family: Roboto;
                font-size: 16px;
                font-style: normal;
                font-weight: 700;
                line-height: normal;
                padding-bottom: 11px;
            }

            .matching-items {
                --bs-gutter-x: 12px;
                --bs-gutter-y: 11px;

                &__item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    border-radius: 12px;
                    background: #fff;
                    box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08);
                    padding: 16px;

                    &-avatar {
                        width: 96px;
                        height: 96px;
                        aspect-ratio: 1/1;
                        border-radius: 48px;
                        border: 1px solid #d9d9d9;
                        background: url(<path-to-image>) lightgray 50% / cover
                            no-repeat;
                        margin-bottom: 20px;
                    }

                    &-name {
                        color: #222;
                        text-align: center;
                        font-family: Roboto;
                        font-size: 16px;
                        font-style: normal;
                        font-weight: 700;
                        line-height: normal;
                        margin-bottom: 11px;
                    }

                    &-info {
                        color: #555;
                        text-align: center;
                        font-family: Roboto;
                        font-size: 13px;
                        font-style: normal;
                        font-weight: 400;
                        line-height: normal;
                        margin-bottom: 3px;

                        &.sm {
                            color: #666;
                            font-size: 12px;
                            margin-bottom: 10px;
                        }
                    }

                    &-tags {
                        display: flex;
                        flex-direction: row;
                        justify-content: center;
                        flex-wrap: wrap;
                        gap: 2px;
                        margin-bottom: 14px;

                        .tag {
                            color: var(--brand_red, #e60013);
                            text-align: center;
                            font-family: Roboto;
                            font-size: 11px;
                            font-style: normal;
                            font-weight: 400;
                            line-height: normal;
                        }
                    }

                    &-btn {
                        width: 100%;
                        padding: 8.5px;
                        border-radius: 8px;
                        background: var(--brand_red, #e60013);
                        color: #fff;
                        text-align: center;
                        font-family: Arial;
                        font-size: 14px;
                        font-style: normal;
                        font-weight: 700;
                        line-height: normal;
                        border: none;
                    }
                }
            }
        }
        &.matching-layout {
            .matching-list__body-search {
                flex-direction: row;
                gap: 12px;
                .common-input {
                    flex: 1;
                }
                .common-btn {
                    border-radius: 12px;
                    border: 1px solid var(--brand_red, #e60013);
                    background: #fff;
                    color: var(--brand_red, #e60013);
                    padding: 11px;
                    width: 70px;
                    text-align: center;
                    font-family: Roboto;
                    font-size: 12px;
                    font-style: normal;
                    font-weight: 700;
                    line-height: normal;
                    height: 36px;
                    margin: auto;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
            }
            .col-6 {
                width: 100%;
            }
            .row.matching-items {
                --bs-gutter-y: 0;
            }
            .matching-items__item {
                flex-direction: row;
                gap: 20px;
                border-radius: 0;
                border-bottom: 0.869px solid #cdcfd0;
                &-tags,
                &-btn {
                    display: none;
                }
                &-avatar {
                    width: 60px;
                    height: 60px;
                    flex: 0 0 60px;
                    border-radius: 50%;
                    margin: 0;
                }
                &-details_layout {
                    word-break: break-word;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    > div {
                        margin-bottom: 0;
                    }
                }
                &-name {
                    font-size: 16px;
                    font-weight: 700;
                }
                &-info {
                    font-family: Roboto;
                    font-size: 10px;
                    font-style: normal;
                    font-weight: 400;
                    line-height: normal;
                    &.sm {
                        text-align: start;
                        color: #000;
                        font-family: Roboto;
                        font-size: 11px;
                        font-style: normal;
                        font-weight: 400;
                        line-height: normal;
                        display: -webkit-box;
                        word-break: break-word;
                        overflow: hidden;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                    }
                }
                &-notify {
                    width: 24px;
                    height: 24px;
                    text-align: center;
                    aspect-ratio: 1/1;
                    border-radius: var(--lv99_999, 999px);
                    background: var(--brand_red, #E60013);
                    color: #FFF;
                    font-family: Arial;
                    font-size: 18px;
                    font-style: normal;
                    font-weight: 700;
                }
                &-marking {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    &.active {
                        .checkbox-text {
                            color: #E60013;
                            font-family: Arial;
                            font-size: 8px;
                            font-style: normal;
                            font-weight: 400;
                            line-height: normal;
                        }
                    }
                    .checkbox-text {
                        color: #979C9E;
                        font-family: Arial;
                        font-size: 8px;
                        font-style: normal;
                        font-weight: 700;
                        line-height: normal;
                    }
                }
            }
        }
    }
    &__footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.70);
        padding: 25px 8px 15px 8px;
        position: fixed;
        width: 100%;
        bottom: 0;
        button {
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            vertical-align: middle;
            text-align: center;
            font-family: Arial;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: normal;
            width: 145px;
        }
    }
}
</style>
