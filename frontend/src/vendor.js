
// bootstrap JS (css is included via theme* entry point) and required jquery + popper
window.jQuery = require('jquery');
window.popper = require('popper.js');
require('bootstrap');
window.jQuery(function() {
    window.jQuery('[data-toggle="popover"]').popover();
});

// fontawesome (contains font files)
require('../node_modules/@fortawesome/fontawesome-free/css/all.css');

// swagger client
window.neucoreJsClient = require('neucore-js-client');

// Vue.js - runtime + compiler
window.Vue = require('vue/dist/vue.min.js');

// vue-multiselect
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
window.Vue.component('multiselect', Multiselect);

// lodash
window._ = require('lodash');

import "babel-polyfill"; // for useBuiltIns
