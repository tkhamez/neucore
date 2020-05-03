
import "core-js";

// bootstrap JS (css is included via theme* entry point) with required jquery and popper.js
import $ from 'jquery';
import 'popper.js'; // initialization needs o be done after the component was rendered
import 'bootstrap';

// data tables
import 'datatables.net';
import bs4 from 'datatables.net-bs4';
bs4(window, $);
import 'datatables.net-bs4/css/dataTables.bootstrap4.css';

// fontawesome (contains font files)
import '../node_modules/@fortawesome/fontawesome-free/css/all.css';

// Vue.js
import Vue from 'vue'

// vue-multiselect
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
Vue.component('multiselect', Multiselect);


// app

import "./index.scss";
import App from './App.vue';
import './mixin';

new Vue({

    data: {

        /**
         * The player object
         */
        player: null,

        /**
         * System settings from backend
         */
        settings: {},

        loadingCount: 0,
    },

    render(h) {
        return h(App, {
            props: {
                player: this.player,
                settings: this.settings,
                loadingCount: this.loadingCount,
            }
        })
    },

}).$mount('#app');
