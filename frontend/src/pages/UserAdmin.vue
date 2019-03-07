<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>User Administration</h1>
        </div>
    </div>

    <div v-cloak v-if="player" class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Characters
                </h3>
                <div class="card-body">
                    <character-search :swagger="swagger" v-on:result="onSearchResult($event)"></character-search>
                    <span class="text-muted small">
                        Select a character to show it's player account.
                    </span>
                </div>
                <div class="list-group">
                    <button v-for="char in searchResult"
                        class="list-group-item list-group-item-action"
                        :class="{ active: isCharacterOfPlayer(char.id) }"
                        v-on:click="findPlayer(char.id)">
                        {{ char.name }}
                    </button>
                </div>
            </div>

            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Players by role
                </h3>
                <div class="card-body">
                    <button v-for="role in availableRoles"
                        type="button" class="btn mr-1 mb-1"
                        :class="{ 'btn-secondary': activeButton !== role, 'btn-primary': activeButton === role }"
                        v-on:click="getPlayerByRole(role)">
                        {{ role }}
                    </button>
                </div>
                <div class="list-group">
                     <a v-for="pr in playersRole" class="list-group-item list-group-item-action"
                        :class="{ active: playerEdit && playerEdit.id === pr.id }"
                        :href="'#UserAdmin/' + pr.id">
                        {{ pr.name }}
                    </a>
                </div>
            </div>
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Player accounts
                </h3>
                <div class="card-body">
                    <button type="button" class="btn mr-1 mb-1"
                            :class="{
                                'btn-secondary': activeButton !== 'withCharacters',
                                'btn-primary': activeButton === 'withCharacters'
                            }"
                            v-on:click="getPlayers('withCharacters')">
                        with characters
                    </button>
                    <button type="button" class="btn mr-1 mb-1"
                            :class="{
                                'btn-secondary': activeButton !== 'withoutCharacters',
                                'btn-primary': activeButton === 'withoutCharacters'
                            }"
                            v-on:click="getPlayers('withoutCharacters')">
                        without characters
                    </button>
                </div>
                <div class="list-group">
                    <a v-for="emptyAcc in playersChars"
                       class="list-group-item list-group-item-action"
                       :class="{ active: playerEdit && playerEdit.id === emptyAcc.id }"
                       :href="'#UserAdmin/' + emptyAcc.id">
                        {{ emptyAcc.name }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Player Account:
                    <span v-cloak v-if="playerEdit">
                        {{ playerEdit.name }}
                    </span>
                    <span v-cloak v-if="playerEdit"
                          v-on:click="updateCharacters"
                          class="fas fa-sync update-char"
                          title="update characters"></span>
                </h3>

                <div v-cloak v-if="playerEdit" class="card-body">
                    <h4>Roles</h4>
                    <p>
                        See
                        <a href="https://github.com/bravecollective/brvneucore/blob/master/doc/API.md" target="_blank">
                            doc/API.md</a> for permissions for each role.
                    </p>
                    <div class="input-group mb-1">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="userAdminSelectRole">Add role</label>
                        </div>
                        <select class="custom-select" v-model="newRole" id="userAdminSelectRole">
                            <option value="">Select role ...</option>
                            <option v-for="role in availableRoles"
                                v-if="! hasRole(role, playerEdit)" v-bind:value="role">
                                {{ role }}
                            </option>
                        </select>
                    </div>

                    <div v-for="role in playerEdit.roles" v-if="role !== 'user'" class="list-group-item">
                        <button type="button" class="btn btn-danger mr-5"
                                :disabled="role === 'user-admin' && playerEdit.id === player.id"
                                v-on:click="removeRole(role)">
                            <i class="fas fa-minus-circle"></i>
                            remove
                        </button>
                        {{ role }}
                    </div>
                    <div v-if="playerEdit.roles.length === 1">No roles.</div>

                    <hr>

                    <h4>Characters</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Corporation</th>
                                <th>Alliance</th>
                                <th>Main</th>
                                <th>Valid Token</th>
                                <th>Last Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="character in playerEdit.characters">
                                <td>{{ character.id }}</td>
                                <td>{{ character.name }}</td>
                                <td>
                                    <span v-if="character.corporation">
                                        [{{ character.corporation.ticker }}]
                                        {{ character.corporation.name }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="character.corporation && character.corporation.alliance">
                                        [{{ character.corporation.alliance.ticker }}]
                                        {{ character.corporation.alliance.name }}
                                    </span>
                                </td>
                                <td>{{ character.main }}</td>
                                <td>{{ character.validToken }}</td>
                                <td>
                                    <span v-if="character.lastUpdate">
                                        {{ character.lastUpdate.toUTCString() }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Group Membership</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Visibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in playerEdit.groups">
                                <td>{{ group.id }}</td>
                                <td>{{ group.name }}</td>
                                <td>{{ group.visibility }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Group Manager</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Visibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="managerGroup in playerEdit.managerGroups">
                                <td>{{ managerGroup.id }}</td>
                                <td>{{ managerGroup.name }}</td>
                                <td>{{ managerGroup.visibility }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>App Manager</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="managerApp in playerEdit.managerApps">
                                <td>{{ managerApp.id }}</td>
                                <td>{{ managerApp.name }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Group Applications</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Visibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="application in playerEdit.applications">
                                <td>{{ application.id }}</td>
                                <td>{{ application.name }}</td>
                                <td>{{ application.visibility }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Removed Characters</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>Character ID</th>
                                <th>Character Name</th>
                                <th>Date Removed</th>
                                <th>Action</th>
                                <th>New Player</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="removedCharacter in playerEdit.removedCharacters">
                                <td>{{ removedCharacter.characterId }}</td>
                                <td>{{ removedCharacter.characterName }}</td>
                                <td>
                                    <span v-if="removedCharacter.removedDate">
                                        {{ removedCharacter.removedDate.toUTCString() }}
                                    </span>
                                </td>
                                <td>{{ removedCharacter.action }}</td>
                                <td>
                                    <a v-if="removedCharacter.newPlayerId"
                                       :href="'#UserAdmin/' + removedCharacter.newPlayerId">
                                        {{ removedCharacter.newPlayerName }}
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import CharacterSearch from '../components/CharacterSearch.vue';

module.exports = {
    components: {
        CharacterSearch,
    },

    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object], // logged in player
    },

    data: function() {
        return {
            playersRole: [],
            playersChars: [],
            activeButton: '',
            playerId: null, // player ID from route
            playerEdit: null,// player being edited
            availableRoles: [
                'app-admin',
                'app-manager',
                'group-admin',
                'group-manager',
                'user-admin',
                'esi',
                'settings',
                'tracking',
            ],
            newRole: '',
            searchResult: [],
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },

        route: function() {
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },

        playerId: function() {
            if (this.playerId) {
                this.getPlayer();
            }
        },

        newRole: function() {
            if (this.playerEdit && this.newRole) {
                this.addRole(this.playerEdit.id, this.newRole);
                this.newRole = '';
            }
        },
    },

    methods: {
        isCharacterOfPlayer: function(charId) {
            if (! this.playerEdit) {
                return false;
            }
            for (let char of this.playerEdit.characters) {
                if (char.id === charId) {
                    return true;
                }
            }
            return false;
        },

        findPlayer: function(characterId) {
            const vm = this;
            vm.loading(true);
            new this.swagger.CharacterApi().findPlayerOf(characterId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                window.location.hash = '#UserAdmin/' + data.id;
            });
        },

        onSearchResult: function(result) {
            this.searchResult = result;
            if (result.length > 0) {
                this.playersRole = [];
                this.playersChars = [];
                this.activeButton = '';
            }
        },

        getPlayerByRole: function(roleName) {
            if (roleName === this.activeButton) {
                this.activeButton = '';
                this.playersRole = [];
                return;
            }

            const vm = this;
            vm.activeButton = roleName;
            vm.playersChars = [];
            vm.searchResult = [];
            vm.loading(true);
            new this.swagger.PlayerApi().withRole(roleName, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.playersRole = data;
            });
        },

        getPlayers: function(withOutChars) {
            if (withOutChars === this.activeButton) {
                this.activeButton = '';
                this.playersChars = [];
                return;
            }

            const vm = this;
            vm.activeButton = withOutChars;
            vm.playersRole = [];
            vm.searchResult = [];
            const api = new this.swagger.PlayerApi();
            vm.loading(true);
            api[withOutChars].apply(api, [function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.playersChars = data;
            }]);
        },

        getPlayer: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().showById(this.playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    vm.playerEdit = null;
                    return;
                }
                data.roles = vm.fixRoles(data.roles);
                vm.playerEdit = data;
            });
        },

        addRole: function(playerId, roleName) {
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().addRole(playerId, roleName, function(error) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.getPlayer();
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
            });
        },

        removeRole: function(roleName) {
            if (! this.playerEdit) {
                return;
            }
            const playerId = this.playerEdit.id;
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().removeRole(playerId, roleName, function(error) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.getPlayer();
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
            });
        },

        updateCharacters: function() {
            if (! this.playerEdit) {
                return;
            }
            const vm = this;
            const charsCount = this.playerEdit.characters.length;
            let charsUpdated = 0;
            this.playerEdit.characters.forEach(function(character) {
                vm.updateCharacter(character.id, function() {
                    charsUpdated ++;
                    if (charsUpdated < charsCount) {
                        return;
                    }
                    vm.getPlayer();
                    if (vm.playerEdit.id === vm.playerId) {
                        vm.$root.$emit('playerChange');
                    }
                });
            });
        },
    },
}
</script>

<style scoped>
    table {
        font-size: 90%;
    }
    .update-char {
        float: right;
        cursor: pointer;
    }
</style>
