<template>
    <div id="app">
        <div v-if="loadingCount > 0" v-cloak id="loader"></div>

        <div v-if="messageTxt" v-cloak class="alert alert-dismissible fade show app-alert" :class="[messageType]">
            {{ messageTxt }}
            <button type="button" class="btn-close" v-on:click="messageTxt = ''" aria-label="Close"></button>
        </div>

        <nav-bar v-if="settingsLoaded" v-cloak :auth-char="authChar" :logout="logout" :route="route"></nav-bar>

        <playerModal ref="playerModal"></playerModal>
        <copy-text ref="copyText"></copy-text>

        <component v-if="settingsLoaded" v-cloak v-bind:is="page"
                   :route="route"
                   :auth-char="authChar"
                   :authLoaded="authLoaded"
        ></component>

        <footer class="footer border-top text-muted small">
            <div class="container-fluid">
                <span v-cloak>{{ settings.customization_footer_text }}</span>
            </div>
            <div class="container-fluid small">
                <div class="second-row">
                    EVE and related materials are trademarks of
                    <a class="external" href="https://www.ccpgames.com/" target="_blank"
                       rel="noopener noreferrer">CCP</a>.
                    <span class="brand">
                        <a :href="settings.repository" class="text-dark text-muted"
                           target="_blank" rel="noopener noreferrer" title="Neucore on GitHub">
                            <img :src="logo" alt=""> Neucore
                        </a>
                    </span>
                </div>
            </div>
        </footer>
    </div>
</template>

<script>
import {toRef} from "vue";
import superAgentPlugin from './superagent-plugin.js';
import { ApiClient, AuthApi, CharacterApi, PlayerApi, SettingsApi } from 'neucore-js-client';
import Data   from "./classes/Data";
import Helper from "./classes/Helper";
import Util   from "./classes/Util";
import NavBar      from './components/NavBar.vue';
import PlayerModal from './components/PlayerModal.vue';
import CopyText    from './components/CopyText.vue';
import Home    from './pages/Home.vue';
import Groups  from './pages/Groups.vue';
import Service from './pages/Service.vue';
import GroupManagement       from './pages/Management/GroupManagement.vue';
import AppManagement         from './pages/Management/AppManagement.vue';
import PlayerManagement from './pages/Management/PlayerManagement.vue';
import GroupAdmin     from './pages/Administration/GroupAdmin.vue';
import AppAdmin       from './pages/Administration/AppAdmin.vue';
import PluginAdmin   from './pages/Administration/PluginAdmin.vue';
import UserAdmin      from './pages/Administration/UserAdmin.vue';
import RoleAdmin      from './pages/Administration/RoleAdmin.vue';
import TrackingAdmin  from './pages/Administration/TrackingAdmin.vue';
import WatchlistAdmin from './pages/Administration/WatchlistAdmin.vue';
import SystemSettings from './pages/Administration/SystemSettings.vue';
import EVELoginAdmin  from './pages/Administration/EVELoginAdmin.vue';
import Statistics     from './pages/Administration/Statistics.vue';
import Tracking   from './pages/MemberData/Tracking.vue';
import Watchlist  from './pages/MemberData/Watchlist.vue';
import Characters from './pages/MemberData/Characters.vue';
import Esi        from './pages/MemberData/Esi.vue';
import logo from "../../setup/logo-small.svg";

export default {
    name: 'app',

    components: {
        NavBar,
        PlayerModal,
        CopyText,
        Home,
        Groups,
        Service,
        GroupManagement,
        AppManagement,
        PlayerManagement,
        GroupAdmin,
        AppAdmin,
        PluginAdmin,
        UserAdmin,
        RoleAdmin,
        TrackingAdmin,
        WatchlistAdmin,
        SystemSettings,
        EVELoginAdmin,
        Statistics,
        Tracking,
        Watchlist,
        Characters,
        Esi,
    },

    inject: ['store'],

    data() {
        return {
            h: new Helper(this),
            logo: logo,

            loadingCount: toRef(this.store.state, 'loadingCount'),
            settings: toRef(this.store.state, 'settings'),
            player: toRef(this.store.state, 'player'),

            /**
             * Current route (hash split by /), first element is the current page.
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
                'PlayerManagement',
                'GroupAdmin',
                'AppAdmin',
                'PluginAdmin',
                'UserAdmin',
                'RoleAdmin',
                'TrackingAdmin',
                'WatchlistAdmin',
                'SystemSettings',
                'EVELoginAdmin',
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

            authLoaded: false,

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

    created() {
        // environment variables
        Data.envVars = {
            baseUrl: process.env.BASE_URL,
            eveImageServer: process.env.VUE_APP_EVE_IMAGE_SERVER,
            backendHost: process.env.VUE_APP_BACKEND_HOST,
        };
        if (!Data.envVars.backendHost) {
            const winLocation = window.location;
            let port = '';
            if (winLocation.port !== '' && `${winLocation.port}` !== '80' && `${winLocation.port}` !== '443') {
                port = `:${winLocation.port}`;
            }
            Data.envVars.backendHost = `${winLocation.protocol}//${winLocation.hostname}${port}`;
        }

        // configure neucore-js-client
        ApiClient.instance.basePath = `${Data.envVars.backendHost}/api`;
        ApiClient.instance.plugins = [superAgentPlugin(this.h)];
        delete ApiClient.instance.defaultHeaders['User-Agent'];

        // Store redirect param from login
        this.loginRedirect = Util.getHashParameter('redirect', '');
        Util.removeHashParameter('redirect');

        // initial route
        this.updateRoute();

        // route listener
        window.addEventListener('hashchange', () => {
            this.updateRoute();
        });

        // event listener
        this.emitter.on('message', data => {
            this.showMessage(data.text, data.type, data.timeout);
        });
        this.emitter.on('copyText', characters => {
            this.$refs.copyText.exec(characters);
        });

        // refresh session every 5 minutes
        window.setInterval(() => {
            this.getAuthenticatedCharacter(true);
        }, 1000*60*5);

        // Get initial data.
        this.getSettings(_ => {
            // Make sure the first request is finished before making another one, so the rest of
            // them have the session cookie.

            // event listeners
            this.emitter.on('playerChange', () => {
                this.getPlayer();
                this.getSettings(); // roles and groups of a player can affect settings
            });
            this.emitter.on('settingsChange', () => {
                this.getSettings();
            });
            this.emitter.on('showCharacters', playerId => {
                this.$refs.playerModal.showCharacters(playerId);
            });

            getCsrfHeader(this);
            this.getAuthenticatedCharacter();
        });
    },

    mounted() {
        // These pages trigger a "playerChange" event, so it is not necessary to load the player already here.
        if (['Home', 'Groups', 'GroupManagement'].indexOf(this.page) === -1) {
            this.getPlayer();
        }
    },

    watch: {
        settings() {
            window.document.title = this.settings.customization_document_title;
        }
    },

    methods: {
        showMessage(text, type, timeout) {
            this.messageTxt = text;
            this.messageType = `alert-${type}`;
            if (timeout) {
                if (this.messageTimeout) {
                    window.clearTimeout(this.messageTimeout);
                    this.messageTimeout = null;
                }
                this.messageTimeout = window.setTimeout(() => {
                    this.messageTxt = '';
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
            if (['login-unknown', 'login', 'login-alt', 'login-custom'].indexOf(this.route[0]) !== -1) {
                authResult(
                    ['login-alt', 'login-custom'].indexOf(this.route[0]) !== -1 ? 'success' : '',
                    this
                );
                // Set hash value to redirect value from login or remove it, so that it does not appear in bookmarks,
                // for example.
                window.location.hash = this.loginRedirect;
                this.loginRedirect = '';
                return;
            } else if (this.route[0] === 'login-mail') {
                window.location.hash = 'SystemSettings/Mails';
                return;
            }

            // set page, fallback to Home
            if (this.pages.indexOf(this.route[0]) === -1) {
                this.route[0] = 'Home';
            }
            this.page = this.route[0];

            /**
             * @param {string} [successMessageType]
             * @param vm The vue instance
             */
            function authResult(successMessageType, vm) {
                new AuthApi().result((error, data) => {
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

        getSettings(callback) {
            new SettingsApi().systemList((error, data) => {
                if (error) {
                    return;
                }
                const settings = {};
                for (const variable of data) {
                    settings[variable.name] =
                        ['navigationGeneralPlugins', 'navigationServices'].indexOf(variable.name) !== -1 ?
                        JSON.parse(variable.value) :
                        variable.value;
                }
                this.store.setSettings(settings);
                this.settingsLoaded = true;

                if (callback) {
                    callback();
                }
            });
        },

        getAuthenticatedCharacter(ping) {
            new CharacterApi().userCharacterShow((error, data) => {
                if (error) { // 403 usually
                    this.authChar = null;
                    this.store.setPlayer(null);
                    this.page = 'Home';
                } else if (!ping) { // don't update because it triggers watch events
                    this.authChar = data;
                }
                this.authLoaded = true;
            });
        },

        getPlayer() {
            new PlayerApi().userPlayerShow((error, data) => {
                if (error) { // 403 usually
                    this.store.setPlayer(null);
                    this.authChar = null;
                    return;
                }
                this.store.setPlayer(data);
            });
        },

        logout() {
            new AuthApi().logout(() => {
                //this.authChar = null;
                //this.store.setPlayer(null);
                window.location.hash = '';

                // This is necessary to get a new CSRF token from the new session.
                window.location.reload();
            });
        },
    },
}

function getCsrfHeader(vm) {
    new AuthApi().authCsrfToken((error, data) => {
        if (error) {
            vm.csrfToken = '';
        } else {
            vm.csrfToken = data;
        }
    });
}

</script>

<style lang="scss" scoped>
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
        text-align: center;

        .second-row {
            position: relative;
            .brand {
                position: absolute;
                right: 0;
                background-color: var(--bs-body-bg);
                white-space: nowrap;
                img {
                    height: 12.25px;
                }
                a {
                    text-decoration: none;
                }
            }
        }
    }
</style>
