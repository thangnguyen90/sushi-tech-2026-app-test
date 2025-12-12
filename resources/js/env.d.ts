// resources/js/env.d.ts
import type { I18n } from "vue-i18n";

declare module "@vue/runtime-core" {
    interface ComponentCustomProperties {
        $t: I18n["t"];
    }
}
