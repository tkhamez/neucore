'use strict';

require("./index.scss");

import Navbar from './components/Navbar.vue';
import Home            from './pages/Home.vue';
import GroupManagement from './pages/GroupManagement.vue';
import GroupAdmin      from './pages/GroupAdmin.vue';
import UserAdmin       from './pages/UserAdmin.vue';
import AppAdmin        from './pages/AppAdmin.vue';

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

        hasRole: function(name, player) {
            player = player || this.$root.player;
            if (! player) {
                return false;
            }
            return player.roles.indexOf(name) !== -1;
        },

        hasAnyRole: function(names) {
            for (var name of names) {
                if (this.hasRole(name)) {
                    return true;
                }
            }
            return false;
        },

        /**
         * Workaround for Swagger Codegen bug
         * https://github.com/swagger-api/swagger-codegen/issues/4819
         *
         * roles is: [{0: "a", 1: "b"}, {}] instead of ["ab", ""]
         */
        fixRoles: function(roles) {
            var fixed = [];
            for (var i = 0; i < roles.length; i++) {
                fixed[i] = '';
                for (var property in roles[i]) {
                    if (roles[i].hasOwnProperty(property)) {
                        fixed[i] += roles[i][property];
                    }
                }
            }
            return fixed;
        }
    }
});

var app = new window.Vue({
    el: '#app',

    components: {
        Navbar,
        Home,
        GroupManagement,
        UserAdmin,
        GroupAdmin,
        AppAdmin,
    },

    data: {

        /**
         * Current route (hash splitted by /), first element is the current page.
         */
        route: [],

        /**
         * All available pages
         */
        pages: ['Home', 'GroupManagement', 'UserAdmin', 'GroupAdmin', 'AppAdmin'],

        /**
         * The authenticated character
         */
        authChar: null,

        /**
         * The player object
         */
        player: null,

        /**
         * brvneucore API client
         */
        swagger: null,

        /**
         * True after first Ajax request finished.
         *
         * Don't do any request before this is true to avoid creating
         * several session on the server.
         */
        initialized: false,

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
        this.updateRoute();

        // route listener
        window.addEventListener('hashchange', () => {
            this.updateRoute();
        });

        // event listeners
        this.$on('playerChange', () => {
            this.getPlayer();
        });

        this.getAuthenticatedCharacter();

        // refresh session every 5 minutes
        window.setInterval(function() {
            app.getAuthenticatedCharacter(true);
        }, 1000*60*5);
    },

    watch: {
        initialized: function() {
            console.log('initialized');
            this.getPlayer();
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

        updateRoute() {
            this.route = window.location.hash.substr(1).split('/');

            // handle routes that do not have a page
            if (this.route[0] === 'login' || this.route[0] === 'login-alt') {
                this.getAuthResult();
                window.location.hash = '';
            } else if (this.route[0] === 'logout') {
                this.logout();
            }

            // set page, fallback to Home
            if (this.pages.indexOf(this.route[0]) === -1) {
                this.route[0] = 'Home';
            }
            this.page = this.route[0];
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

        getAuthenticatedCharacter: function(ping) {
            this.loading(true);
            new this.swagger.CharacterApi().show(function(error, data) {
                app.loading(false);
                if (error) { // 403 usually
                    app.authChar = null;
                    app.player = null;
                    app.page = 'Home';
                } else if (! ping) { // don't update because it triggers watch events
                    app.authChar = data;
                }
                app.initialized = true;
            });
        },

        getPlayer: function() {
            if (! this.authChar) {
                return;
            }

            this.loading(true);
            new this.swagger.PlayerApi().show(function(error, data) {
                app.loading(false);
                if (error) { // 403 usually
                    app.player = null;
                    return;
                }
                data.roles = app.fixRoles(data.roles);
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
                app.authChar = null;
                app.player = null;
            });
        },
    },
});
