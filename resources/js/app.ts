import { createApp } from 'vue';
import { createPinia } from "pinia";
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createVfm, VueFinalModal } from "vue-final-modal";
import App from './App.vue';
import router from "@/router";
import i18n from '@/i18n';
import { VueQrcodeReader } from 'vue-qrcode-reader'

// import Bootstrap JS
import "bootstrap";
import "vue-final-modal/style.css";
import 'vue3-toastify/dist/index.css';

const pinia = createPinia();

const app = createApp(App);
app.use(pinia);
app.use(VueQueryPlugin);
app.use(router);
app.use(i18n);
app.use(createVfm);
app.use(VueQrcodeReader);
app.component('VueFinalModal', VueFinalModal)
app.mount("#app");
