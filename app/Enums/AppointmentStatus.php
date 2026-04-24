<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Applied = 'applied';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case RescheduleRequested = 'reschedule_requested';

    public static function fromModuleCode(string $moduleCode): ?self
    {
        return match ($moduleCode) {
            'BusinessAppointment' => self::Applied,
            'BusinessAppointmentApproved' => self::Approved,
            'BusinessAppointmentCancel',
            'BusinessAppointmentCanceled',
            'BusinessAppointmentCancelled' => self::Cancelled,
            'BusinessAppointmentReject',
            'BusinessAppointmentRejected' => self::Rejected,
            'BusinessAppointmentReschedule' => self::RescheduleRequested,
            default => null,
        };
    }
}
