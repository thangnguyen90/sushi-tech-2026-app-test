import { CHAT_EVENT_ID, EVENTOS_CLIENT_URL, EVENTOS_PORTAL_ID } from "@/shared/constants/env"

export const LiveChatRedirect = (moduleId: string, language?: string, isMatching: boolean = false) => {
    // const url = `${EVENTOS_CLIENT_URL}/uri/web_link/${EVENTOS_PORTAL_ID}/${EVENTOS_EVENT_ID}/${moduleId}/${webLinkId}`;
    const url = `${EVENTOS_CLIENT_URL}/web/portal/${EVENTOS_PORTAL_ID}/event/${CHAT_EVENT_ID}/module/live_chat/${moduleId}?language=${language}`;
    if (isMatching) {
        return `${url}&isMatching=1`;
    }
    return url
}
