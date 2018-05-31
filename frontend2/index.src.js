'use strict';

// jquery, popper + bootstrap with bootswatch
window.jQuery = require('jquery');
window.popper = require('popper.js');
require('bootstrap');
require('./node_modules/bootswatch/dist/darkly/bootstrap.min.css');
window.jQuery(function() {
	window.jQuery('[data-toggle="popover"]').popover();
});

// open-iconic, font files are copied to ../web, see fontify.json
require('./node_modules/open-iconic/font/css/open-iconic-bootstrap.min.css');

// swagger client
var swagger = require('brvneucore-js-client');
swagger.ApiClient.instance.basePath = location.protocol + "//" + location.hostname + ':' + location.port + '/api';

var Vue = require('vue');
var bravecore = new Vue({
	el: '#app',

	data: {
		preview: false,
		loginUrl: null,
		loginAltUrl: null,
		successMessage: '',
		errorMessage: '',
		authChar: null,
		player: {},
		loadingCount: 0
	},

	mounted: function() {
		// "preview" banner
		if (location.hostname === 'brvneucore.herokuapp.com') {
			this.preview = true;
		}

		this.getLoginUrl();
		this.getLoginAltUrl();
		this.getCharacter();
		this.getPlayer();

		if (location.hash === '#login' || location.hash === '#login-alt') {
			this.getAuthResult();
			location.hash = '';
		}
	},

	methods: {

		showSuccess: function(message) {
			this.successMessage = message;
			setTimeout(function() {
				bravecore.successMessage = '';
			}, 1500);
		},

		loading: function (status) {
			if (status) {
				this.loadingCount ++;
			} else {
				this.loadingCount --;
			}
		},

		getLoginUrl: function() {
			this.loading(true);
			new swagger.AuthApi().loginUrl({
				redirect: '/#login'
			}, function(error, data) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				bravecore.loginUrl = data;
			});
		},

		getLoginAltUrl: function() {
			this.loading(true);
			new swagger.AuthApi().loginAltUrl({
				redirect: '/#login-alt'
			}, function(error, data) {
				bravecore.loading(false);
				if (error) { // 403 usually
					return;
				}
				bravecore.loginAltUrl = data;
			});
		},

		getAuthResult: function() {
			this.loading(true);
			new swagger.AuthApi().result(function(error, data) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				if (! data.success) {
					bravecore.errorMessage = data.message;
				}
			});
		},

		getCharacter: function() {
			this.loading(true);
			new swagger.CharacterApi().show(function(error, data) {
				bravecore.loading(false);
				if (error) { // 403 usually
					bravecore.authChar = null;
					return;
				}
				bravecore.authChar = data;
			});
		},

		getPlayer: function() {
			this.loading(true);
			new swagger.PlayerApi().show(function(error, data) {
				bravecore.loading(false);
				if (error) { // 403 usually
					return;
				}
				bravecore.player = data;
			});
		},

		logout: function() {
			this.loading(true);
			new swagger.AuthApi().logout(function(error) {
				bravecore.loading(false);
				if (error) { // 403 usually
					return;
				}
				bravecore.getCharacter();
				bravecore.getLoginUrl();
			});
		},

		makeMain: function(characterId) {
			this.loading(true);
			new swagger.PlayerApi().setMain(characterId, function(error) {
				bravecore.loading(false);
				if (error) { // 403 usually
					return;
				}
				bravecore.getPlayer();
			});
		},

		update: function(characterId) {
			this.loading(true);
			new swagger.CharacterApi().update(characterId, function(error) {
				bravecore.loading(false);
				if (error) { // 403 (Core) or 503 (ESI down) usually
					if (error.message) {
						bravecore.errorMessage = error.message;
					}
					return;
				}
				bravecore.showSuccess('Update done.');
				bravecore.getPlayer();
			});
		}
	}
});
