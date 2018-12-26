<template>
    <div class="container-fluid">

        <div v-cloak class="modal fade" id="tokenModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Invalid ESI token</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        The ESI token for this character is no longer valid.<br>
                        Please use the EVE login button and login with this character
                        again to create a new token.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-cloak class="modal fade" id="deleteCharModal">
            <div class="modal-dialog">
                <div v-cloak v-if="charToDelete" class="modal-content">
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

        <div class="jumbotron mt-3">
            <span v-cloak id="preview" v-if="preview">PREVIEW</span>
            <a href="https://www.bravecollective.com/">
                <img src="/images/brave_300.png" class="float-right" alt="Brave logo"
                    title="Brave Collective: What's your fun per hour?">
            </a>
            <h1 class="display-3">BRAVE Core</h1>
            <p class="lead">
                This site provides access to alliance services such as Mumble, Wiki and Forum.
            </p>
            <hr class="my-4">

            <div v-cloak v-if="! authChar">
                <p class="lead">
                    Click the button below to login through <i>EVE Online SSO</i>.
                </p>
                <a href="/login">
                    <img src="/images/EVE_SSO_Login_Buttons_Large_Black.png" alt="LOG IN with EVE Online">
                </a>
                <p class="small">
                    <br>
                    Learn more about the security of <i>EVE Online SSO</i> in this
                    <a href="https://www.eveonline.com/article/eve-online-sso-and-what-you-need-to-know/"
                        target="_blank">dev-blog</a> article.
                </p>
            </div>

            <div v-cloak v-if="authChar">
                <p>Please add all your characters by logging in with EVE SSO.</p>
                <p><a href="/login-alt"><img src="/images/eve_sso.png" alt="LOG IN with EVE Online"></a></p>
            </div>
        </div>

        <div v-cloak v-if="deactivated" class="alert alert-danger">
            Groups for this account are <strong>disabled</strong> (or will be disabled soon)
            because one or more characters do not have a valid ESI token.
        </div>

        <div v-cloak v-if="this.player">
            <div class="row">
                <div class="col-lg-8">
                    <h2>Characters</h2>
                    <div class="card-columns">
                        <div v-for="char in player.characters" class="card border-secondary bg-light">
                            <div class="card-header">
                                <img :src="'https://image.eveonline.com/Character/'+char.id+'_32.jpg'"
                                    alt="Character Portrait">
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
                                <span v-if="char.main" class="fas fa-star text-warning mr-2" title="Main"></span>
                                <button v-if="char.validToken === false"
                                        type="button" class="btn btn-danger btn-sm mt-1"
                                        data-toggle="modal" data-target="#tokenModal">
                                    Invalid ESI token
                                </button>
                                <button v-if="accountDeactivation && char.validToken === null"
                                        disabled type="button" class="btn btn-warning btn-sm mt-1">
                                    No ESI token
                                </button>
                                <button v-if="! char.main && char.validToken !== false"
                                        type="button" class="btn btn-primary btn-sm mt-1"
                                        v-on:click="makeMain(char.id)">
                                    <i class="fas fa-star"></i>
                                    Make Main
                                </button>
                                <button type="button" class="btn btn-primary btn-sm mt-1"
                                        v-on:click="update(char.id)">
                                    <i class="fas fa-sync small"></i>
                                    Update
                                </button>
                                <button v-if="authChar.id !== char.id && deleteButton"
                                        type="button" class="btn btn-danger btn-sm mt-1"
                                        v-on:click="askDeleteChar(char.id, char.name)">
                                    <i class="far fa-trash-alt small"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="player-hdl">
                        <h2>Account</h2>
                        <span class="text-muted">Name: {{ player.name }}, ID: {{ player.id }}</span>
                    </div>
                    <div class="card border-secondary mb-3">
                        <h3 class="card-header">Groups</h3>
                        <ul class="list-group list-group-flush" :class="{ 'groups-disabled': deactivated }">
                            <li v-for="group in player.groups" class="list-group-item">
                                {{ group.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-cloak v-if="player.managerGroups && player.managerGroups.length > 0"
                            class="card border-secondary mb-3" >
                        <h3 class="card-header">Group Manager</h3>
                        <ul class="list-group list-group-flush">
                            <li v-for="group in player.managerGroups" class="list-group-item">
                                {{ group.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-cloak v-if="player.managerApps && player.managerApps.length > 0"
                            class="card border-secondary mb-3" >
                        <h3 class="card-header">App Manager</h3>
                        <ul class="list-group list-group-flush">
                            <li v-for="app in player.managerApps" class="list-group-item">
                                {{ app.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-cloak v-if="player.roles.length > 1" class="card border-secondary mb-3" >
                        <h3 class="card-header">Roles</h3>
                        <ul class="list-group list-group-flush">
                            <li v-for="role in player.roles" class="list-group-item">
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
module.exports = {
    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        authChar: [null, Object],
        player: [null, Object],
        settings: Array,
    },

    data: function() {
        return {
            preview: false,
            deleteButton: false,
            accountDeactivation: false,
            deactivated: false,
            charToDelete: null,
        }
    },

    mounted: function() { // after "redirect" from another page
        this.adjustSettings();
        this.checkDeactivated();
    },

    watch: {
        authChar: function() { // for primary login and logout
            this.checkDeactivated();
        },

        player: function() {
            if (! this.player) {
                return;
            }
            const vm = this;
            this.player.characters.forEach(function(character) {
                if (character.lastUpdate === null) {
                    vm.update(character.id);
                }
            });
            this.checkDeactivated();
        },

        settings: function() {
            this.adjustSettings();
            this.checkDeactivated();
        }
    },

    methods: {
        adjustSettings: function() {
            for (let variable of this.settings) {
                if (variable.name === 'show_preview_banner') {
                    this.preview = variable.value === '1';
                }
                if (variable.name === 'allow_character_deletion') {
                    this.deleteButton = variable.value === '1';
                }
                if (variable.name === 'groups_require_valid_token') {
                    this.accountDeactivation = variable.value === '1';
                }
            }
        },

        checkDeactivated: function() {
            this.deactivated = false;

            if (! this.accountDeactivation) {
                return;
            }
            if (! this.player) {
                return;
            }

            for (let character of this.player.characters) {
                if (! character.validToken) {
                    this.deactivated = true;
                    return;
                }
            }
        },

        makeMain: function(characterId) {
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().setMain(characterId, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.$root.$emit('playerChange');
            });
        },

        update: function(characterId) {
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
                vm.$root.$emit('playerChange');
            });
        },

        askDeleteChar(characterId, characterName) {
            this.charToDelete = {
                id: characterId,
                name: characterName,
            };
            window.jQuery('#deleteCharModal').modal('show');
        },

        deleteChar() {
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().deleteCharacter(this.charToDelete.id, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    vm.message('Deletion denied.', 'error');
                    return;
                }
                vm.message('Deleted character.', 'success');
                vm.update(vm.authChar.id);
            });
            this.charToDelete = null;
        },
    }
}
</script>

<style scoped>
    .jumbotron {
        position: relative;
        min-height: 430px;
    }

    .player-hdl {
        position: relative;
    }
    .player-hdl h2 {
        display: inline-block;
        margin-right: 10px;
    }

    #preview {
        position: absolute;
        right: 20px;
        font-size: 3em;
        font-family: impact, sans-serif;
        letter-spacing: 30px;
        color: #dd3333;
        text-shadow: 1px 1px black;
        padding-left: 30px;
        transform: rotate(10deg);
        background-color: rgba(200, 200, 200, .5);
    }

    .groups-disabled {
        color: red;
        text-decoration: line-through;
    }
</style>
