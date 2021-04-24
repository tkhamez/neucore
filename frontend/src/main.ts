
// bootstrap JS (css is included via theme* entry point) with required jquery and popper.js
import $ from 'jquery';
//import * as $ from 'jquery';
import 'popper.js'; // initialization needs o be done after the component was rendered
import 'bootstrap';

// data tables
//import df from 'datatables.net';
// @ts-ignore
import bs4 from 'datatables.net-bs4';
bs4(window, $);
import 'datatables.net-bs4/css/dataTables.bootstrap4.css';

// fontawesome (contains font files)
import '../node_modules/@fortawesome/fontawesome-free/css/all.css';

// Vue.js
import{ createApp, h } from 'vue';

// vue3-multiselect (Component is imported where it is used)
import '@suadelabs/vue3-multiselect/dist/vue3-multiselect.css';


// app

import "./index.scss";
import App from './App.vue';
// @ts-ignore
import mixin from './mixin';
import mitt from 'mitt';

const app = createApp({

    data() {
        return {

            /**
             * The player object
             */
            player: null,

            /**
             * System settings from backend
             */
            settings: {},

            /**
             * Configuration form .env files
             */
            envVars: {},

            loadingCount: 0,
        }
    },

    render() {
        return h(App, {
            player: this.$data.player,
            settings: this.$data.settings,
            loadingCount: this.$data.loadingCount,
        });
    },

})
    .mixin(mixin);

app.config.globalProperties.emitter = mitt();

app.mount('#app');
