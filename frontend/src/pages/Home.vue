<template>

<esi-tokens :eveLogins="eveLogins" :page="'Home'" ref="esiTokensModal"></esi-tokens>

<div class="modal fade" id="eveLoginsModal">
    <div v-cloak v-if="eveLogins" class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add additional ESI Tokens</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <p>
                    <strong>Do not use these logins unless you were asked to do so</strong>.<br>
                    If you want to add more characters to your account use the EVE login button on the main page.
                </p>
                <div class="table-responsive">
                    <table class="table" aria-describedby="EVE logins">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Login</th>
                                <th>Description</th>
                                <th>Scopes</th>
                                <th title="Required roles in the game" class="text-with-tooltip">Roles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="eveLogin in filteredEveLogins()">
                                <td>{{ eveLogin.name }}</td>
                                <td>
                                    <a :href="`${loginHost}/login/${eveLogin.name}`"
                                       class="ms-1 char-login-button" title="EVE SSO Login">
                                        <img src="../../public/img/eve_sso-short.png" alt="LOG IN with EVE Online">
                                    </a>
                                </td>
                                <td>{{ eveLogin.description }}</td>
                                <td>
                                    <span v-for="scope in eveLogin.esiScopes.split(' ')">
                                        {{ scope }}<br>
                                    </span>
                                </td>
                                <td>{{ eveLogin.eveRoles.join(', ') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div v-cloak v-if="authChar" class="modal fade" id="deleteCharModal">
    <div class="modal-dialog">
        <div v-if="charToDelete" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Character</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>
                    Are you sure you want to delete this character?
                    You will lose the associated groups.
                </p>
                <p class="text-warning">{{ charToDelete.name }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal" v-on:click="deleteChar()">
                    DELETE character
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div v-cloak v-if="authLoaded && !authChar" class="mt-3">
        <title-logo></title-logo>
        <p>Click the button below to login through <em>EVE Online SSO</em>.</p>
        <a :href="`${loginHost}/login/${loginNames.default}${redirectQuery}`">
            <img src="../assets/EVE_SSO_Login_Buttons_Large_Black.png" alt="LOG IN with EVE Online">
        </a>
        <p class="small">
            <br>
            Learn more about
            <a class="external" href="https://support.eveonline.com/hc/en-us/articles/205381192"
               target="_blank" rel="noopener noreferrer">EVE Online Single Sign On</a>.
        </p>
        <span v-if="markdownLoginText">
            <br>
            <span v-html="markdownLoginText"></span>
        </span>
    </div>

    <div v-cloak v-if="authChar" class="card mt-3 mb-3">
        <div class="card-body">
            <title-logo></title-logo>
            <p>Add your other characters by logging in with EVE SSO.</p>
            <p>
                <a :href="`${loginHost}/login/${loginNames.default}`">
                    <img src="../../public/img/eve_sso.png" alt="LOG IN with EVE Online"
                         title="Login to add another character.">
                </a>
            </p>
            <button href="#" class="btn btn-secondary nc-btn-xs fw-normal p-2"
               data-bs-toggle="modal" data-bs-target="#eveLoginsModal">
                Add additional ESI tokens
            </button>
        </div>
    </div>

    <div v-cloak v-if="authChar && markdownHtml" class="card mb-3">
        <div class="card-body pb-0" v-html="markdownHtml"></div>
    </div>

    <div v-cloak v-if="deactivated" class="alert alert-danger">
        Groups for this account are <strong>disabled</strong> (or will be disabled soon)
        because one or more characters do not have a valid ESI token.<br>
        Note: Deleted characters will be removed automatically within 1 to 2 days.
    </div>

    <div v-cloak v-if="player">
        <div class="row">
            <div class="col-lg-8">
                <h2>Characters</h2>
                <div class="row row-cols-2 row-cols-md-3 row-cols-xxl-4 g-4">
                    <div v-for="char in player.characters" class="col border-secondary">
                        <div class="card h-100">
                            <div class="card-header">
                                <img :src="h.characterPortrait(char.id, 32)" alt="portrait">
                                {{ char.name }}
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <span class="text-muted">Corporation: </span>
                                        <span v-if="char.corporation">{{ char.corporation.name }}</span>
                                    <br>
                                    <span class="text-muted">Alliance: </span>
                                    <span v-if="char.corporation && char.corporation.alliance">
                                        {{ char.corporation.alliance.name }}
                                    </span>
                                </p>
                            </div>
                            <div class="card-footer">
                                <span v-if="char.main" class="badge bg-warning me-1">Main</span>
                                <a v-if="! char.main" class="btn btn-primary nc-btn-xs fw-normal me-1" href="#"
                                   v-on:click.prevent="makeMain(char.id)">Make Main</a>

                                <a class="btn btn-primary nc-btn-xs fw-normal me-1" href="#"
                                   v-on:click.prevent="update(char.id)">Update character</a>
                                <a v-if="authChar && authChar.id !== char.id &&
                                         settings.allow_character_deletion === '1'"
                                   class="btn btn-danger nc-btn-xs"
                                   v-on:click.prevent="askDeleteChar(char.id, char.name)"
                                   href="#" title="Delete"><span role="img" class="fas fa-trash-alt"></span></a>

                                <br>

                                <a href="#" class="fw-normal me-1"
                                   v-on:click.prevent="showEsiTokens(char, char.validToken === false)"
                                   :class="{
                                          'btn btn-success nc-btn-xs': char.validToken,
                                          'btn btn-info nc-btn-xs': char.validToken === null,
                                          'btn btn-danger btn-sm mt-1': char.validToken === false,
                                   }"
                                   :title="char.validToken ? 'Valid default ESI token' :
                                           (char.validToken === null ? 'No default ESI token' :
                                            'Invalid default ESI token')"
                                >
                                    ESI tokens
                                </a>
                                <a v-if="char.validToken === false" :href="`${loginHost}/login/${loginNames.default}`"
                                   class="char-login-button" :title="`Login in with: ${char.name}`">
                                    <img src="../../public/img/eve_sso-short.png" alt="LOG IN with EVE Online">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="player-hdl">
                    <h2>Account</h2>
                    <span class="text-muted">{{ player.name }} #{{ player.id }}</span>
                    <span v-if="player.status === 'managed'" class="text-muted"> (manually managed)</span>
                </div>
                <div class="card border-secondary mb-3">
                    <h3 class="card-header">Groups</h3>
                    <ul class="list-group list-group-flush" :class="{ 'groups-disabled': deactivated }">
                        <li v-for="group in player.groups" class="list-group-item">
                            {{ group.name }}
                        </li>
                    </ul>
                </div>
                <div v-if="player.roles.length > 1" class="card border-secondary mb-3" >
                    <h3 class="card-header">Roles</h3>
                    <ul class="list-group list-group-flush">
                        <li v-for="role in playerRoles" class="list-group-item">
                            {{ role }}
                        </li>
                    </ul>
                </div>
            </div>
        </div> <!-- row -->
    </div> <!-- if authenticated -->
</div> <!-- container -->
</template>

<script>
import {toRef} from "vue";
import {Modal} from "bootstrap";
import {PlayerApi, SettingsApi} from 'neucore-js-client';
import markdownIt from 'markdown-it';
import mdEmoji from 'markdown-it-emoji/light';
import mdSup from 'markdown-it-sup';
import mdSub from 'markdown-it-sub';
import mdIns from 'markdown-it-ins';
import mdAbbr from 'markdown-it-abbr';
import mdMark from 'markdown-it-mark';
import mdAttrs from 'markdown-it-attrs';
import Data from "../classes/Data";
import Character from "../classes/Character";
import Helper from "../classes/Helper";
import TitleLogo from './Home--title-logo.vue';
import EsiTokens from "../components/EsiTokens.vue";

export default {
    components: {
        EsiTokens,
        TitleLogo,
    },

    inject: ['store'],

    props: {
        route: Array,
        authLoaded: Boolean,
        authChar: Object,
    },

    data: function() {
        return {
            h: new Helper(this),
            settings: toRef(this.store.state, 'settings'),
            player: toRef(this.store.state, 'player'),
            loginNames: Data.loginNames,
            deactivated: false,
            charToDelete: null,
            md: null,
            markdownHtml: '',
            markdownLoginText: '',
            loginHost: '',
            redirectQuery: '',
            eveLogins: null,
            deleteCharModal: null,
        }
    },

    computed: {
        playerRoles() {
            return this.player.roles.filter(role => role !== 'user');
        }
    },

    created () {
        this.md = markdownIt({ typographer: true })
            .use(mdEmoji)
            .use(mdSup)
            .use(mdSub)
            .use(mdIns)
            .use(mdAbbr)
            .use(mdMark)
            .use(mdAttrs) // for classes, like .text-warning, .bg-primary
        ;
        this.md.renderer.rules.emoji = function(token, idx) {
            return `<span class="emoji">${token[idx].content}</span>`;
        };
    },

    mounted () { // after "redirect" from another page
        window.scrollTo(0, 0);
        this.emitter.emit('playerChange'); // Ensure group memberships are up-to-date.

        this.loginHost = Data.envVars.backendHost;

        loginAddRedirect(this);
        checkDeactivated(this);
        getEveLogins(this);
    },

    watch: {
        settings () {
            this.markdownHtml = this.md.render(this.settings.customization_home_markdown);
            this.markdownLoginText = this.md.render(this.settings.customization_login_text);
        },

        authChar: function() { // for primary login and logout
            checkDeactivated(this);
        },

        player: function() {
            if (!this.player) {
                return;
            }
            checkDeactivated(this);
        },
    },

    methods: {
        makeMain: function(characterId) {
            const vm = this;
            new PlayerApi().setMain(characterId, function(error) {
                if (error) { // 403 usually
                    return;
                }
                vm.emitter.emit('playerChange');
            });
        },

        update: function(characterId) {
            const vm = this;
            (new Character(this)).updateCharacter(characterId, function() {
                vm.emitter.emit('playerChange');
            });
        },

        askDeleteChar(characterId, characterName) {
            this.charToDelete = {
                id: characterId,
                name: characterName,
            };
            this.deleteCharModal = new Modal('#deleteCharModal');
            this.deleteCharModal.show();
        },

        deleteChar() {
            const vm = this;
            (new Character(vm)).deleteCharacter(this.charToDelete.id, null, function() {
                vm.emitter.emit('playerChange');
            });
            if (this.deleteCharModal) {
                this.deleteCharModal.hide();
            }
            this.charToDelete = null;
        },

        showEsiTokens (character, showInvalid) {
            this.$refs.esiTokensModal.showModal(character, showInvalid);
        },

        filteredEveLogins () {
            return this.eveLogins.filter(eveLogin => {
                return eveLogin.name.indexOf(Data.loginPrefixProtected) !== 0 ||
                       eveLogin.name === Data.loginNames.tracking
            });
        },
    }
}

function loginAddRedirect(vm) {
    if (vm.authChar || window.location.hash.length <= 1) {
        return;
    }

    vm.redirectQuery = `?redirect=${window.location.hash.substring(1)}`;

    // Add redirect query to login links in custom markdown
    const markdownHtml = document.createElement("div");
    markdownHtml.innerHTML = vm.markdownLoginText;
    for (const link of markdownHtml.getElementsByTagName('a')) {
        const href = link.getAttribute('href');
        if (href.indexOf('/login/') === 0) {
            link.setAttribute('href', href + vm.redirectQuery);
        }
    }
    vm.markdownLoginText = markdownHtml.innerHTML;
}

function checkDeactivated(vm) {
    if (!vm.player) {
        vm.deactivated = false;
        return;
    }

    new PlayerApi().groupsDisabled(function(error, data) {
        if (error) { // 403 usually
            return;
        }
        vm.deactivated = data;
    });
}

function getEveLogins(vm) {
    new SettingsApi().userSettingsEveLoginList((error, data) => {
        if (error) {
            return;
        }
        vm.eveLogins = data;
    });
}
</script>

<style lang="scss" scoped>
    .player-hdl {
        position: relative;

        h2 {
            display: inline-block;
            margin-right: 10px;
        }
    }

    .char-login-button img {
        position: relative;
        top: 2px;
    }

    .groups-disabled {
        text-decoration: line-through;
    }
</style>
