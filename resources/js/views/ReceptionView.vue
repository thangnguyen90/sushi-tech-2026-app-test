<template>
    <div class="reception">
        <div
            class="reception__body"
            :class="{
                'reception__body--scanning': viewState === 'scanning',
                'reception__body--intro': viewState === 'intro',
            }"
        >
            <section v-if="viewState === 'intro'" class="reception__intro">
                <div class="reception__intro-copy">
                    <p>この端末は</p>
                    <p class="reception__intro-highlight">
                        <span>「</span><span>{{ boothLabel }}」</span>
                    </p>
                    <p>の商談ブースの受付の端末です</p>
                </div>

                <button type="button" class="reception__camera-button" @click="openScanner">
                    <img
                        class="reception__camera-button-graphic"
                        :src="introCameraGraphic"
                        alt=""
                        width="117"
                        height="117"
                        decoding="async"
                    />
                    <span class="reception__camera-button-text">カメラを起動</span>
                </button>
            </section>

            <section
                v-else-if="viewState === 'scanning'"
                class="reception__scanner"
            >
                <button
                    type="button"
                    class="reception__scanner-switch"
                    :class="{ 'is-hidden': isErrorModalOpen }"
                    :aria-label="cameraSwitchLabel"
                    @click="toggleScannerCamera"
                >
                    <span class="reception__scanner-switch-icon" aria-hidden="true">
                        <span class="reception__scanner-switch-camera"></span>
                    </span>
                </button>

                <button
                    type="button"
                    class="reception__scanner-close"
                    :class="{ 'is-hidden': isErrorModalOpen }"
                    @click="resetToIntro"
                >
                    <span class="visually-hidden">{{ $t('common.close') }}</span>
                </button>

                <div class="reception__scanner-frame">
                    <qrcode-stream
                        class="reception__scanner-stream"
                        :key="scannerKey"
                        :constraints="scannerConstraints"
                        @camera-on="handleCameraOn"
                        @detect="handleDetect"
                        @error="handleScannerError"
                    />
                </div>

                <div v-if="isErrorModalOpen" class="reception__scanner-overlay">
                    <div
                        class="reception__scanner-popup"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="reception-error-message"
                    >
                        <p id="reception-error-message" class="reception__scanner-popup-message">
                            {{ scannerError }}
                        </p>

                        <button
                            type="button"
                            class="reception__scanner-popup-button"
                            @click="handleErrorModalVisibility(false)"
                        >
                            {{ $t("common.confirm") }}
                        </button>
                    </div>
                </div>
            </section>

            <section
                v-else-if="viewState === 'free-single' && freeVisitors[0]"
                class="reception__free-state reception__free-state--single-booth"
            >
                <div class="reception__free-booth-wrap">
                    <div class="reception__free-card reception__free-card--single">
                        <div class="reception__free-booth-badge" aria-hidden="true">
                            <span class="reception__free-booth-badge-mark">!</span>
                        </div>

                        <h1 class="reception__free-booth-title">商談ブースの利用者情報</h1>

                        <div class="reception__free-booth-visitor">
                            <p class="reception__free-booth-visitor-heading">来場者情報</p>
                            <p>お名前 ：{{ freeVisitors[0].name || "ー" }}</p>
                            <p>企業名 ：{{ freeVisitors[0].company_name || "ー" }}</p>
                        </div>

                        <button
                            type="button"
                            class="reception__free-outline-btn reception__free-outline-btn--block"
                            @click="openScanner"
                        >
                            <span>来場者を追加する</span>
                            <span class="reception__free-outline-btn-icon" aria-hidden="true">
                                <svg
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                        stroke="#e60013"
                                        stroke-width="1.5"
                                    />
                                    <path
                                        d="M12 8v8M8 12h8"
                                        stroke="#e60013"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="reception__free-outline-btn reception__free-outline-btn--block"
                        @click="cancelFreeCheckin"
                    >
                        キャンセルする
                    </button>
                </div>
            </section>

            <section
                v-else-if="viewState === 'free-pair' && freeVisitors.length === 2"
                class="reception__free-state reception__free-state--single-booth"
            >
                <div class="reception__free-booth-wrap">
                    <div class="reception__free-card reception__free-card--pair">
                        <div class="reception__free-booth-badge" aria-hidden="true">
                            <span class="reception__free-booth-badge-mark">!</span>
                        </div>

                        <h1 class="reception__free-booth-title">商談ブースの利用者情報</h1>

                        <div class="reception__free-pair-visitors">
                            <div
                                v-for="(visitor, index) in freeVisitors"
                                :key="visitor.user_uuid"
                                class="reception__free-booth-visitor-group"
                            >
                                <p class="reception__free-booth-visitor-label">
                                    来場者情報{{ index === 0 ? "①" : "②" }}
                                </p>
                                <p class="reception__free-booth-visitor-line">
                                    お名前 ：{{ visitor.name || "ー" }}
                                </p>
                                <p class="reception__free-booth-visitor-line">
                                    企業名 ：{{ visitor.company_name || "ー" }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="reception__free-pair-actions">
                        <button
                            type="button"
                            class="reception__free-primary"
                            @click="completeFreeCheckin"
                        >
                            チェックイン
                        </button>

                        <button
                            type="button"
                            class="reception__free-outline-btn reception__free-outline-btn--block"
                            @click="cancelFreeCheckin"
                        >
                            キャンセルする
                        </button>
                    </div>
                </div>
            </section>

            <section v-else-if="viewState === 'no-data' && checkinResult" class="reception__no-data">
                <div class="reception__no-data-card">
                    <div class="reception__no-data-badge" aria-hidden="true">
                        <span></span>
                    </div>

                    <h1 class="reception__no-data-title">予約した商談が存在しません</h1>

                    <div class="reception__no-data-details">
                        <p>【来場者情報】</p>
                        <p>お名前 ： {{ checkinResult.visitor_name || "ー" }}</p>
                    </div>
                </div>

                <button type="button" class="reception__top-button" @click="resetToIntro">
                    TOPに戻る
                </button>
            </section>

            <section v-else-if="viewState === 'no-approved' && checkinResult" class="reception__no-approved">
                <div class="reception__no-approved-card">
                    <div class="reception__no-approved-badge" aria-hidden="true">
                        <span>!</span>
                    </div>

                    <h1 class="reception__no-approved-title">
                        <span>承認済みの商談予約が</span>
                        <span>ありません</span>
                    </h1>

                    <div class="reception__no-approved-copy">
                        <p>「{{ boothLabel }}」で受付可能な</p>
                        <p>商談が見つかりませんでした</p>
                    </div>

                    <div class="reception__no-approved-details">
                        <p>【来場者情報】</p>
                        <p>お名前 ： {{ checkinResult.visitor_name || "ー" }}</p>
                    </div>
                </div>

                <div class="reception__no-approved-actions">
                    <button type="button" class="reception__no-approved-primary" @click="openScanner">
                        再スキャンする
                    </button>

                    <button type="button" class="reception__top-button" @click="resetToIntro">
                        TOPに戻る
                    </button>
                </div>
            </section>

            <section v-else-if="viewState === 'result' && checkinResult" class="reception__result">
                <div class="reception__result-card">
                    <div class="reception__result-badge">
                        <img :src="checkIcon" alt="" />
                    </div>

                    <h1 class="reception__result-title">予約済みの来場者です</h1>

                    <div class="reception__result-block">
                        <p>【来場者情報】</p>
                        <p>お名前 ： {{ checkinResult.visitor_name || "ー" }}</p>
                    </div>

                    <div class="reception__result-block">
                        <p>【予定されている商談】</p>
                    </div>

                    <div
                        v-for="appointment in checkinResult.appointments"
                        :key="appointment.appointment_id"
                        class="reception__appointment"
                    >
                        <p>予約時間：{{ appointment.time_label }}</p>
                        <p>商談場所：{{ appointment.location_label }}</p>
                        <p>商談相手：{{ appointment.partner_name }}</p>
                        <p>商談ID：{{ appointment.appointment_id }}</p>

                        <button
                            type="button"
                            class="reception__appointment-button"
                            :class="{ 'is-disabled': !appointment.can_checkin }"
                            :disabled="!appointment.can_checkin"
                            @click="completeCheckin(appointment)"
                        >
                            {{ appointment.action_label }}
                        </button>
                    </div>
                </div>

                <button type="button" class="reception__top-button" @click="resetToIntro">
                    TOPに戻る
                </button>
            </section>

            <section v-else-if="viewState === 'complete' && completedCheckinResult" class="reception__complete">
                <div class="reception__complete-inner">
                    <div class="reception__complete-card">
                        <div class="reception__complete-badge">
                            <img :src="checkIcon" alt="" />
                        </div>

                        <h1 class="reception__complete-title">
                            <span>商談場所の受付が</span>
                            <span>完了しました</span>
                        </h1>

                        <template v-if="completedCheckinResult.mode === 'free'">
                            <div class="reception__complete-free-body">
                                <div class="reception__complete-pair-visitors">
                                    <div class="reception__complete-visitor-group">
                                        <p class="reception__complete-visitor-label">来場者情報①</p>
                                        <p class="reception__complete-visitor-line">
                                            お名前 ：{{ completedCheckinResult.visitor_name || "ー" }}
                                        </p>
                                    </div>
                                    <div class="reception__complete-visitor-group">
                                        <p class="reception__complete-visitor-label">来場者情報②</p>
                                        <p class="reception__complete-visitor-line">
                                            お名前 ：{{ completedCheckinResult.second_user_name || "ー" }}
                                        </p>
                                    </div>
                                </div>
                                <p class="reception__complete-time">
                                    チェックイン時間：{{ formatCheckinTimeLabel(completedCheckinResult.checkin_at) }}
                                </p>
                            </div>
                        </template>

                        <div v-else class="reception__complete-default-body">
                            <div class="reception__complete-details">
                                <p class="reception__complete-visitor-label">来場者情報</p>
                                <p class="reception__complete-visitor-line">
                                    お名前 ：{{ completedCheckinResult.visitor_name || "ー" }}
                                </p>
                                <p class="reception__complete-visitor-line">
                                    商談相手：{{ completedCheckinResult.partner_name }}
                                </p>
                                <p class="reception__complete-visitor-line">
                                    商談ID ：{{ completedCheckinResult.appointment_id }}
                                </p>
                                <p class="reception__complete-visitor-line">
                                    予約時間：{{ completedCheckinResult.time_label }}
                                </p>
                            </div>
                            <p class="reception__complete-time">
                                チェックイン時間：{{ formatCheckinTimeLabel(completedCheckinResult.checkin_at) }}
                            </p>
                        </div>
                    </div>

                    <button type="button" class="reception__complete-button" @click="closeComplete">
                        {{ completeButtonLabel }}
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import introCameraGraphic from "@/assets/reception/reception-intro-camera.svg";
import checkIcon from "@/assets/reception/check-icon.svg";
import ReceptionService from "@/services/app/Reception";
import type {
    ReceptionAppointment,
    ReceptionCheckinResult,
    ReceptionCheckinCompleteResult,
    ReceptionVisitor,
} from "@/shared/interfaces/reception";
import type { DetectedBarcode } from "vue-qrcode-reader";
import type { AxiosError } from "axios";
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute } from "vue-router";

type ReceptionViewState =
    | "intro"
    | "scanning"
    | "free-single"
    | "free-pair"
    | "no-data"
    | "no-approved"
    | "result"
    | "complete";

const defaultBoothLabel = "商談エリアB（1階）";
const defaultBoothRoomId = 23;
const invalidQrApiMessage = "QRコードが不正です";
const viewState = ref<ReceptionViewState>("intro");
const scannerKey = ref<number>(0);
const scannerError = ref<string>("");
const isErrorModalOpen = ref<boolean>(false);
const activeFacingMode = ref<"user" | "environment">("environment");
const isSubmitting = ref<boolean>(false);
const isCompletingAppointment = ref<string | null>(null);
const scannedUserUuid = ref<string>("");
const checkinResult = ref<ReceptionCheckinResult | null>(null);
const completedCheckinResult = ref<ReceptionCheckinCompleteResult | null>(null);
const freeVisitors = ref<ReceptionVisitor[]>([]);
const boothLabel = ref<string>(defaultBoothLabel);
const boothIsFree = ref<boolean>(false);
const completeReturnState = ref<"intro" | "result">("result");
const { t } = useI18n();
const route = useRoute();
const boothRoomId = computed<number>(() => {
    const roomId = route.query.room_id;
    const firstRoomId = Array.isArray(roomId) ? roomId[0] : roomId;
    const parsedRoomId = Number(firstRoomId);

    return Number.isInteger(parsedRoomId) && parsedRoomId > 0 ? parsedRoomId : defaultBoothRoomId;
});
const scannerConstraints = computed(() => ({
    facingMode: activeFacingMode.value,
}));
const cameraSwitchLabel = computed<string>(() => {
    return activeFacingMode.value === "environment"
        ? "フロントカメラに切り替える"
        : "背面カメラに切り替える";
});
const completeButtonLabel = computed<string>(() => {
    return completeReturnState.value === "intro" ? "TOPに戻る" : "戻る";
});

const formatCheckinTimeLabel = (raw: string | null | undefined): string => {
    if (raw === null || raw === undefined || raw === "") {
        return "ー";
    }

    const [datePart, timePart] = raw.trim().split(" ");
    if (datePart === undefined || datePart === "") {
        return raw;
    }

    const ymd = datePart.replaceAll("/", "-").split("-");
    if (ymd.length !== 3 || ymd[0] === undefined || ymd[1] === undefined || ymd[2] === undefined) {
        return raw;
    }

    let hours = "00";
    let minutes = "00";
    if (timePart !== undefined && timePart !== "") {
        const parts = timePart.split(":");
        hours = parts[0] ?? "00";
        minutes = parts[1] ?? "00";
    }

    return `${ymd[0]}/${ymd[1]}/${ymd[2]} ${hours}:${minutes}`;
};

const loadBoothRoom = async (): Promise<void> => {
    try {
        const room = await ReceptionService.getRoom(boothRoomId.value);

        boothLabel.value = room.room_name?.trim() || defaultBoothLabel;
        boothIsFree.value = room.is_free;
    } catch {
        boothLabel.value = defaultBoothLabel;
        boothIsFree.value = false;
    }
};

watch(boothRoomId, () => {
    void loadBoothRoom();
}, { immediate: true });

const openScanner = (): void => {
    closeErrorModal();
    completedCheckinResult.value = null;
    activeFacingMode.value = "environment";
    viewState.value = "scanning";
    scannerKey.value += 1;
};

const resetToIntro = (): void => {
    closeErrorModal();
    isSubmitting.value = false;
    isCompletingAppointment.value = null;
    activeFacingMode.value = "environment";
    scannedUserUuid.value = "";
    checkinResult.value = null;
    completedCheckinResult.value = null;
    freeVisitors.value = [];
    completeReturnState.value = "result";
    viewState.value = "intro";
};

const backToResult = (): void => {
    completedCheckinResult.value = null;
    isCompletingAppointment.value = null;
    viewState.value = "result";
};

const closeComplete = (): void => {
    if (completeReturnState.value === "intro") {
        resetToIntro();

        return;
    }

    backToResult();
};

const handleCameraOn = (): void => {
    closeErrorModal();
};

const resolveScannerErrorMessage = (error: unknown): string => {
    const errorName = error instanceof Error ? error.name : "";
    const translatedMessage = errorName ? t(`errorMessages.${errorName}`) : "";

    if (errorName && translatedMessage !== `errorMessages.${errorName}`) {
        return translatedMessage;
    }

    return t("errorMessages.qrMessage");
};

const handleScannerError = (error: unknown): void => {
    openErrorModal(resolveScannerErrorMessage(error));
};

const extractApiError = (
    error: unknown,
): { status: number | null; message: string | null; result: Record<string, unknown> | null } => {
    const axiosError = error as AxiosError<{ message?: string; result?: Record<string, unknown> }>;
    const responseData = axiosError?.response?.data;
    const result = responseData?.result ?? null;
    const messageFromResult = typeof result?.message === "string" ? result.message : null;
    const messageFromRoot = typeof responseData?.message === "string" ? responseData.message : null;

    return {
        status: axiosError?.response?.status ?? null,
        message: messageFromResult ?? messageFromRoot,
        result,
    };
};

const createFallbackCheckinResult = (visitorName: string | null): ReceptionCheckinResult => {
    return {
        visitor_name: visitorName,
        appointments: [],
    };
};

const cancelFreeCheckin = (): void => {
    resetToIntro();
};

const closeErrorModal = (): void => {
    scannerError.value = "";
    isErrorModalOpen.value = false;
};

const resolvePresentationErrorMessage = (message: string): string => {
    return message === invalidQrApiMessage
        ? t("errorMessages.invalidQrCode")
        : message;
};

const openErrorModal = (message: string): void => {
    scannerError.value = resolvePresentationErrorMessage(message);
    isErrorModalOpen.value = true;
};

const handleErrorModalVisibility = (value: boolean): void => {
    isErrorModalOpen.value = value;

    if (! value) {
        scannerError.value = "";
    }
};

const handleFreeDetect = async (qrCode: string): Promise<void> => {
    const visitor = await ReceptionService.getUser(qrCode);

    if (freeVisitors.value.some((item) => item.user_uuid === visitor.user_uuid)) {
        openErrorModal("同じ来場者は追加できません");

        return;
    }

    freeVisitors.value = [...freeVisitors.value, visitor].slice(0, 2);
    viewState.value = freeVisitors.value.length >= 2 ? "free-pair" : "free-single";
};

const toggleScannerCamera = (): void => {
    if (viewState.value !== "scanning" || isSubmitting.value || isErrorModalOpen.value) {
        return;
    }

    activeFacingMode.value = activeFacingMode.value === "environment" ? "user" : "environment";
};

const handleDetect = async (detectedCodes: DetectedBarcode[]): Promise<void> => {
    if (viewState.value !== "scanning" || isSubmitting.value || isErrorModalOpen.value || detectedCodes.length === 0) {
        return;
    }

    const qrCode = detectedCodes[0]?.rawValue?.trim();

    if (! qrCode) {
        openErrorModal(t("errorMessages.invalidQrCode"));

        return;
    }

    isSubmitting.value = true;
    closeErrorModal();

    try {
        if (boothIsFree.value) {
            await handleFreeDetect(qrCode);

            return;
        }

        scannedUserUuid.value = qrCode;
        const result = await ReceptionService.checkin(boothRoomId.value, qrCode);

        checkinResult.value = result;
        viewState.value = result.appointments.length === 0 ? "no-data" : "result";
    } catch (error) {
        const apiError = extractApiError(error);

        if (apiError.status === 404 && apiError.message === "商談予約をしていないユーザです") {
            const visitorName = typeof apiError.result?.visitor_name === "string"
                ? apiError.result.visitor_name
                : null;

            checkinResult.value = createFallbackCheckinResult(visitorName);
            viewState.value = "no-data";

            return;
        }

        if (apiError.message) {
            openErrorModal(apiError.message);
        } else {
            openErrorModal(resolveScannerErrorMessage(error));
        }
    } finally {
        isSubmitting.value = false;
    }
};

const completeFreeCheckin = async (): Promise<void> => {
    if (freeVisitors.value.length !== 2 || isCompletingAppointment.value) {
        return;
    }

    isCompletingAppointment.value = "free";

    try {
        const apiResult = await ReceptionService.completeFreeCheckin(
            boothRoomId.value,
            freeVisitors.value[0].user_uuid,
            freeVisitors.value[1].user_uuid,
        );

        completedCheckinResult.value = {
            mode: "free",
            visitor_name: freeVisitors.value[0].name ?? null,
            second_user_name: freeVisitors.value[1].name ?? null,
            partner_name: "",
            appointment_id: "-",
            time_label: "",
            room_label: boothLabel.value,
            checkin_at: apiResult.checkin_at,
        };
        freeVisitors.value = [];
        completeReturnState.value = "intro";
        viewState.value = "complete";
    } catch (error) {
        const apiError = extractApiError(error);

        openErrorModal(apiError.message ?? resolveScannerErrorMessage(error));
    } finally {
        isCompletingAppointment.value = null;
    }
};

const completeCheckin = async (appointment: ReceptionAppointment): Promise<void> => {
    if (! appointment.can_checkin) {
        return;
    }

    if (isCompletingAppointment.value) {
        return;
    }

    isCompletingAppointment.value = appointment.appointment_id;

    try {
        const apiResult = await ReceptionService.completeCheckin(appointment.appointment_id, scannedUserUuid.value);

        completedCheckinResult.value = {
            mode: "default",
            visitor_name: checkinResult.value?.visitor_name ?? null,
            partner_name: appointment.partner_name,
            appointment_id: appointment.appointment_id,
            time_label: appointment.time_label,
            checkin_at: apiResult.checkin_at,
        };
        completeReturnState.value = "result";
        viewState.value = "complete";
    } catch (error) {
        const apiError = extractApiError(error);

        if (apiError.status === 409 && scannedUserUuid.value) {
            const refreshedResult = await ReceptionService.checkin(boothRoomId.value, scannedUserUuid.value);

            checkinResult.value = refreshedResult;
            viewState.value = refreshedResult.appointments.length === 0 ? "no-data" : "result";

            return;
        }

        openErrorModal(apiError.message ?? resolveScannerErrorMessage(error));
    } finally {
        isCompletingAppointment.value = null;
    }
};
</script>

<style lang="scss" scoped>
.reception {
    min-height: 100svh;
    display: flex;
    flex-direction: column;
    background: #fff;
    color: #000;

    &__body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 40px 20px;

        &--intro {
            padding: 56px 10px;
        }

        &--scanning {
            padding: 0;
            height: 100svh;
            min-height: 100svh;
        }
    }

    &__intro {
        display: flex;
        flex: 1;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 48px;
    }

    &__intro-copy {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 32px;
        text-align: center;
        color: #000;

        > p {
            margin-bottom: 0;
        }
    }

    &__intro-highlight {
        margin: 0;
        color: #e60013;
    }

    &__camera-button {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        border: 0;
        background: transparent;
        color: #000;
        padding: 0;
        cursor: pointer;

        &:focus-visible {
            outline: 3px solid #e60013;
            outline-offset: 6px;
            border-radius: 8px;
        }

        &-graphic {
            display: block;
            width: 117px;
            height: 117px;
            flex-shrink: 0;
        }

        &-text {
            font-family: "Inter", "Noto Sans JP", sans-serif;
            font-size: 16px;
            font-weight: 700;
            line-height: 32px;
            text-align: center;
        }
    }

    &__scanner {
        position: relative;
        flex: 1 1 auto;
        display: flex;
        width: 100%;
        height: 100svh;
        min-height: 100svh;
        background: #000;
        overflow: hidden;
    }

    &__scanner-overlay {
        position: absolute;
        inset: 0;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: transparent;
    }

    &__scanner-popup {
        width: min(100%, 276px);
        display: flex;
        flex-direction: column;
        gap: 18px;
        padding: 22px 18px 18px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 20px 48px rgba(0, 0, 0, 0.32);
    }

    &__scanner-popup-message {
        margin-bottom: 0;
        color: #161717;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 24px;
        text-align: center;
        white-space: pre-line;
    }

    &__scanner-popup-button {
        width: 100%;
        min-height: 40px;
        border: 0;
        border-radius: 6px;
        background: #e60013;
        color: #fff;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 14px;
        font-weight: 700;
        line-height: 20px;
    }

    &__scanner-frame {
        position: absolute;
        inset: 0;
        min-height: 0;
        overflow: hidden;
        background: #0f0f0f;
    }

    &__scanner-stream {
        display: block;
        width: 100%;
        height: 100%;

        :deep(video),
        :deep(canvas),
        :deep(#qrcode-stream-pause-frame),
        :deep(#qrcode-stream-tracking-layer) {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    }

    &__scanner-switch {
        position: absolute;
        top: max(18px, env(safe-area-inset-top));
        left: 16px;
        z-index: 2;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: rgba(182, 182, 182, 0.84);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
        transition: opacity 0.18s ease, visibility 0.18s ease;

        &.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
    }

    &__scanner-switch-icon {
        position: relative;
        width: 24px;
        height: 24px;
    }

    &__scanner-switch-camera {
        position: absolute;
        inset: 4px 3px 6px;
        border: 2px solid #161717;
        border-radius: 5px;

        &::before {
            content: "";
            position: absolute;
            top: -5px;
            left: 50%;
            width: 10px;
            height: 4px;
            border-radius: 999px;
            background: #161717;
            transform: translateX(-50%);
        }

        &::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 24px;
            height: 24px;
            background:
                linear-gradient(45deg, transparent 44%, #161717 45%, #161717 56%, transparent 57%),
                linear-gradient(-45deg, transparent 44%, #161717 45%, #161717 56%, transparent 57%);
            transform: translate(-50%, -50%);
        }
    }

    &__scanner-close {
        position: absolute;
        top: max(18px, env(safe-area-inset-top));
        right: 16px;
        z-index: 2;
        width: 48px;
        height: 48px;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.88);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
        transition: opacity 0.18s ease, visibility 0.18s ease;

        &.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        &::before,
        &::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 22px;
            height: 2.5px;
            border-radius: 999px;
            background: #161717;
        }

        &::before {
            transform: translate(-50%, -50%) rotate(45deg);
        }

        &::after {
            transform: translate(-50%, -50%) rotate(-45deg);
        }
    }

    &__no-data,
    &__no-approved,
    &__free-state,
    &__result {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 20px;
        justify-content: center;
    }

    &__no-data {
        gap: 36px;
    }

    &__no-approved {
        gap: 28px;
        justify-content: center;
    }

    &__free-state {
        gap: 24px;
        justify-content: center;

        &--single-booth {
            gap: 0;
        }
    }

    &__free-booth-wrap {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 48px;
        width: 100%;
    }

    &__no-data-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 24px;
        padding: 48px 24px 42px;
        border: 8px solid #e60013;
        border-radius: 12px;
        background: rgba(230, 0, 19, 0.2);
    }

    &__no-data-badge {
        position: absolute;
        top: -27px;
        left: 50%;
        transform: translateX(-50%);
        width: 54px;
        height: 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #e60013;

        span,
        span::before,
        span::after {
            display: block;
        }

        span {
            position: relative;
            width: 24px;
            height: 24px;
        }

        span::before,
        span::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 22px;
            height: 4px;
            border-radius: 999px;
            background: #fff;
        }

        span::before {
            transform: translate(-50%, -50%) rotate(45deg);
        }

        span::after {
            transform: translate(-50%, -50%) rotate(-45deg);
        }
    }

    &__no-data-title {
        margin-bottom: 0;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 32px;
        text-align: center;
    }

    &__no-data-details {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 32px;

        > p {
            margin-bottom: 0;
        }
    }

    &__no-approved-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 44px 24px 28px;
        border: 6px solid #f1dd53;
        border-radius: 12px;
        background: #fff9cf;
        box-shadow: 0 10px 24px rgba(173, 145, 0, 0.12);
    }

    &__no-approved-badge {
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 3px solid #f1dd53;
        border-radius: 999px;
        background: #fff;
        color: #d0b300;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 28px;
        font-weight: 800;
        line-height: 1;
    }

    &__no-approved-title {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-bottom: 0;
        color: #161717;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 32px;
        text-align: center;
    }

    &__no-approved-copy {
        display: flex;
        flex-direction: column;
        gap: 2px;
        color: #3d3d3d;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 15px;
        font-weight: 700;
        line-height: 24px;
        text-align: center;

        > p {
            margin-bottom: 0;
        }
    }

    &__no-approved-details {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 14px 16px;
        border: 2px solid rgba(230, 0, 19, 0.22);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.72);
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 28px;

        > p {
            margin-bottom: 0;
        }
    }

    &__no-approved-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    &__no-approved-primary {
        width: 100%;
        min-height: 48px;
        border: 0;
        border-radius: 12px;
        background: #e60013;
        color: #fff;
        font-family: "Roboto", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: normal;
    }

    &__free-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 18px;
        padding: 52px 24px 26px;
        border-radius: 12px;

        &--single,
        &--pair {
            align-items: center;
            gap: 48px;
            padding: 48px 24px;
            border: 9px solid #f5e900;
            border-radius: 12px;
            background: rgba(245, 233, 0, 0.2);
            box-shadow: none;
        }
    }

    &__free-strip {
        margin: -44px -24px -2px;
        height: 54px;
        overflow: hidden;
        border-radius: 6px 6px 18px 18px;

        img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }
    }

    &__free-booth-badge {
        position: absolute;
        top: -22px;
        left: calc(50% - 22px);
        z-index: 1;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #f5e900;
    }

    &__free-booth-badge-mark {
        color: #fff;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
    }

    &__free-booth-title {
        margin: 0;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 32px;
        text-align: center;
    }

    &__free-booth-visitor {
        display: flex;
        flex-direction: column;
        gap: 0;
        width: 100%;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 32px;
        text-align: left;

        > p {
            margin-bottom: 0;
        }
    }

    &__free-booth-visitor-heading {
        margin-bottom: 0;
    }

    &__free-pair-visitors {
        display: flex;
        flex-direction: column;
        gap: 16px;
        width: 100%;
    }

    &__free-booth-visitor-group {
        display: flex;
        flex-direction: column;
        gap: 0;
        width: 100%;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 32px;
        text-align: left;

        > p {
            margin-bottom: 0;
        }
    }

    &__free-booth-visitor-label {
        margin-bottom: 0;
    }

    &__free-booth-visitor-line {
        margin-bottom: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &__free-pair-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }

    &__free-outline-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-sizing: border-box;
        min-height: 48px;
        padding: 11px 56px;
        border: 1px solid #e60013;
        border-radius: 12px;
        background: #fff;
        color: #e60013;
        font-family: "Roboto", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: normal;
        white-space: nowrap;
        cursor: pointer;

        &--block {
            width: 100%;
        }
    }

    &__free-outline-btn-icon {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
    }

    &__free-primary {
        width: 100%;
        min-height: 48px;
        border: 0;
        border-radius: 12px;
        background: #e60013;
        color: #fff;
        font-family: "Roboto", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: normal;
    }

    &__result-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 48px 24px 24px;
        border: 9px solid #5bd182;
        border-radius: 12px;
        background: rgba(91, 209, 130, 0.2);
    }

    &__result-badge {
        position: absolute;
        top: -23px;
        left: 50%;
        transform: translateX(-50%);
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #5bd182;

        img {
            width: 24px;
            height: 24px;
        }
    }

    &__result-title {
        margin-bottom: 0;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 32px;
        text-align: center;
    }

    &__result-block,
    &__appointment {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 32px;

        > p {
            margin-bottom: 0;
        }
    }

    &__appointment {
        gap: 0;
    }

    &__appointment-button {
        width: 100%;
        max-width: 289px;
        height: 36px;
        margin: 12px auto 0;
        border: 0;
        border-radius: 6px;
        background: #e60013;
        color: #fff;
        font-family: "Roboto", "Noto Sans JP", sans-serif;
        font-size: 13px;
        font-weight: 700;
        line-height: normal;
        opacity: 1;

        &:disabled,
        &.is-disabled {
            background: #cdcfd0;
            color: #fff;
            cursor: not-allowed;
            box-shadow: none;
        }
    }

    &__top-button {
        width: 100%;
        min-height: 48px;
        border: 1px solid #e60013;
        border-radius: 12px;
        background: #fff;
        color: #e60013;
        font-family: "Roboto", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: normal;
    }

    &__complete {
        display: flex;
        flex: 1;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 56px 20px;
    }

    &__complete-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 48px;
        width: 100%;
    }

    &__complete-card {
        position: relative;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 24px;
        padding: 48px 24px;
        border: 9px solid #2c88ff;
        border-radius: 12px;
        background: rgba(44, 136, 255, 0.2);
    }

    &__complete-free-body,
    &__complete-default-body {
        display: flex;
        flex-direction: column;
        gap: 24px;
        width: 100%;
    }

    &__complete-pair-visitors {
        display: flex;
        flex-direction: column;
        gap: 16px;
        width: 100%;
    }

    &__complete-visitor-group {
        display: flex;
        flex-direction: column;
        gap: 0;
        width: 100%;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 32px;
        text-align: left;

        > p {
            margin-bottom: 0;
        }
    }

    &__complete-visitor-label {
        margin-bottom: 0;
    }

    &__complete-visitor-line {
        margin-bottom: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &__complete-time {
        margin-bottom: 0;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 13px;
        font-weight: 700;
        line-height: 32px;
        text-align: left;
    }

    &__complete-badge {
        position: absolute;
        top: -23px;
        left: 50%;
        transform: translateX(-50%);
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #2c88ff;

        img {
            width: 24px;
            height: 24px;
        }
    }

    &__complete-title {
        display: flex;
        flex-direction: column;
        gap: 0;
        margin-bottom: 0;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 32px;
        text-align: center;
    }

    &__complete-details {
        display: flex;
        flex-direction: column;
        gap: 0;
        width: 100%;
        color: #000;
        font-family: "Inter", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 32px;
        text-align: left;

        > p {
            margin-bottom: 0;
        }
    }

    &__complete-button {
        width: min(100%, 223px);
        min-height: 42px;
        border: 0;
        border-radius: 12px;
        background: #e60013;
        color: #fff;
        font-family: "Roboto", "Noto Sans JP", sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: normal;
    }
}
</style>
