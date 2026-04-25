import API from './API';
import { formatAppointmentTimeRange } from '@/utils/useDate';
import type {
    AppointmentCheckinApiResult,
    FreeReceptionCheckinApiResult,
    ReceptionAppointment,
    ReceptionAppointmentStatusTone,
    ReceptionAppointmentsApiItem,
    ReceptionAppointmentsApiResult,
    ReceptionCheckinResult,
    ReceptionRoomApiResult,
    ReceptionVisitor,
} from '@/shared/interfaces/reception';
const appointmentStatusLabels = {
    bothPending: '未チェックイン',
    selfCheckedIn: 'あなたはチェックイン済みです',
    partnerCheckedIn: 'お相手がチェックイン済みです',
    bothCheckedIn: 'チェックイン完了',
} as const;
const appointmentStatusTones = {
    bothPending: 'both-pending',
    selfCheckedIn: 'self-checked-in',
    partnerCheckedIn: 'partner-checked-in',
    bothCheckedIn: 'both-checked-in',
} as const satisfies Record<string, ReceptionAppointmentStatusTone>;

export default {
    async getRoom(roomId: number): Promise<ReceptionRoomApiResult> {
        const response = await API.get(`/v1/rooms/${roomId}`);

        return response.data.result as ReceptionRoomApiResult;
    },

    async getUser(userUuid: string): Promise<ReceptionVisitor> {
        const response = await API.get(`/v1/reception/users/${userUuid}`);

        return response.data.result as ReceptionVisitor;
    },

    async checkin(roomId: number, userUuid: string): Promise<ReceptionCheckinResult> {
        const response = await API.get(`/v1/rooms/${roomId}/appointments`, {
            params: {
                user_uuid: userUuid,
            },
        });

        return this.mapAppointmentsResponse(response.data.result as ReceptionAppointmentsApiResult, roomId, userUuid);
    },

    async completeCheckin(appointmentId: string, userUuid: string): Promise<AppointmentCheckinApiResult> {
        const response = await API.post(`/v1/appointments/${appointmentId}/checkin`, {
            user_uuid: userUuid,
        });

        return response.data.result as AppointmentCheckinApiResult;
    },

    async completeFreeCheckin(
        roomId: number,
        firstUserUuid: string,
        secondUserUuid: string,
    ): Promise<FreeReceptionCheckinApiResult> {
        const response = await API.post(`/v1/rooms/${roomId}/free-checkin`, {
            first_user_uuid: firstUserUuid,
            second_user_uuid: secondUserUuid,
        });

        return response.data.result as FreeReceptionCheckinApiResult;
    },

    mapAppointmentsResponse(
        result: ReceptionAppointmentsApiResult,
        boothRoomId: number,
        currentUserUuid: string,
    ): ReceptionCheckinResult {
        return {
            visitor_name: result.visitor_name,
            visitor: result.visitor ?? null,
            appointments: (result.appointments ?? []).map((appointment) =>
                this.mapAppointment(appointment, boothRoomId, currentUserUuid)
            ),
        };
    },

    mapAppointment(
        appointment: ReceptionAppointmentsApiItem,
        boothRoomId: number,
        currentUserUuid: string,
    ): ReceptionAppointment {
        const hasRoomAssignment = appointment.room_id !== null;
        const isCurrentBoothAppointment = appointment.room_id === boothRoomId;

        return {
            appointment_id: String(appointment.appointment_id ?? ''),
            time_label: this.resolveTimeLabel(appointment.schedule_time),
            location_label: appointment.room_id === null
                ? '商談場所の予約がありません。'
                : (appointment.room_name || ''),
            room_id: appointment.room_id,
            partner_name: appointment.partner_name ?? 'ー',
            can_checkin: hasRoomAssignment && isCurrentBoothAppointment,
            action_label: this.resolveActionLabel(appointment, boothRoomId),
            ...this.resolveStatus(appointment, currentUserUuid),
        };
    },

    resolveStatus(
        appointment: ReceptionAppointmentsApiItem,
        currentUserUuid: string,
    ): Pick<ReceptionAppointment, 'status_label' | 'status_tone'> {
        if (! appointment.checkin_status) {
            return {
                status_label: appointmentStatusLabels.bothPending,
                status_tone: appointmentStatusTones.bothPending,
            };
        }

        if (appointment.checkin_status === 'second_checkin') {
            return {
                status_label: appointmentStatusLabels.bothCheckedIn,
                status_tone: appointmentStatusTones.bothCheckedIn,
            };
        }

        if (appointment.user_uuid === currentUserUuid) {
            return {
                status_label: appointmentStatusLabels.selfCheckedIn,
                status_tone: appointmentStatusTones.selfCheckedIn,
            };
        }

        return {
            status_label: appointmentStatusLabels.partnerCheckedIn,
            status_tone: appointmentStatusTones.partnerCheckedIn,
        };
    },

    resolveActionLabel(appointment: ReceptionAppointmentsApiItem, boothRoomId: number): string {
        if (appointment.room_id !== boothRoomId) {
            return '別の商談場所が予約されています';
        }

        return 'この商談をチェックインする';
    },

    resolveTimeLabel(scheduleTime: string | null): string {
        if (! scheduleTime) {
            return '未設定';
        }

        return formatAppointmentTimeRange(scheduleTime) || scheduleTime;
    },
}
