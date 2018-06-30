
// bootstrap with bootswatch and required jquery + popper
window.jQuery = require('jquery');
window.popper = require('popper.js');
require('../node_modules/bootswatch/dist/darkly/bootstrap.min.css');
require('bootstrap');
window.jQuery(function() {
    window.jQuery('[data-toggle="popover"]').popover();
});

// open-iconic (contains font files)
require('../node_modules/open-iconic/font/css/open-iconic-bootstrap.min.css');

// swagger client
window.brvneucoreJsClient = require('brvneucore-js-client');

// Vue.js - runtime + compiler
window.Vue = require('vue/dist/vue.min.js');
