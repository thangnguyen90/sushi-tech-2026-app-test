import { EVENTOS_CLIENT_URL, EVENT_CHAT_EVENT_ID, EVENTOS_LIVE_CHAT_CONTENT_ID, EVENTOS_PORTAL_ID } from "@/shared/constants/env"

export const LiveChatRedirect = (moduleId: string, webLinkId: string) => {
    const url = `${EVENTOS_CLIENT_URL}/uri/web_link/${EVENTOS_PORTAL_ID}/${EVENT_CHAT_EVENT_ID}/${EVENTOS_LIVE_CHAT_CONTENT_ID}/${moduleId}/${webLinkId}`;
    return url
}
