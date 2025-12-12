import { createI18n } from "vue-i18n";

import eng from "@/locales/eng.json";
import jpn from "@/locales/jpn.json";

const i18n = createI18n({
    legacy: false,
    globalInjection: true,

    fallbackLocale: "jpn",
    messages: { jpn, eng },
});

export default i18n;
