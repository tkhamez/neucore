'use strict';

require("./styles.scss");

var app;
var initApp = function() {
	app = new window.Vue(options);
};

if (window.addEventListener) {
	window.addEventListener('load', initApp);
} else if (window.attachEvent) { // IE
	window.attachEvent('onload', initApp);
}

var options = {
	el: '#app',

	data: {
		preview: false,
		loginUrl: null,
		loginAltUrl: null,
		successMessage: '',
		errorMessage: '',
		authChar: null,
		player: {},
		loadingCount: 0,
		swagger: null
	},

	mounted: function() {
		// "preview" banner
		if (location.hostname === 'brvneucore.herokuapp.com') {
			this.preview = true;
		}

		// configure swagger client
		this.swagger = window.brvneucoreJsClient;
		this.swagger.ApiClient.instance.basePath =
			location.protocol + "//" + location.hostname + ':' + location.port + '/api';

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
				app.successMessage = '';
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
			new this.swagger.AuthApi().loginUrl({
				redirect: '/#login'
			}, function(error, data) {
				app.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				app.loginUrl = data;
			});
		},

		getLoginAltUrl: function() {
			this.loading(true);
			new this.swagger.AuthApi().loginAltUrl({
				redirect: '/#login-alt'
			}, function(error, data) {
				app.loading(false);
				if (error) { // 403 usually
					return;
				}
				app.loginAltUrl = data;
			});
		},

		getAuthResult: function() {
			this.loading(true);
			new this.swagger.AuthApi().result(function(error, data) {
				app.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				if (! data.success) {
					app.errorMessage = data.message;
				}
			});
		},

		getCharacter: function() {
			this.loading(true);
			new this.swagger.CharacterApi().show(function(error, data) {
				app.loading(false);
				if (error) { // 403 usually
					app.authChar = null;
					return;
				}
				app.authChar = data;
			});
		},

		getPlayer: function() {
			this.loading(true);
			new this.swagger.PlayerApi().show(function(error, data) {
				app.loading(false);
				if (error) { // 403 usually
					return;
				}
				app.player = data;
			});
		},

		logout: function() {
			this.loading(true);
			new this.swagger.AuthApi().logout(function(error) {
				app.loading(false);
				if (error) { // 403 usually
					return;
				}
				app.getCharacter();
				app.getLoginUrl();
			});
		},

		makeMain: function(characterId) {
			this.loading(true);
			new this.swagger.PlayerApi().setMain(characterId, function(error) {
				app.loading(false);
				if (error) { // 403 usually
					return;
				}
				app.getPlayer();
			});
		},

		update: function(characterId) {
			this.loading(true);
			new this.swagger.CharacterApi().update(characterId, function(error) {
				app.loading(false);
				if (error) { // 403 (Core) or 503 (ESI down) usually
					if (error.message) {
						app.errorMessage = error.message;
					}
					return;
				}
				app.showSuccess('Update done.');
				app.getPlayer();
			});
		}
	}
};
