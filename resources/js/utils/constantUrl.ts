import { EVENTOS_CLIENT_URL, EVENTOS_EVENT_ID, EVENTOS_LIVE_CHAT_CONTENT_ID, EVENTOS_PORTAL_ID } from "@/shared/constants/env"
import { LOCALE_CODE } from '@/shared/constants/variables';

export const LiveChatRedirect = (isMatching: boolean = false, language: string = LOCALE_CODE.JPN) => {
    const url = `${EVENTOS_CLIENT_URL}/web/portal/${EVENTOS_PORTAL_ID}/event/${EVENTOS_EVENT_ID}/module/live_chat/${EVENTOS_LIVE_CHAT_CONTENT_ID}?language=${language}`;

    if (isMatching) {
        return url+'&isMatching=1';
    }
    return url
}
