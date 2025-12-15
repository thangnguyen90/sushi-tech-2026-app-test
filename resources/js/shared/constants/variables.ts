export const LOCALE_CODE = {
    JPN: 'jpn',
    ENG: 'eng',
} as const;

export const LOCALES = [
    LOCALE_CODE.JPN,
    LOCALE_CODE.ENG,
] as const;

export const CROWD_STATUS = {
    AVAILABLE: 3,
    RELATIVE_AVAILABLE: 2,
    BUSY: 1,
    CLOSED: 0,
} as const

export const CROWED_STATUS_LABEL = {
    [CROWD_STATUS.AVAILABLE]: 'available',
    [CROWD_STATUS.RELATIVE_AVAILABLE]: 'relativeAvailable',
    [CROWD_STATUS.BUSY]: 'busy',
    [CROWD_STATUS.CLOSED]: 'closed',
} as const

export const RESERVATION_TYPES = {
    BOOKING: 1,
    RESERVATION: 2,
} as const
