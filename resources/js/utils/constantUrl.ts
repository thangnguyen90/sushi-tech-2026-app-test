import { EVENTOS_CLIENT_URL, CHAT_EVENT_ID, EVENTOS_PORTAL_ID, BUSINESS_MODULE_ID, BUSINESS_LINK_ID, EXHIBITOR_MODULE_ID, EXHIBITOR_LINK_ID, READ_QR_MODULE_ID, READ_QR_LINK_ID, HISTORY_QR_MODULE_ID, HISTORY_QR_LINK_ID } from "@/shared/constants/env"

export const LiveChatRedirect = (moduleId: string, webLinkId: string) => {
    const url = `${EVENTOS_CLIENT_URL}/uri/web_link/${EVENTOS_PORTAL_ID}/${CHAT_EVENT_ID}/${moduleId}/${webLinkId}`;
    return url
}

export const BusinessWebLink = () => {
    const url = `${EVENTOS_CLIENT_URL}/uri/united_web_link/${EVENTOS_PORTAL_ID}/${CHAT_EVENT_ID}/${BUSINESS_MODULE_ID}/${BUSINESS_LINK_ID}`;
    return url
}

export const ExhibitorWebLink = () => {
    const url = `${EVENTOS_CLIENT_URL}/uri/web_link/${EVENTOS_PORTAL_ID}/${CHAT_EVENT_ID}/${EXHIBITOR_MODULE_ID}/${EXHIBITOR_LINK_ID}`;
    return url

}
export const ReadQrWebLink = () => {
    if (READ_QR_MODULE_ID || READ_QR_LINK_ID) {
        const url = `${EVENTOS_CLIENT_URL}/uri/web_link/${EVENTOS_PORTAL_ID}/${CHAT_EVENT_ID}/${READ_QR_MODULE_ID}/${READ_QR_LINK_ID}`;
        return url;
    }
    return '';
}

export const HistoryQrWebLink = () => {
    if (HISTORY_QR_MODULE_ID || HISTORY_QR_LINK_ID) {
        const url = `${EVENTOS_CLIENT_URL}/uri/web_link/${EVENTOS_PORTAL_ID}/${CHAT_EVENT_ID}/${HISTORY_QR_MODULE_ID}/${HISTORY_QR_LINK_ID}`;
        return url;
    }
    return '';
}
