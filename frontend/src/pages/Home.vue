<template>
    <div class="container-fluid">

        <div v-cloak v-if="authChar" class="modal fade" id="tokenModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Invalid ESI token</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>
                            The ESI token for this character is no longer valid.<br>
                            Please use the EVE login button and login with this character
                            again to create a new token.
                        </p>
                        <p class="align-center">
                            <a :href="loginHost + '/login-alt'">
                                <img src="../assets/eve_sso.png" alt="LOG IN with EVE Online">
                            </a>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-cloak v-if="authChar" class="modal fade" id="deleteCharModal">
            <div class="modal-dialog">
                <div v-if="charToDelete" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Character</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>
                            Are you sure you want to delete this character?
                            You will lose the associated groups.
                        </p>
                        <p class="text-warning">{{ charToDelete.name }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal" v-on:click="deleteChar()">
                            DELETE character
                        </button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-cloak v-if="! authChar" class="jumbotron mt-3">
            <title-logo :settings="settings"></title-logo>
            <p>Click the button below to login through <em>EVE Online SSO</em>.</p>
            <a :href="loginHost + '/login'">
                <img src="../assets/EVE_SSO_Login_Buttons_Large_Black.png" alt="LOG IN with EVE Online">
            </a>
            <p class="small">
                <br>
                Learn more about the security of <em>EVE Online SSO</em> in this
                <a href="https://www.eveonline.com/article/eve-online-sso-and-what-you-need-to-know/"
                    target="_blank" rel="noopener noreferrer">dev-blog</a> article.
            </p>
            <span v-if="markdownLoginText">
                <br>
                <span v-html="markdownLoginText"></span>
            </span>
        </div>
        <div v-cloak v-if="authChar" class="card mt-3 mb-3">
            <div class="card-body">
                <title-logo :settings="settings"></title-logo>
                <p>Add your other characters by logging in with EVE SSO.</p>
                <p>
                    <a :href="loginHost + '/login-alt'">
                        <img src="../assets/eve_sso.png" alt="LOG IN with EVE Online">
                    </a>
                    <span v-if="player && player.status === 'managed'">
                        <br>
                        <a :href="loginHost + '/login-managed-alt'">Login without scopes</a>
                    </span>
                </p>
            </div>
        </div>

        <div v-cloak v-if="authChar && markdownHtml" class="card mb-3">
            <div class="card-body pb-0" v-html="markdownHtml"></div>
        </div>

        <div v-cloak v-if="deactivated" class="alert alert-danger">
            Groups for this account are <strong>disabled</strong> (or will be disabled soon)
            because one or more characters do not have a valid ESI token.
        </div>

        <div v-cloak v-if="player">
            <div class="row">
                <div class="col-lg-8">
                    <h2>Characters</h2>
                    <div class="card-columns">
                        <div v-for="char in player.characters" class="card border-secondary">
                            <div class="card-header">
                                <img :src="characterPortrait(char.id, 32)" alt="portrait">
                                {{ char.name }}
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <span class="text-muted">Corporation:</span>
                                    <span v-if="char.corporation">
                                        {{ char.corporation.name }}
                                    </span>
                                    <br>
                                    <span class="text-muted">Alliance:</span>
                                    <span v-if="char.corporation && char.corporation.alliance">
                                        {{ char.corporation.alliance.name }}
                                    </span>
                                </p>
                            </div>
                            <div class="card-footer">
                                <span v-if="char.main" class="badge badge-warning">Main</span>
                                <a v-if="! char.main" class="badge badge-primary" href="#"
                                   v-on:click.prevent="makeMain(char.id)">Make Main</a>

                                <a class="badge badge-primary ml-1" href="#"
                                   v-on:click.prevent="update(char.id)">Update character</a>
                                <a v-if="authChar && authChar.id !== char.id &&
                                         settings.allow_character_deletion === '1'"
                                   class="badge badge-danger ml-1"
                                   v-on:click.prevent="askDeleteChar(char.id, char.name)"
                                   href="#" title="Delete"><span role="img" class="fas fa-trash-alt"></span></a>

                                <br>

                                <span v-if="char.validToken" class="badge badge-success">Valid ESI token</span>
                                <span v-if="char.validToken === null" class="badge badge-warning">No ESI token</span>
                                <button v-if="char.validToken === false"
                                        type="button" class="btn btn-danger btn-sm mt-1"
                                        data-toggle="modal" data-target="#tokenModal">
                                    Invalid ESI token
                                </button>

                                <a v-if="char.validToken === false" :href="loginHost + '/login-alt'"
                                   class="ml-1 char-login-button">
                                    <img src="../assets/eve_sso-short.png" alt="LOG IN with EVE Online">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="player-hdl">
                        <h2>Account</h2>
                        <span class="text-muted">{{ player.name }} #{{ player.id }}</span>
                        <span v-if="player.status === 'managed'" class="text-muted">(manually managed)</span>
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
import $ from 'jquery';
import {PlayerApi} from 'neucore-js-client';
import TitleLogo from './Home--title-logo.vue';
import markdownIt from 'markdown-it';
import mdEmoji from 'markdown-it-emoji/light';
import mdSup from 'markdown-it-sup';
import mdSub from 'markdown-it-sub';
import mdIns from 'markdown-it-ins';
import mdAbbr from 'markdown-it-abbr';
import mdMark from 'markdown-it-mark';
import mdAttrs from 'markdown-it-attrs';
import Character from "../classes/Character";

export default {
    components: {
        TitleLogo
    },

    props: {
        route: Array,
        authChar: Object,
        player: Object,
        settings: Object,
    },

    data: function() {
        return {
            deactivated: false,
            charToDelete: null,
            markdownHtml: '',
            markdownLoginText: '',
            loginHost: '',
        }
    },

    computed: {
        playerRoles() {
            return this.player.roles.filter(role => role !== 'user');
        }
    },

    mounted: function() { // after "redirect" from another page
        window.scrollTo(0, 0);

        this.loginHost = this.$root.envVars.backendHost;

        const md = markdownIt({ typographer: true })
            .use(mdEmoji)
            .use(mdSup)
            .use(mdSub)
            .use(mdIns)
            .use(mdAbbr)
            .use(mdMark)
            .use(mdAttrs) // for classes, like .text-warning, .bg-primary
        ;
        md.renderer.rules.emoji = function(token, idx) {
            return `<span class="emoji">${token[idx].content}</span>`;
        };

        this.checkDeactivated();
        this.markdownHtml = md.render(this.settings.customization_home_markdown);
        this.markdownLoginText = md.render(this.settings.customization_login_text);
    },

    watch: {
        authChar: function() { // for primary login and logout
            this.checkDeactivated();
        },

        player: function() {
            if (! this.player) {
                return;
            }
            this.checkDeactivated();
        },
    },

    methods: {
        checkDeactivated: function() {
            if (! this.player) {
                this.deactivated = false;
                return;
            }

            const vm = this;
            new PlayerApi().groupsDisabled(function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.deactivated = data;
            });
        },

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
            $('#deleteCharModal').modal('show');
        },

        deleteChar() {
            const vm = this;
            (new Character(vm)).deleteCharacter(this.charToDelete.id, null, function() {
                vm.update(vm.authChar.id);
            });
            $('#deleteCharModal').modal('hide');
            this.charToDelete = null;
        },
    }
}
</script>

<style scoped>
    .player-hdl {
        position: relative;
    }
    .player-hdl h2 {
        display: inline-block;
        margin-right: 10px;
    }

    .char-login-button img {
        position: relative;
        top: 2px;
    }

    .groups-disabled {
        text-decoration: line-through;
    }
</style>
