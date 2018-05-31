(function () {
	'use strict';

	var SwaggerBrvneucoreJs = require('bravecore-swagger-js-client'); /* jshint ignore: line */
	var defaultClient = SwaggerBrvneucoreJs.ApiClient.instance;
	defaultClient.basePath = location.protocol + "//" + location.hostname + ':' + location.port + '/api';

	var bravecore = new window.Vue({
		el : '#app',

		data: {
			loginUrl : null,
			loginAltUrl : null,
			errorMessage: '',
			authChar : null,
			player : {},
			loadingCount: 0
		},

		mounted: function() {
			this.getLoginUrl();
			this.getLoginAltUrl();
			this.getCharacter();
			this.getPlayer();
			if (location.hostname === 'brvneucore.herokuapp.com') {
				window.$('#preview').show();
			}
			if (location.hash === '#login' || location.hash === '#login-alt') {
				this.getAuthResult();
				location.hash = '';
			}
		},

		methods: {

			show: function(selector) {
				window.$(selector).show();
			},

			hide: function(selector) {
				window.$(selector).hide();
			},

			loading: function (status) {
				if (status) {
					this.loadingCount ++;
				} else {
					this.loadingCount --;
				}
				if (this.loadingCount > 0) {
					window.$("#loader").fadeIn();
				} else {
					window.$("#loader").fadeOut();
				}
			},

			getLoginUrl: function() {
				this.loading(true);
				new SwaggerBrvneucoreJs.AuthApi().loginUrl({
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
				new SwaggerBrvneucoreJs.AuthApi().loginAltUrl({
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
				new SwaggerBrvneucoreJs.AuthApi().result(function(error, data) {
					bravecore.loading(false);
					if (error) {
						window.console.error(error);
						return;
					}
					if (! data.success) {
						bravecore.errorMessage = data.message;
						bravecore.show("#msg-error");
					}
				});
			},

			getCharacter: function() {
				this.loading(true);
				new SwaggerBrvneucoreJs.CharacterApi().show(function(error, data) {
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
				new SwaggerBrvneucoreJs.PlayerApi().show(function(error, data) {
					bravecore.loading(false);
					if (error) { // 403 usually
						return;
					}
					bravecore.player = data;
				});
			},

			logout: function() {
				this.loading(true);
				new SwaggerBrvneucoreJs.AuthApi().logout(function(error) {
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
				new SwaggerBrvneucoreJs.PlayerApi().setMain(characterId, function(error) {
					bravecore.loading(false);
					if (error) { // 403 usually
						return;
					}
					bravecore.getPlayer();
				});
			},

			update: function(characterId) {
				this.loading(true);
				new SwaggerBrvneucoreJs.CharacterApi().update(characterId, function(error) {
					bravecore.loading(false);
					if (error) { // 403 (Core) or 503 (ESI down) usually
						if (error.message) {
							bravecore.errorMessage = error.message;
							window.$("#msg-error").show();
						}
						return;
					}
					bravecore.getPlayer();
					window.$("#msg-update-done").fadeIn(500, function() {
						window.$(this).delay(1500).slideUp(500);
					});
				});
			}
		}
	});
})();
