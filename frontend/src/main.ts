import { createApp } from "vue";
import "./style.css";
import App from "./App.vue";
import { registerServiceWorker } from "./registerServiceWorker";

createApp(App).mount("#app");
registerServiceWorker();
