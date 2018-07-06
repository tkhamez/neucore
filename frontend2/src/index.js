'use strict';

require("./index.scss");

import Navbar from './components/Navbar.vue';
import Home from './pages/Home.vue';
import GroupManagement from './pages/GroupManagement.vue';

window.Vue.mixin({
    methods: {
        loading: function(status) {
            if (status) {
                this.$root.loadingCount ++;
            } else {
                this.$root.loadingCount --;
            }
        },

        message: function(text, type) {
            switch (type) {
                case 'error':
                    this.$root.showError(text);
                    break;
                case 'success':
                    this.$root.showSuccess(text);
                    break;
            }
        },

        hasRole: function(name) {
            if (! this.$root.authChar || ! this.$root.player.roles) {
                return false;
            }
            return this.$root.player.roles.indexOf(name) !== -1;
        },

        hasAnyRole: function(names) {
            for (var name of names) {
                if (this.hasRole(name)) {
                    return true;
                }
            }
            return false;
        },
    }
});

var app = new window.Vue({
    el: '#app',

    components: {
        Navbar,
        Home,
        GroupManagement,
    },

    data: {
        /**
         * current route/location hash
         */
        route: '',

        /**
         * the current page/component
         */
        page: 'Home',

        /**
         * all available pages
         */
        pages: ['Home', 'GroupManagement'],

        /**
         * the authenticated character
         */
        authChar: null,

        /**
         * the player object
         */
        player: {},

        /**
         * brvneucore API client
         */
        swagger: null,

        successMessage: '',
        errorMessage: '',
        loadingCount: 0,
    },

    created: function() {
        // configure swagger client
        this.swagger = window.brvneucoreJsClient;
        this.swagger.ApiClient.instance.basePath =
            window.location.protocol + "//" +
            window.location.hostname + ':' +
            window.location.port + '/api';

        // initial route
        this.route = window.location.hash;

        // route listener
        window.addEventListener('hashchange', () => {
            this.route = window.location.hash;
        });

        // event listeners
        this.$on('playerChange', () => {
            this.getPlayer();
        });
    },

    mounted: function() {
        this.getCharacter();
        this.getPlayer();
    },

    watch: {
        route: function() {
            this.updatePage();
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

        updatePage() {
            var parts = this.route.substr(1).split('/');

            if (parts[0] === 'login' || parts[0] === 'login-alt') {
                this.getAuthResult();
            } else if (parts[0] === 'logout') {
                this.logout();
            }

            this.page = this.pages.indexOf(parts[0]) !== -1 ? parts[0] : 'Home';
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
                    app.page = 'Home';
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
    },
});
