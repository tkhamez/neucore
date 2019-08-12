<template>
<div class="container-fluid">

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
                        Are you sure you want to delete this character?<br>
                        <span class="text-warning">{{ charToDelete.name }}</span>
                    </p>
                    <p>If so, please choose a reason:</p>
                    <div class="form-group">
                        <div class="radio">
                            <label>
                                <input type="radio" name="reason" value="deleted-owner-changed" v-model="deleteReason">
                                Character Owner Changed<br>
                                <span class="text-muted small">
                                    Choose this if the character was sold to another player, check the
                                    <a href="https://forums.eveonline.com/c/marketplace/character-bazaar"
                                        target="_blank">Character Bazaar</a>.<br>
                                    Creates an appropriate "removed character" entry.
                                </span>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="reason" value="deleted-manually" v-model="deleteReason">
                                Simon says<br>
                                <span class="text-muted small">
                                     Creates a "removed character" entry with the reason "deleted-manually".
                                </span>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="reason" value="deleted-by-admin" v-model="deleteReason">
                                <span title="see no evil">&#x1F648</span>
                                <span title="hear no evil">&#x1F649</span>
                                <span title="speak no evil">&#x1F64A</span>
                                <span class="small"> - Nope</span>
                                <br>
                                <span class="text-muted small">
                                    Does <em>not</em> create a "removed character" entry.
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal"
                            :disabled="deleteReason === ''" v-on:click="deleteChar()">
                        DELETE character
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                        :class="{ active: isCharacterOfPlayer(char.character_id) }"
                        @click="loadPlayer(char.player_id)">
                        {{ char.character_name }}
                    </button>
                </div>
            </div>

            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Players by role
                </h3>
                <div class="card-body">
                    <select class="form-control" v-model="activeRole" @change="getPlayerByRole(activeRole)">
                        <option value=""> ... select a role</option>
                        <option v-for="role in availableRoles">{{ role }}</option>
                    </select>
                </div>
                <div class="list-group">
                     <a v-for="pr in playersRole" class="list-group-item list-group-item-action"
                        :class="{ active: playerEdit && playerEdit.id === pr.id }"
                        :href="'#UserAdmin/' + pr.id">
                        {{ pr.name }} #{{ pr.id }}
                    </a>
                </div>
            </div>
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Player accounts
                </h3>
                <div class="card-body">
                    <select class="form-control" v-model="activeList" @change="getPlayers(activeList)">
                        <option value=""> ... select a list</option>
                        <option value="withCharacters">with characters</option>
                        <option value="withoutCharacters">without characters</option>
                        <option value="invalidToken">invalid token</option>
                        <option value="noToken">no token</option>
                    </select>
                </div>
                <div class="list-group">
                    <a v-for="emptyAcc in playersChars"
                       class="list-group-item list-group-item-action"
                       :class="{ active: playerEdit && playerEdit.id === emptyAcc.id }"
                       :href="'#UserAdmin/' + emptyAcc.id">
                        {{ emptyAcc.name }} #{{ emptyAcc.id }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Player Account:
                    <span v-cloak v-if="playerEdit">
                        {{ playerEdit.name }} #{{ playerEdit.id }}
                    </span>
                    <span v-cloak v-if="playerEdit && playerEdit.characters.length > 0"
                          v-on:click="updateCharacters"
                          class="fas fa-sync update-char"
                          title="update characters"></span>
                </h3>

                <div v-cloak v-if="playerEdit" class="card-body">
                    <h4>Roles</h4>
                    <p v-cloak>
                        See
                        <a :href="settings.customization_github + '/blob/master/doc/API.md'"
                           target="_blank">doc/API.md</a>
                        for permissions for each role.
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
                            <span class="fas fa-minus-circle"></span>
                            remove
                        </button>
                        {{ role }}
                    </div>
                    <div v-if="playerEdit.roles.length === 1">No roles.</div>

                    <hr>

                    <h4>Account Status</h4>
                    <p class="text-warning">
                        All groups will be removed from the player account when the status is changed!
                    </p>
                    <div class="input-group mb-1">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="userAdminSetStatus">status</label>
                        </div>
                        <select class="custom-select" id="userAdminSetStatus"
                                v-model="playerEdit.status"
                                v-on:change="setAccountStatus()">
                            <option value="standard">standard</option>
                            <option value="managed">manually managed</option>
                        </select>
                    </div>
                    <p class="text-muted small" v-if="playerEdit.status === 'managed'">
                        Groups of this player can manually be changed on the
                        <a :href="'#PlayerGroupManagement/' + playerEdit.id">Player Groups Admin</a>
                        page.
                    </p>

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
                                <th>Last Update (GMT)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="character in playerEdit.characters">
                                <td>{{ character.id }}</td>
                                <td>
                                    <a :href="'https://evewho.com/pilot/' + character.name"
                                       title="Eve Who" target="_blank" >{{ character.name }}</a>
                                </td>
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
                                        {{ formatDate(character.lastUpdate) }}
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm mt-1"
                                            :disabled="authChar.id === character.id"
                                            v-on:click="askDeleteChar(character.id, character.name)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Group Membership</h4>
                    <p v-if="playerEditDeactivated" class="small text-info">
                        Groups for this account are disabled (or will be disabled soon)
                        because one or more characters do not have a valid ESI token.
                    </p>
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
                                <td :class="{ 'groups-disabled': playerEditDeactivated }">{{ group.name }}</td>
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

                    <h4>Removed Characters</h4>
                    <table class="table table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>Character ID</th>
                                <th>Character Name</th>
                                <th>Date Removed (GMT)</th>
                                <th>Reason</th>
                                <th>New Player</th>
                                <th>Deleted by</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="removedChar in playerEdit.removedCharacters">
                                <td>{{ removedChar.characterId }}</td>
                                <td>
                                    <a :href="'https://evewho.com/pilot/' + removedChar.characterName"
                                       title="Eve Who" target="_blank" >{{ removedChar.characterName }}</a>
                                </td>
                                <td>
                                    <span v-if="removedChar.removedDate">
                                        {{ formatDate(removedChar.removedDate) }}
                                    </span>
                                </td>
                                <td>{{ removedChar.reason }}</td>
                                <td>
                                    <a v-if="removedChar.newPlayerId"
                                       :href="'#UserAdmin/' + removedChar.newPlayerId">
                                        {{ removedChar.newPlayerName }} #{{ removedChar.newPlayerId }}
                                    </a>
                                </td>
                                <td>
                                    <a v-if="removedChar.deletedBy"
                                       :href="'#UserAdmin/' + removedChar.deletedBy.id">
                                        {{ removedChar.deletedBy.name }} #{{ removedChar.deletedBy.id }}
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
        authChar: [null, Object], // logged in character
        settings: Object,
    },

    data: function() {
        return {
            playersRole: [],
            playersChars: [],
            activeRole: '',
            activeList: '',
            playerId: null, // player ID from route
            playerEdit: null,// player being edited
            playerEditDeactivated: false,
            availableRoles: [
                'app-admin',
                'app-manager',
                'group-admin',
                'group-manager',
                'user-admin',
                'user-manager',
                'esi',
                'settings',
                'tracking',
            ],
            newRole: '',
            searchResult: [],
            charToDelete: null,
            deleteReason: '',
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.setPlayerId();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.setPlayerId();
        },

        route: function() {
            this.setPlayerId();
        },

        playerId: function() {
            if (this.playerId) {
                this.getPlayer();
            }
        },

        newRole: function() {
            if (this.playerEdit && this.newRole) {
                this.addRole(this.newRole);
                this.newRole = '';
            }
        },
    },

    methods: {
        setPlayerId: function() {
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },

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

        loadPlayer: function(playerId) {
            window.location.hash = '#UserAdmin/' + playerId;
        },

        onSearchResult: function(result) {
            this.searchResult = result;
            if (result.length > 0) {
                this.playersRole = [];
                this.playersChars = [];
                this.activeRole = '';
                this.activeList = '';
            }
        },

        getPlayerByRole: function(roleName) {
            const vm = this;
            if (roleName === '') {
                vm.playersRole = [];
                return;
            }
            vm.activeList = '';
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

        getPlayers: function(listName) {
            const vm = this;
            if (listName === '') {
                vm.playersChars = [];
                return;
            }
            vm.activeRole = '';
            vm.playersRole = [];
            vm.searchResult = [];
            const api = new this.swagger.PlayerApi();
            vm.loading(true);
            api[listName].apply(api, [function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.playersChars = data;
            }]);
        },

        getPlayer: function() {
            const vm = this;
            const api = new this.swagger.PlayerApi();

            vm.loading(true);
            api.showById(this.playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    vm.playerEdit = null;
                    return;
                }
                vm.playerEdit = data;
            });

            vm.loading(true);
            api.groupsDisabledById(this.playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.playerEditDeactivated = data;
            });
        },

        addRole: function(roleName) {
            this.changePlayerAttribute('addRole', roleName);
        },

        removeRole: function(roleName) {
            this.changePlayerAttribute('removeRole', roleName);
        },

        setAccountStatus: function() {
            this.changePlayerAttribute('setStatus', this.playerEdit.status);
        },

        changePlayerAttribute: function(method, param) {
            if (! this.playerEdit) {
                return;
            }
            const playerId = this.playerEdit.id;
            const api = new this.swagger.PlayerApi();
            const vm = this;
            vm.loading(true);
            api[method].apply(api, [playerId, param, function(error) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.getPlayer();
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
            }]);
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
                    if (vm.playerEdit.id === vm.player.id) {
                        vm.$root.$emit('playerChange');
                    }
                });
            });
        },

        askDeleteChar(characterId, characterName) {
            this.charToDelete = {
                id: characterId,
                name: characterName,
            };
            this.deleteReason = '';
            window.$('#deleteCharModal').modal('show');
        },

        deleteChar() {
            const vm = this;
            this.deleteCharacter(this.charToDelete.id, this.deleteReason, function() {
                vm.getPlayer();
                if (vm.playerEdit.id === vm.player.id) {
                    vm.updateCharacter(vm.authChar.id, function() {
                        vm.$root.$emit('playerChange');
                    });
                }
            });
            window.$('#deleteCharModal').modal('hide');
            this.charToDelete = null;
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
    .groups-disabled {
        text-decoration: line-through;
    }
</style>
