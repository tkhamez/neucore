
// bootstrap JS (css is included via theme* entry point)
import 'bootstrap';

// Font Awesome (contains font files)
import '../node_modules/@fortawesome/fontawesome-free/css/all.css';

// Vue.js
import {createApp} from 'vue';

// vue3-multiselect CSS (Component is imported where it is used)
import '@suadelabs/vue3-multiselect/dist/vue3-multiselect.css';


// app

import mitt from 'mitt';
import store from "./store";
import "./index.scss";
import App from './App.vue';

const app = createApp(App);

app.config.globalProperties.emitter = mitt();
app.config.globalProperties.globalStore = store;

app.provide('store', store)
app.mount('#app');
