'use strict';

var brvneucore = new window.Vue({
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
				brvneucore.successMessage = '';
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
				brvneucore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				brvneucore.loginUrl = data;
			});
		},

		getLoginAltUrl: function() {
			this.loading(true);
			new this.swagger.AuthApi().loginAltUrl({
				redirect: '/#login-alt'
			}, function(error, data) {
				brvneucore.loading(false);
				if (error) { // 403 usually
					return;
				}
				brvneucore.loginAltUrl = data;
			});
		},

		getAuthResult: function() {
			this.loading(true);
			new this.swagger.AuthApi().result(function(error, data) {
				brvneucore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				if (! data.success) {
					brvneucore.errorMessage = data.message;
				}
			});
		},

		getCharacter: function() {
			this.loading(true);
			new this.swagger.CharacterApi().show(function(error, data) {
				brvneucore.loading(false);
				if (error) { // 403 usually
					brvneucore.authChar = null;
					return;
				}
				brvneucore.authChar = data;
			});
		},

		getPlayer: function() {
			this.loading(true);
			new this.swagger.PlayerApi().show(function(error, data) {
				brvneucore.loading(false);
				if (error) { // 403 usually
					return;
				}
				brvneucore.player = data;
			});
		},

		logout: function() {
			this.loading(true);
			new this.swagger.AuthApi().logout(function(error) {
				brvneucore.loading(false);
				if (error) { // 403 usually
					return;
				}
				brvneucore.getCharacter();
				brvneucore.getLoginUrl();
			});
		},

		makeMain: function(characterId) {
			this.loading(true);
			new this.swagger.PlayerApi().setMain(characterId, function(error) {
				brvneucore.loading(false);
				if (error) { // 403 usually
					return;
				}
				brvneucore.getPlayer();
			});
		},

		update: function(characterId) {
			this.loading(true);
			new this.swagger.CharacterApi().update(characterId, function(error) {
				brvneucore.loading(false);
				if (error) { // 403 (Core) or 503 (ESI down) usually
					if (error.message) {
						brvneucore.errorMessage = error.message;
					}
					return;
				}
				brvneucore.showSuccess('Update done.');
				brvneucore.getPlayer();
			});
		}
	}
});
