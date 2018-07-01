'use strict';

require("./index.scss");

import Home from './Home.vue';
import GroupManagement from './GroupManagement.vue';

var app = new window.Vue({
    el: '#app',

    components: {
        Home,
        GroupManagement,
    },

    data: {
        currentComponent: 'Home',
        successMessage: '',
        errorMessage: '',
        authChar: null,
        player: {},
        loadingCount: 0,
        swagger: null
    },

    created: function() {
        // configure swagger client
        this.swagger = window.brvneucoreJsClient;
        this.swagger.ApiClient.instance.basePath =
            window.location.protocol + "//" + window.location.hostname + ':' + window.location.port + '/api';

        // event listeners
        this.$on('playerChange', function() {
            this.getPlayer();
        });
    },

    mounted: function() {
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
            window.setTimeout(function() {
                app.successMessage = '';
            }, 1500);
        },

        showError: function(message) {
            this.errorMessage = message;
        },

        loading: function (status) {
            if (status) {
                this.loadingCount ++;
            } else {
                this.loadingCount --;
            }
        },

        getAuthResult: function() {
            this.loading(true);
            new this.swagger.AuthApi().result(function(error, data) {
                app.loading(false);
                if (error) {
                    window.console.error(error);
                    return;
                }
                if (data.success) {
                    console.log(data.message);
                } else {
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

                // TODO swagger codegen bug:
                // https://github.com/swagger-api/swagger-codegen/issues/4819
                // data.roles is: [{0: "a", 1: "b"}, {}] instead of ["ab", ""]
                // so transform back:
                var roles = [];
                for (var i = 0; i < data.roles.length; i++) {
                    roles[i] = '';
                    for (var property in data.roles[i]) {
                        if (data.roles[i].hasOwnProperty(property)) {
                            roles[i] += data.roles[i][property];
                        }
                    }
                }
                data.roles = roles;

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
            });
        },

        hasRole: function(name) {
            if (! this.authChar || ! this.player.roles) {
                return false;
            }
            return this.player.roles.indexOf(name) !== -1;
        }
    }
});
