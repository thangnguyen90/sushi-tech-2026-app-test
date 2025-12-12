import { RouteGuard } from '@/shared/interfaces';
import { LOCALE_TYPE } from '@/types';
import { changeLanguage } from '@/utils/commonFunction';

export default async function language({ to, next, store }: RouteGuard): Promise<void> {
    const lang = to?.query?.language ? to?.query?.language as LOCALE_TYPE : store.languageCode as LOCALE_TYPE;
    if (lang) {
        changeLanguage(lang);
        store.setlanguageCode(lang);
    }
    return next();
}
