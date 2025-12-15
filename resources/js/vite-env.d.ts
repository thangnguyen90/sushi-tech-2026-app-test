/// <reference types="vite/client" />

declare module "*.svg" {
    const src: string;
    export default src;
}

declare module "*.svg?raw" {
    const content: string;
    export default content;
}

declare module "*App.vue" {
    const App: Component<Record<string, object>>;
    export default App;
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const component: DefineComponent<object, object, any>;
    export default component;
}

