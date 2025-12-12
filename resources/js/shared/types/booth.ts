/**
 * Type definitions for Eventos Booth API Response
 */

export interface BoothItemLayout {
    key: string;
    type: string;
    setting: {
        chi: LayoutSetting;
        eng: LayoutSetting;
        jpn: LayoutSetting;
        zhtw: LayoutSetting;
    };
}

export interface LayoutSetting {
    label: string;
    enable: boolean;
    is_label: boolean;
    [key: string]: any;
}

export interface BoothItem {
    key: string;
    type: string;
    enable: boolean;
    layout: BoothItemLayout;
    // Different types have different properties
    value?: string;
    words?: string[];
    path?: string[];
    ids?: number[];
    phonetic?: string;
    adoption?: boolean;
    priority?: number;
    is_visible?: boolean;
    visible_period?: boolean;
    visible_end_date?: string;
    visible_start_date?: string;
    image?: {
        file: string;
        mime?: string;
        size?: number;
        width?: number;
        height?: number;
    };
}

export interface LiveStream {
    id: number | null;
    stream_id: string | null;
    firebase_key: string | null;
    stream_status: string | null;
    playback_video_file_id: number | null;
    playback_video_file_url: string | null;
    playback_current_time_second: number | null;
    live_start_date: string | null;
    live_end_date: string | null;
}

export interface BoothData {
    booth_id: number;
    items: BoothItem[];
    live_stream: LiveStream;
    movie_live_streams: any[];
    is_business_appointment: boolean;
    live_stream_pro: any;
    brightcove_movie_live_streams: any[];
    description?: string;
    flat_description?: string;
}

export interface PaginationData {
    current_page: number;
    data: BoothData[];
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
    list_text: string;
}

export interface BoothDetailResponse {
    code: number;
    error: any;
    data: PaginationData;
}

/**
 * Processed program detail for display
 */
export interface ProgramDetail {
    id: number;
    title: string;
    description: string;
    image: string;
    area: string;
    access: string;
    progress: string;
    guide: string;
    rainyDay: string;
    duration?: string;
    categories: number[];
    reservationCategoryIds?: { largeCategoryId: number; middleCategoryId: number };
    startTime?: string;
    endTime?: string;
    mapLink?: string;
    meals?: string;
    detailImage1?: string;
    detailText1?: string;
    detailImage2?: string;
    detailText2?: string;
    detailImage3?: string;
    detailText3?: string;
    showCommonBadge?: boolean;
    showSpecialInvitationBadge?: boolean;
    isPlenarySession?: boolean;
    isTechExperience?: boolean;
}

