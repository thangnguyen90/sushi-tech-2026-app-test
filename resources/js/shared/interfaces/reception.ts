export interface ReceptionVisitor {
    user_uuid: string;
    user_id: number | null;
    name: string | null;
    email: string | null;
    company_name: string | null;
}

export interface ReceptionRoomApiResult {
    room_id: number;
    is_free: boolean;
    room_name: string | null;
}

export interface ReceptionAppointment {
    appointment_id: string;
    time_label: string;
    location_label: string;
    room_id: number | null;
    partner_name: string;
    can_checkin: boolean;
    action_label: string;
}

export interface ReceptionCheckinResult {
    visitor_name: string | null;
    visitor?: ReceptionVisitor | null;
    appointments: ReceptionAppointment[];
}

export interface ReceptionCheckinCompleteResult {
    mode?: 'default' | 'free';
    visitor_name: string | null;
    partner_name: string;
    second_user_name?: string | null;
    appointment_id: string;
    time_label: string;
    room_label?: string | null;
    checkin_at?: string | null;
}

export interface ReceptionAppointmentsApiItem {
    appointment_id: string | number | null;
    partner_name: string | null;
    schedule_time: string | null;
    room_id: number | null;
    room_name: string;
}

export interface ReceptionAppointmentsApiResult {
    visitor_name: string | null;
    visitor?: ReceptionVisitor | null;
    appointments: ReceptionAppointmentsApiItem[];
}

export interface AppointmentCheckinApiResult {
    appointment_id: string | number;
    checkin_status: 'first_checkin' | 'second_checkin';
    user_uuid: string;
    checkin_at: string | null;
    first_checkin_at: string | null;
    second_checkin_at: string | null;
}

export interface FreeReceptionCheckinApiResult {
    room_id: number;
    checkin_status: 'second_checkin';
    checkin_at: string | null;
    first_checkin_at: string | null;
    second_checkin_at: string | null;
    user_uuid: string;
    applicant_user_id: number | null;
    applicant_user_uuid: string | null;
    recipient_user_id: number | null;
    recipient_user_uuid: string | null;
}
