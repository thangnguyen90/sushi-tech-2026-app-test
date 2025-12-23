export interface UserMatchingInfo {
    id: number;
    userId: string;
    name: string;
    email: string;
    phone: string;
    avatar: string;
    message: string;
    department: string;
    position: string;
    company: string;
    gender: number;
    birthday: string | Date;
    url?: string;
    purpose?: string;
    category?: HashTag[];
    created_at?: string;
    updated_at?: string;
    deleted_at?: string;
}

export interface HashTag {
    id: number;
    name: string;
}
