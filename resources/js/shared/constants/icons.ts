import CrowedStatusAvailableList from '@/assets/icons/crowed_status_available_list.svg'
import CrowedStatusRecentlyAvailableList from '@/assets/icons/crowed_status_recetly_available_list.svg'
import CrowedStatusBusyList from '@/assets/icons/crowed_status_busy_list.svg'
import CrowedStatusClosedList from '@/assets/icons/crowed_status_closed_list.svg'
import CrowedStatusAvailable from '@/assets/icons/crowed_status_available.svg'
import CrowedStatusRecentlyAvailable from '@/assets/icons/crowed_status_recetly_available.svg'
import CrowedStatusBusy from '@/assets/icons/crowed_status_busy.svg'
import CrowedStatusClosed from '@/assets/icons/crowed_status_closed.svg'
import ErrorCheckinIcon from '@/assets/icons/checkin-error-icon.svg';
import SuccessCheckinIcon from '@/assets/icons/checkin-success-icon.svg';

import { CROWD_STATUS } from './variables'

export const CROWED_STATUS_ICON = {
    LIST: {
        [CROWD_STATUS.AVAILABLE]: CrowedStatusAvailableList,
        [CROWD_STATUS.RELATIVE_AVAILABLE]: CrowedStatusRecentlyAvailableList,
        [CROWD_STATUS.BUSY]: CrowedStatusBusyList,
        [CROWD_STATUS.CLOSED]: CrowedStatusClosedList,
    },
    DETAILS: {
        [CROWD_STATUS.AVAILABLE]: CrowedStatusAvailable,
        [CROWD_STATUS.RELATIVE_AVAILABLE]: CrowedStatusRecentlyAvailable,
        [CROWD_STATUS.BUSY]: CrowedStatusBusy,
        [CROWD_STATUS.CLOSED]: CrowedStatusClosed,
    }
} as const

export const CHECKIN_MODAL_STATUS_ICON = {
    ERROR: ErrorCheckinIcon,
    SUCCESS: SuccessCheckinIcon
}
