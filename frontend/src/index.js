'use strict';

require("./index.scss");

import NavBar from './components/NavBar.vue';
import Home             from './pages/Home.vue';
import GroupManagement  from './pages/GroupManagement.vue';
import AppManagement    from './pages/AppManagement.vue';
import GroupAdmin       from './pages/GroupAdmin.vue';
import AppAdmin         from './pages/AppAdmin.vue';
import UserAdmin        from './pages/UserAdmin.vue';
import PlayerGroupAdmin from './pages/PlayerGroupAdmin.vue';
import Esi              from './pages/Esi.vue';
import SystemSettings   from './pages/SystemSettings.vue';
import Tracking         from './pages/Tracking.vue';

window.Vue.mixin({
    methods: {
        loading: function(status) {
            if (status) {
                this.$root.loadingCount ++;
            } else {
                this.$root.loadingCount --;
            }
        },

        /**
         * @param text
         * @param {string} type One of: error, warning, info or success
         */
        message: function(text, type) {
            switch (type) {
                case 'error':
                case 'info':
                case 'warning':
                    type = type === 'error' ? 'danger' : type;
                    this.$root.showMessage(text, type);
                    break;
                default: // success
                    this.$root.showMessage(text, type, 1500);
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
            for (let name of names) {
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
            const fixed = [];
            for (let i = 0; i < roles.length; i++) {
                fixed[i] = '';
                for (let property in roles[i]) {
                    if (roles[i].hasOwnProperty(property)) {
                        fixed[i] += roles[i][property];
                    }
                }
            }
            return fixed;
        },

        /**
         * @param {Date} date
         * @returns {string}
         */
        formatDate: function(date) {
            let str = date.toISOString();
            str = str.replace('T', ' ');
            str = str.replace('.000Z', '');
            return str.substr(0, str.length - 3);
        },

        /**
         *
         * @param {int} characterId
         * @param {function} [callback]
         */
        updateCharacter: function(characterId, callback) {
            const vm = this;

            vm.loading(true);
            new this.swagger.CharacterApi().update(characterId, function(error, data, response) {
                vm.loading(false);
                if (error) { // usually 403 (from Core) or 503 (ESI down)
                    if (error.message) {
                        vm.message(error.message, 'error');
                    }
                    return;
                }
                if (response.statusCode === 204) {
                    vm.message(
                        'The character was removed because it was deleted or ' +
                        'no longer belongs to the same EVE account.',
                        'info'
                    );
                } else {
                    vm.message('Update done.', 'success');
                }
                if (typeof callback === typeof Function) {
                    callback();
                }
            });
        },
    }
});

const app = new window.Vue({
    el: '#app',

    components: {
        NavBar,
        Home,
        GroupManagement,
        AppManagement,
        GroupAdmin,
        AppAdmin,
        UserAdmin,
        PlayerGroupAdmin,
        Esi,
        SystemSettings,
        Tracking,
    },

    data: {

        /**
         * Current route (hash splitted by /), first element is the current page.
         */
        route: [],

        /**
         * All available pages
         */
        pages: [
            'Home',
            'GroupManagement',
            'AppManagement',
            'GroupAdmin',
            'AppAdmin',
            'UserAdmin',
            'PlayerGroupAdmin',
            'Esi',
            'SystemSettings',
            'Tracking',
        ],

        /**
         * Current page
         */
        page: null,

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
         * System settings from backend
         */
        settings: [],

        /**
         * True after first Ajax request finished.
         *
         * Don't do any request before this is true to avoid creating
         * several session on the server.
         */
        initialized: false,

        messageTxt: '',

        messageType: '',

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
        this.$on('settingsChange', () => {
            this.getSettings();
        });

        this.getAuthenticatedCharacter();

        // refresh session every 5 minutes
        window.setInterval(function() {
            app.getAuthenticatedCharacter(true);
        }, 1000*60*5);
    },

    watch: {
        initialized: function() {
            this.getSettings();
            this.getPlayer();
        }
    },

    methods: {
        showMessage: function(text, type, timeout) {
            this.messageTxt = text;
            this.messageType = 'alert-' + type;
            if (timeout) {
                window.setTimeout(function() {
                    app.messageTxt = '';
                }, timeout);
            }
        },

        updateRoute() {
            this.route = window.location.hash.substr(1).split('/');

            // handle routes that do not have a page
            const vm = this;
            if (this.route[0] === 'logout') {
                this.logout();
            } else if (['login', 'login-alt'].indexOf(this.route[0]) !== -1) {
                authResult();
            } else if (this.route[0] === 'login-director') {
                authResult('info');
            }  else if (this.route[0] === 'login-mail') {
                location.hash = 'SystemSettings';
            }

            // set page, fallback to Home
            if (this.pages.indexOf(this.route[0]) === -1) {
                this.route[0] = 'Home';
            }
            this.page = this.route[0];

            /**
             * @param {string} [successMessageType]
             */
            function authResult(successMessageType) {
                vm.loading(true);
                new vm.swagger.AuthApi().result(function(error, data) {
                    vm.loading(false);
                    if (error) {
                        window.console.error(error);
                        return;
                    }
                    if (data.success) {
                        if (successMessageType) {
                            vm.message(data.message, successMessageType);
                        }
                    } else {
                        vm.message(data.message, 'error');
                    }
                });
            }
        },

        getSettings: function() {
            this.settings = [];
            this.loading(true);
            new this.swagger.SettingsApi().systemList(function(error, data) {
                app.loading(false);
                if (error) { // 403 usually
                    return;
                }
                app.settings = data;
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
