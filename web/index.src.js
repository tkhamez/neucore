var SwaggerBrvneucoreJs = require('swagger-brvneucore-js'); /* jshint ignore: line */
var defaultClient = SwaggerBrvneucoreJs.ApiClient.instance;
defaultClient.basePath = location.protocol + "//" + location.hostname + ':' + location.port + '/api';

var bravecore = new window.Vue({
	el : '#app',

	data : {
		loginUrl : null,
		loginAltUrl : null,
		authChar : null,
		player : {},
		loadingCount: 0
	},

	mounted : function() {
		this.getLoginUrl();
		this.getLoginAltUrl();
		this.getCharacter();
		this.getPlayer();
	},

	methods : {

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

		getLoginUrl : function() {
			this.loading(true);
			new SwaggerBrvneucoreJs.AuthApi().loginUrl({}, function(error, data) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				bravecore.loginUrl = data;
			});
		},

		getLoginAltUrl : function() {
			this.loading(true);
			new SwaggerBrvneucoreJs.AuthApi().loginAltUrl({}, function(error, data) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				bravecore.loginAltUrl = data;
			});
		},

		getCharacter : function() {
			this.loading(true);
			new SwaggerBrvneucoreJs.CharacterApi().show(function(error, data) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					bravecore.authChar = null;
					return;
				}
				bravecore.authChar = data;
			});
		},

		getPlayer : function() {
			this.loading(true);
			new SwaggerBrvneucoreJs.PlayerApi().show(function(error, data) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				bravecore.player = data;
			});
		},

		logout : function() {
			this.loading(true);
			new SwaggerBrvneucoreJs.AuthApi().logout(function(error) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				bravecore.getCharacter();
				bravecore.getLoginUrl();
			});
		},

		makeMain : function(characterId) {
			this.loading(true);
			new SwaggerBrvneucoreJs.PlayerApi().setMain(characterId, function(error) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
					return;
				}
				bravecore.getPlayer();
			});
		},

		update : function(characterId) {
			this.loading(true);
			new SwaggerBrvneucoreJs.CharacterApi().update(characterId, function(error) {
				bravecore.loading(false);
				if (error) {
					window.console.error(error);
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
