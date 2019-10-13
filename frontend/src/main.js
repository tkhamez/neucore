
import "core-js";

// bootstrap JS (css is included via theme* entry point) and required jquery + popper
import $ from 'jquery';
//import 'popper.js';
import 'bootstrap';
//$(function() {
//    $('[data-toggle="popover"]').popover();
//});

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

        loadingCount: 0,
    },

    render(h) {
        return h(App, {
            props: {
                player: this.player,
                loadingCount: this.loadingCount,
            }
        })
    },

}).$mount('#app');
