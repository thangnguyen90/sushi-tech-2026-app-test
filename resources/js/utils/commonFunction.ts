import i18n from '@/i18n'
import { LOCALE_CODE } from '@/shared/constants/variables'
import { LOCALE_TYPE } from '@/types'
import { setLocale } from '@vee-validate/i18n'
import { ref } from 'vue'

const language = ref<LOCALE_TYPE>(localStorage.getItem('languageCode') as LOCALE_TYPE ?? LOCALE_CODE.JPN)

export function getLanguage() {
    localStorage.setItem('languageCode', language.value)
    setLocale(language.value)
    return language.value
}

export function changeLanguage(lang: LOCALE_TYPE) {
    language.value = lang ? lang : LOCALE_CODE.JPN
    i18n.global.locale.value = language.value
    setLocale(language.value)
}
