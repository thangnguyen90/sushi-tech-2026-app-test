import { format, parseISO } from 'date-fns'

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
        let parsedDate: Date

        if (typeof date === 'string') {
            parsedDate = parseISO(date)
        } else {
            parsedDate = new Date(date)
        }

        return format(parsedDate, pattern)
    } catch (error) {
        console.error('formatDate error:', error)
        return ''
    }
}
