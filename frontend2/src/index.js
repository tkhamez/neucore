'use strict';

require("./index.scss");

import Navbar from './components/Navbar.vue';
import Home from './pages/Home.vue';
import GroupManagement from './pages/GroupManagement.vue';

window.Vue.mixin({
    methods: {
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
        route: '',
        page: '',
        pages: ['Home', 'GroupManagement'],
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
        this.$on('loading', (status) => {
            if (status) {
                this.loadingCount ++;
            } else {
                this.loadingCount --;
            }
        });
        this.$on('playerChange', () => {
            this.getPlayer();
        });
        this.$on('message', (text, type) => {
            switch (type) {
                case 'error':
                    this.showError(text);
                    break;
                case 'success':
                    this.showSuccess(text);
                    break;
            }
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
            this.$emit('loading', true);
            new this.swagger.AuthApi().result(function(error, data) {
                app.$emit('loading', false);
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
            this.$emit('loading', true);
            new this.swagger.CharacterApi().show(function(error, data) {
                app.$emit('loading', false);
                if (error) { // 403 usually
                    app.authChar = null;
                    app.page = 'Home';
                    return;
                }
                app.authChar = data;
            });
        },

        getPlayer: function() {
            this.$emit('loading', true);
            new this.swagger.PlayerApi().show(function(error, data) {
                app.$emit('loading', false);
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
            this.$emit('loading', true);
            new this.swagger.AuthApi().logout(function(error) {
                app.$emit('loading', false);
                if (error) { // 403 usually
                    return;
                }
                app.getCharacter();
            });
        },
    },
});
