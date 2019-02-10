
// bootstrap with bootswatch
import './vendor.scss';

// bootstrap and required jquery + popper
window.jQuery = require('jquery');
window.popper = require('popper.js');
require('bootstrap');
window.jQuery(function() {
    window.jQuery('[data-toggle="popover"]').popover();
});

// fontawesome (contains font files)
require('../node_modules/@fortawesome/fontawesome-free/css/all.css');

// swagger client
window.brvneucoreJsClient = require('brvneucore-js-client');

// Vue.js - runtime + compiler
window.Vue = require('vue/dist/vue.min.js');

window._ = require('lodash');

import "babel-polyfill"; // for useBuiltIns: true
