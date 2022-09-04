<template>
    <div id="app">
        <div v-if="loadingCount > 0" v-cloak id="loader"></div>

        <div v-if="messageTxt" v-cloak class="alert alert-dismissible fade show app-alert" :class="[messageType]">
            {{ messageTxt }}
            <button type="button" class="btn-close" v-on:click="messageTxt = ''" aria-label="Close"></button>
        </div>

        <nav-bar v-if="settingsLoaded" v-cloak :auth-char="authChar" :route="route" :settings="settings"></nav-bar>

        <charactersModal ref="charactersModal"></charactersModal>

        <component v-if="settingsLoaded" v-cloak v-bind:is="page"
                   :route="route"
                   :settings="settings"
                   :player="player"
                   :auth-char="authChar">
        </component>

        <footer class="footer border-top text-muted small">
            <div class="container-fluid">
                <span v-cloak>{{ settings.customization_footer_text }}</span>
                <a v-cloak :href="settings.repository" class="icon-link text-dark text-muted"
                   target="_blank" rel="noopener noreferrer"
                   title="GitHub"><span class="fa-brands fa-github"></span></a>
                <a v-cloak :href="settings.discord" class="icon-link text-dark text-muted"
                   target="_blank" rel="noopener noreferrer"
                   title="Discord"><span class="fa-brands fa-discord"></span></a>
            </div>
            <div class="container-fluid small">
                "EVE", "EVE Online", "CCP" and all related logos and images are trademarks or registered trademarks of
                <a href="https://www.ccpgames.com/" target="_blank" rel="noopener noreferrer">CCP hf</a>.
            </div>
        </footer>
    </div>
</template>

<script>
import { ApiClient, AuthApi, CharacterApi, PlayerApi, SettingsApi } from 'neucore-js-client';
import superAgentPlugin from './superagent-plugin.js';
import Util from "./classes/Util";
import Helper from "./classes/Helper";
import NavBar from './components/NavBar.vue';
import CharactersModal from './components/Characters.vue';
import Home from './pages/Home.vue';
import Groups from './pages/Groups.vue';
import Service from './pages/Service.vue';
import GroupManagement from './pages/GroupManagement.vue';
import AppManagement from './pages/AppManagement.vue';
import PlayerGroupManagement from './pages/PlayerGroupManagement.vue';
import GroupAdmin from './pages/GroupAdmin.vue';
import AppAdmin from './pages/AppAdmin.vue';
import ServiceAdmin from './pages/ServiceAdmin.vue';
import UserAdmin from './pages/UserAdmin.vue';
import RoleAdmin from './pages/RoleAdmin.vue';
import TrackingAdmin from './pages/TrackingAdmin.vue';
import WatchlistAdmin from './pages/WatchlistAdmin.vue';
import SystemSettings from './pages/SystemSettings.vue';
import EVELogins from './pages/EVELogins.vue';
import Statistics from './pages/Statistics.vue';
import Tracking from './pages/Tracking.vue';
import Watchlist from './pages/Watchlist.vue';
import Characters from './pages/Characters.vue';
import Esi from './pages/Esi.vue';

export default {
    name: 'app',

    components: {
        NavBar,
        CharactersModal,
        Home,
        Groups,
        Service,
        GroupManagement,
        AppManagement,
        PlayerGroupManagement,
        GroupAdmin,
        AppAdmin,
        ServiceAdmin,
        UserAdmin,
        RoleAdmin,
        TrackingAdmin,
        WatchlistAdmin,
        SystemSettings,
        EVELogins,
        Statistics,
        Tracking,
        Watchlist,
        Characters,
        Esi,
    },

    props: {
        player: Object,
        settings: Object,
        loadingCount: Number,
    },

    data() {
        return {
            h: new Helper(this),

            /**
             * Current route (hash splitted by /), first element is the current page.
             */
            route: [],

            loginRedirect: '',

            /**
             * All available pages
             */
            pages: [
                'Home',
                'Groups',
                'Service',
                'GroupManagement',
                'AppManagement',
                'PlayerGroupManagement',
                'GroupAdmin',
                'AppAdmin',
                'ServiceAdmin',
                'UserAdmin',
                'RoleAdmin',
                'TrackingAdmin',
                'WatchlistAdmin',
                'SystemSettings',
                'EVELogins',
                'Statistics',
                'Tracking',
                'Watchlist',
                'Characters',
                'Esi',
            ],

            /**
             * Current page
             */
            page: null,

            /**
             * The authenticated character
             */
            authChar: null,

            messageTxt: '',

            messageTimeout: null,

            messageType: '',

            settingsLoaded: false,

            csrfToken: '',
        }
    },

    created: function() {
        // environment variables
        this.$root.envVars = {
            baseUrl: process.env.BASE_URL,
            eveImageServer: process.env.VUE_APP_EVE_IMAGE_SERVER,
            backendHost: process.env.VUE_APP_BACKEND_HOST,
        };
        if (!this.$root.envVars.backendHost) {
            const winLocation = window.location;
            let port = '';
            if (winLocation.port !== '' && `${winLocation.port}` !== '80' && `${winLocation.port}` !== '443') {
                port = `:${winLocation.port}`;
            }
            this.$root.envVars.backendHost = `${winLocation.protocol}//${winLocation.hostname}${port}`;
        }

        // configure neucore-js-client
        ApiClient.instance.basePath = `${this.$root.envVars.backendHost}/api`;
        ApiClient.instance.plugins = [superAgentPlugin(this.h)];

        // Store redirect param from login
        this.loginRedirect = Util.getHashParameter('redirect', '');
        Util.removeHashParameter('redirect');

        // initial route
        this.updateRoute();

        // route listener
        window.addEventListener('hashchange', () => {
            this.updateRoute();
        });

        // event listeners
        this.emitter.on('playerChange', () => {
            this.getPlayer();
            this.getSettings(); // roles and groups of a player can affect settings
        });
        this.emitter.on('settingsChange', () => {
            this.getSettings();
        });
        this.emitter.on('message', (data) => {
            this.showMessage(data.text, data.type, data.timeout);
        });
        this.emitter.on('showCharacters', (playerId) => {
            this.$refs.charactersModal.showCharacters(playerId);
        });

        // refresh session every 5 minutes
        const vm = this;
        window.setInterval(function() {
            vm.getAuthenticatedCharacter(true);
        }, 1000*60*5);

        // get initial data
        this.getSettings();
        getCsrfHeader(this);
        this.getAuthenticatedCharacter();
        this.getPlayer();
    },

    watch: {
        settings: function() {
            window.document.title = this.settings.customization_document_title;
        }
    },

    methods: {
        showMessage: function(text, type, timeout) {
            this.messageTxt = text;
            this.messageType = `alert-${type}`;
            if (timeout) {
                if (this.messageTimeout) {
                    window.clearTimeout(this.messageTimeout);
                    this.messageTimeout = null;
                }
                const vm = this;
                this.messageTimeout = window.setTimeout(() => {
                    vm.messageTxt = '';
                }, timeout);
            }
        },

        updateRoute() {
            let hash = window.location.hash;
            if (hash.indexOf('?') !== -1) {
                hash = hash.substring(0, hash.indexOf('?'));
            }
            this.route = hash.substring(1).split('/');

            // handle routes that do not have a page
            const vm = this;
            if (this.route[0] === 'logout') {
                this.logout();
                window.location.hash = '';
            } else if (
                ['login-unknown', 'login', 'login-alt', 'login-custom']
                    .indexOf(this.route[0]) !== -1
            ) {
                authResult(['login-alt', 'login-custom'].indexOf(this.route[0]) !== -1 ? 'success' : '');
                // Set hash value to redirect value from login or remove it, so that it does not appear in bookmarks,
                // for example.
                window.location.hash = this.loginRedirect;
                this.loginRedirect = '';
            } else if (this.route[0] === 'login-mail') {
                window.location.hash = 'SystemSettings/Mails';
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
                new AuthApi().result(function(error, data) {
                    if (error) {
                        window.console.error(error);
                        return;
                    }
                    if (data.success) {
                        if (successMessageType) {
                            vm.h.message(data.message, successMessageType, 5000);
                        }
                    } else {
                        vm.h.message(data.message, 'error');
                    }
                });
            }
        },

        getSettings: function() {
            const vm = this;
            new SettingsApi().systemList(function(error, data) {
                if (error) {
                    return;
                }
                const settings = {};
                for (const variable of data) {
                    settings[variable.name] = variable.name === 'navigationServices' ?
                        JSON.parse(variable.value) :
                        variable.value;
                }
                vm.$root.settings = settings; // watch() will work this way
                vm.settingsLoaded = true;
            });
        },

        getAuthenticatedCharacter: function(ping) {
            const vm = this;
            new CharacterApi().userCharacterShow(function(error, data) {
                if (error) { // 403 usually
                    vm.authChar = null;
                    vm.$root.player = null;
                    vm.page = 'Home';
                } else if (! ping) { // don't update because it triggers watch events
                    vm.authChar = data;
                }
            });
        },

        getPlayer: function() {
            const vm = this;
            new PlayerApi().userPlayerShow(function(error, data) {
                if (error) { // 403 usually
                    vm.$root.player = null;
                    return;
                }
                vm.$root.player = data;
            });
        },

        logout: function() {
            const vm = this;
            new AuthApi().logout(function(error, data, response) {
                if (error) { // 403 usually
                    if (response.statusCode === 403) {
                        vm.h.message('Unauthorized.', 'error');
                    }
                    return;
                }
                vm.authChar = null;
                vm.$root.player = null;
            });
        },
    },
}

function getCsrfHeader(vm) {
    new AuthApi().authCsrfToken(function(error, data) {
        if (error) {
            vm.csrfToken = '';
        } else {
            vm.csrfToken = data;
        }
    });
}

</script>

<style scoped>
    .alert.app-alert {
        position: fixed;
        top: 60px;
        left: 25%;
        right: 25%;
        z-index: 5000;
    }

    #loader {
        position: fixed;
        top: 25px;
        left: 50%;
        width: 120px;
        height: 120px;
        margin-left: -60px;
        z-index: 5000;
        border: 16px solid #555;
        border-top: 16px solid #eee;
        border-radius: 50%;
        animation: spin 2s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .footer {
        padding: 5px 0;
        position: absolute;
        bottom: 0;
        width: 100%;
        max-height: 75px;
        overflow-y: auto;
    }
    .footer .container-fluid {
        text-align: center;
    }
    .footer .icon-link {
        float: right;
        margin-left: 4px;
    }
</style>

<!--suppress CssUnusedSymbol -->
<style>
    .character-name-changes .tooltip-inner,
    .character-token .tooltip-inner {
        max-width: initial;
        text-align: left;
    }
</style>
