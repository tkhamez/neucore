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
            <a v-cloak :href="settings.customization_website">
                <img v-if="settings.customization_home_logo" class="float-right" alt="Logo"
                    :src="settings.customization_home_logo">
            </a>
            <h1 v-cloak class="display-3">{{ settings.customization_home_headline }}</h1>
            <p v-cloak class="lead">{{ settings.customization_home_description }}</p>
            <hr class="my-4">

            <div v-cloak v-if="! authChar">
                <p>Click the button below to login through <i>EVE Online SSO</i>.</p>
                <a href="/login">
                    <img src="/static/EVE_SSO_Login_Buttons_Large_Black.png" alt="LOG IN with EVE Online">
                </a>
                <p class="small">
                    <br>
                    Learn more about the security of <i>EVE Online SSO</i> in this
                    <a href="https://www.eveonline.com/article/eve-online-sso-and-what-you-need-to-know/"
                        target="_blank">dev-blog</a> article.
                </p>
            </div>

            <div v-cloak v-if="authChar">
                <p>Add your other characters by logging in with EVE SSO.</p>
                <p><a href="/login-alt"><img src="/static/eve_sso.png" alt="LOG IN with EVE Online"></a></p>
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
                                <button v-if="char.validToken === null"
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
                                <button v-cloak type="button" class="btn btn-danger btn-sm mt-1"
                                        v-if="authChar.id !== char.id && settings.allow_character_deletion === '1'"
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
        settings: Object,
    },

    data: function() {
        return {
            deactivated: false,
            charToDelete: null,
        }
    },

    mounted: function() { // after "redirect" from another page
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
            this.checkDeactivated();
        }
    },

    methods: {
        checkDeactivated: function() {
            if (! this.player) {
                return;
            }

            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().groupsDisabled(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.deactivated = data;
            });
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
            this.updateCharacter(characterId, function() {
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
            this.deleteCharacter(this.charToDelete.id, null, function() {
                vm.update(vm.authChar.id);
            });
            window.jQuery('#deleteCharModal').modal('hide');
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

    .groups-disabled {
        text-decoration: line-through;
    }
</style>
