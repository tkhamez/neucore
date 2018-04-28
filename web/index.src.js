var SwaggerBrvneucoreJs = require('swagger-brvneucore-js'); /*jshint ignore: line */
var defaultClient = SwaggerBrvneucoreJs.ApiClient.instance;
defaultClient.basePath = location.protocol + "//" + location.hostname + ':' + location.port + '/api';

var app = new window.Vue({
	el : '#app',

	data : {
		loginUrl : null,
		loginAltUrl : null,
		authChar : null,
		player : {},
	},

	mounted : function() {
		this.getLoginUrl();
		this.getLoginAltUrl();
		this.getCharacter();
		this.getPlayer();
	},

	methods : {
		getLoginUrl : function() {
			new SwaggerBrvneucoreJs.AuthApi().loginUrl({}, function(error, data) {
				if (error) {
					window.console.error(error);
					return;
				}
				app.loginUrl = data;
			});
		},

		getLoginAltUrl : function() {
			new SwaggerBrvneucoreJs.AuthApi().loginAltUrl({}, function(error, data) {
				if (error) {
					window.console.error(error);
					return;
				}
				app.loginAltUrl = data;
			});
		},

		getCharacter : function() {
			new SwaggerBrvneucoreJs.CharacterApi().show(function(error, data) {
				if (error) {
					window.console.error(error);
					app.authChar = null;
					return;
				}
				app.authChar = data;
			});
		},

		getPlayer : function() {
			new SwaggerBrvneucoreJs.PlayerApi().show(function(error, data) {
				if (error) {
					window.console.error(error);
					return;
				}
				app.player = data;
			});
		},

		logout : function() {
			new SwaggerBrvneucoreJs.AuthApi().logout(function(error) {
				if (error) {
					window.console.error(error);
					return;
				}
				app.getCharacter();
				app.getLoginUrl();
			});
		},

		makeMain : function(characterId) {
			new SwaggerBrvneucoreJs.PlayerApi().setMain(characterId, function(error) {
				if (error) {
					window.console.error(error);
					return;
				}
				app.getPlayer();
			});
		},

		update : function(characterId) {
			new SwaggerBrvneucoreJs.CharacterApi().update(characterId, function(error) {
				if (error) {
					window.console.error(error);
					return;
				}
				app.getPlayer();
				window.$(".alert-success").fadeTo(2000, 500).slideUp(500, function(){
					window.$(".alert-success").slideUp(500);
				});
			});
		}
	}
});
