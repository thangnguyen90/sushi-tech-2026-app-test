import { addMinutes, format, isValid, parse, parseISO } from 'date-fns'

/**
 * Format date
 * @param date - String ISO, Date object, or timestamp
 * @param pattern - Format pattern date-fns (default: 'yyyy-MM-dd HH:mm')
 */
export function formatDate(
    date: string | number | Date,
    pattern: string = 'yyyy-MM-dd HH:mm'
): string {
    try {
        const parsedDate = parseDateValue(date)

        return format(parsedDate, pattern)
    } catch (error) {
        console.error('formatDate error:', error)
        return ''
    }
}

export function formatAppointmentTimeRange(
    date: string | number | Date,
    durationInMinutes: number = 30
): string {
    try {
        const startDate = parseDateValue(date)
        const endDate = addMinutes(startDate, durationInMinutes)

        return `${format(startDate, 'yyyy/MM/dd HH:mm')} ~ ${format(endDate, 'HH:mm')}`
    } catch (error) {
        console.error('formatAppointmentTimeRange error:', error)
        return ''
    }
}

function parseDateValue(date: string | number | Date): Date {
    if (typeof date !== 'string') {
        return new Date(date)
    }

    const normalizedDate = date.includes(' ') ? date.replace(' ', 'T') : date
    const parsedIsoDate = parseISO(normalizedDate)

    if (isValid(parsedIsoDate)) {
        return parsedIsoDate
    }

    const supportedPatterns = [
        'yyyy-MM-dd HH:mm:ss',
        'yyyy-MM-dd HH:mm',
        'yyyy/MM/dd HH:mm:ss',
        'yyyy/MM/dd HH:mm',
    ]

    for (const pattern of supportedPatterns) {
        const parsedDate = parse(date, pattern, new Date())

        if (isValid(parsedDate)) {
            return parsedDate
        }
    }

    const fallbackDate = new Date(date)

    if (isValid(fallbackDate)) {
        return fallbackDate
    }

    throw new Error(`Invalid date value: ${date}`)
}
