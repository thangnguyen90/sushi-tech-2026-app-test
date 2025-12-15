// import { useAuthStore } from "@/stores/auth";
import { NavigationGuardNext, RouteLocationNormalizedGeneric, RouteLocationNormalizedLoadedGeneric } from "vue-router";

export interface RouterMeta {
    middleware?: string[];
    title?: string;
}

export interface RouteGuard {
    to: RouteLocationNormalizedGeneric;
    from: RouteLocationNormalizedLoadedGeneric;
    next: NavigationGuardNext;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    store: ReturnType<any>;
}
