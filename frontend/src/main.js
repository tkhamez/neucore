
// bootstrap JS (css is included via theme* entry point) and required jquery + popper
import $ from 'jquery';
//require('popper.js');
require('bootstrap');
//$(function() {
//    $('[data-toggle="popover"]').popover();
//});

// data tables
require('datatables.net');
require('datatables.net-bs4')(window, $);
require('datatables.net-bs4/css/dataTables.bootstrap4.css');

// fontawesome (contains font files)
require('../node_modules/@fortawesome/fontawesome-free/css/all.css');

// Vue.js
import Vue from 'vue'

// vue-multiselect
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
Vue.component('multiselect', Multiselect);

import "babel-polyfill"; // for useBuiltIns


// app

require("./index.scss");
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
