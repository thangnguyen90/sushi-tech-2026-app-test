// import auth from "@/middlewares/auth";
// import language from "@/middlewares/language";
import { RouteGuard, RouterMeta } from "@/shared/interfaces/index";
import { ROUTE } from "@/shared/constants/routing";
import { useAuthStore } from "@/stores/AuthStore";
import { createRouter, createWebHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";
import i18n from "@/i18n";
import language from "@/middlewares/language";
import auth from "@/middlewares/auth";

const routes: RouteRecordRaw[] = [
    {
        path: ROUTE.PATH.HOME,
        children: [
            {
                path: ROUTE.PATH.HOME,
                component: () => import("@/views/HomeView.vue"),
                meta: {
                    title: ROUTE.TITLE.HOME,
                }
            },
            {
                path: ROUTE.PATH.MATCHING_LIST,
                component: () => import("@/views/MatchingList.vue"),
                meta: {
                    title: ROUTE.TITLE.MATCHING_LIST,
                }
            },
            {
                path: ROUTE.PATH.RECEPTION,
                component: () => import("@/views/ReceptionView.vue"),
                meta: {
                    title: ROUTE.TITLE.RECEPTION,
                }
            },
        ],
        meta: {
            middleware: [auth, language],
        }
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const routeMiddleware = (context: RouteGuard, middlewares: Array<any>, index: number) => {
    const nextMiddleware = middlewares[index];

    if (!nextMiddleware) {
        return context.next;
    }

    return () => {
        nextMiddleware({
            ...context,
            next: routeMiddleware(context, middlewares, index + 1),
        });
    };
};

router.beforeEach((to, from, next) => {
    const context: RouteGuard = { to, from, next, store: useAuthStore() };
    if (!to.meta.middleware) {
        return next();
    }
    const middleware = Array.isArray(to.meta.middleware) ? to.meta.middleware : [to.meta.middleware];

    middleware[0]({
        ...context,
        next: routeMiddleware(context, middleware, 1),
    });
});

router.afterEach((to) => {
    const meta: RouterMeta = to.meta;
    const titleKey = meta?.title as string | undefined;
    if (titleKey) {
        document.title = i18n.global.t(`pageTitles.${titleKey}`);
    }
});

export default router;
