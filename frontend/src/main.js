
// bootstrap JS (css is included via theme* entry point)
import 'bootstrap';

import $ from 'jquery';

// data tables
import bs5 from 'datatables.net-bs5';
bs5(window, $);
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// fontawesome (contains font files)
import '../node_modules/@fortawesome/fontawesome-free/css/all.css';

// Vue.js
import {createApp, h} from 'vue';

// vue3-multiselect (Component is imported where it is used)
import '@suadelabs/vue3-multiselect/dist/vue3-multiselect.css';


// app

import "./index.scss";
import App from './App.vue';
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
