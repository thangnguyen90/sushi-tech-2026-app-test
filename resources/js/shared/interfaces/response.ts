export interface MetaPaging {
    page: number;
    current_page?: number;
    last_page?: number;
    per_page: number;
    total?: number;
}

export interface ApiResponse<T = ResultApiResponse> {
    code?: number;
    message?: string;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data?: any;
    result: T;
}

export interface ImageData {
    file: File | string | null;
    link: string | null;
    'absolute-path': string | null;
    width: number;
    height: number;
    mime: string;
    size: number;
}

export interface ImageApiResponse {
    code?: number;
    message?: string;
    result: ImageData;
}

export interface ResultApiResponse {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data?: any;
    meta?: MetaPaging;
    image: ImageApiResponse;
    code?: number;
    message?: string;
}

export interface ErrorApiResponse<T = ResultApiResponse> {
    status: number;
    data: T;
}
