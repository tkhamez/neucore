'use strict';

require("./index.scss");

import NavBar from './components/NavBar.vue';
import Home from './pages/Home.vue';
import Groups from './pages/Groups.vue';
import GroupManagement from './pages/GroupManagement.vue';
import AppManagement from './pages/AppManagement.vue';
import PlayerGroupManagement from './pages/PlayerGroupManagement.vue';
import GroupAdmin from './pages/GroupAdmin.vue';
import AppAdmin from './pages/AppAdmin.vue';
import UserAdmin from './pages/UserAdmin.vue';
import TrackingAdmin from './pages/TrackingAdmin.vue';
import Esi from './pages/Esi.vue';
import SystemSettings from './pages/SystemSettings.vue';
import Tracking from './pages/Tracking.vue';

import './mixin';

const app = new window.Vue({
    el: '#app',

    components: {
        NavBar,
        Home,
        Groups,
        GroupManagement,
        AppManagement,
        PlayerGroupManagement,
        GroupAdmin,
        AppAdmin,
        UserAdmin,
        TrackingAdmin,
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
            'Groups',
            'GroupManagement',
            'AppManagement',
            'PlayerGroupManagement',
            'GroupAdmin',
            'AppAdmin',
            'UserAdmin',
            'TrackingAdmin',
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
         * Neucore API client
         */
        swagger: null,

        /**
         * System settings from backend
         */
        settings: {},

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
        this.swagger = window.neucoreJsClient;
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

        // refresh session every 5 minutes
        window.setInterval(function() {
            app.getAuthenticatedCharacter(true);
        }, 1000*60*5);

        // get settings
        this.getSettings();
    },

    watch: {
        initialized: function() {
            this.getAuthenticatedCharacter();
            this.getPlayer();
        },

        settings: function() {
            window.document.title = this.settings.customization_document_title;
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
                // Remove the hash value so that it does not appear in bookmarks, for example.
                location.hash = '';
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
            this.loading(true);
            new this.swagger.SettingsApi().systemList(function(error, data) {
                app.loading(false);
                if (error) {
                    return;
                }
                const settings = {};
                for (let variable of data) {
                    settings[variable.name] = variable.value;
                }
                app.settings = settings; // watch() will work this way
                app.initialized = true;
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
            });
        },

        getPlayer: function() {
            this.loading(true);
            new this.swagger.PlayerApi().show(function(error, data) {
                app.loading(false);
                if (error) { // 403 usually
                    app.player = null;
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
                app.authChar = null;
                app.player = null;
            });
        },
    },
});
