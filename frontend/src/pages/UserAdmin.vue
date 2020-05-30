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
                                       target="_blank" rel="noopener noreferrer">Character Bazaar</a>.<br>
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
                <h3 class="card-header">Characters</h3>
                <div class="card-body">
                    <!--suppress HtmlUnknownTag -->
                    <character-search v-on:result="onSearchResult($event)"></character-search>
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
                    <label for="roleList">Players by role</label>
                </h3>
                <div class="card-body">
                    <select class="form-control" id="roleList"
                            v-model="activeRole" @change="getPlayerByRole(activeRole)">
                        <option value="">select a role</option>
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
                    <label for="accountList">Player accounts ...</label>
                </h3>
                <div class="card-body">
                    <select class="form-control" id="accountList"
                            v-model="activeList" @change="getPlayers(activeList)">
                        <option value="">select a list</option>
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
                          role="img" class="fas fa-sync update-char"
                          title="update characters"></span>
                </h3>

                <div v-cloak v-if="playerEdit" class="card-body">
                    <h4>Roles</h4>
                    <p v-cloak>
                        See
                        <a :href="settings.customization_github + '/blob/master/doc/API.md'"
                           target="_blank" rel="noopener noreferrer">doc/API.md</a>
                        for permissions for each role.
                    </p>
                    <div class="input-group mb-1">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="userAdminSelectRole">Add role</label>
                        </div>
                        <select class="custom-select" v-model="newRole" id="userAdminSelectRole">
                            <option value="">Select role ...</option>
                            <option v-for="role in availableRoles"
                                    v-if="! hasRole(role, playerEdit) && autoRoles.indexOf(role) === -1"
                                    v-bind:value="role">
                                {{ role }}
                            </option>
                        </select>
                    </div>

                    <table class="table table-hover nc-table-sm" aria-describedby="Roles">
                        <thead class="thead-dark">
                            <tr>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in playerEdit.roles" v-if="role !== 'user'">
                                <td>{{ role }}</td>
                                <td>
                                    <button v-if="autoRoles.indexOf(role) === -1"
                                            type="button" class="btn btn-danger btn-sm mr-5"
                                            :disabled="role === 'user-admin' && playerEdit.id === player.id"
                                            v-on:click="removeRole(role)">
                                        <span role="img" class="fas fa-minus-circle"></span>
                                        remove
                                    </button>
                                    <span v-if="autoRoles.indexOf(role) !== -1" class="text-muted">
                                        automatically assigned
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <hr>

                    <h4>Account Status</h4>
                    <p>
                        {{ playerEdit.status }}
                        <span v-if="hasRole('user-manager')" class="text-muted">
                            (change here:
                            <a :href="'#PlayerGroupManagement/' + playerEdit.id">Player Groups Management</a>)
                        </span>
                    </p>

                    <hr>

                    <h4>Characters</h4>
                    <div class="table-responsive">
                        <table class="table table-hover nc-table-sm" aria-describedby="Characters">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Corporation</th>
                                    <th scope="col">Alliance</th>
                                    <th scope="col">Main</th>
                                    <th scope="col">Created*</th>
                                    <th scope="col">Token status</th>
                                    <th scope="col">Token status changed*</th>
                                    <th scope="col">Last updated*</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="character in playerEdit.characters">
                                    <td>{{ character.id }}</td>
                                    <td>
                                        <a :href="'https://evewho.com/character/' + character.id"
                                           title="Eve Who" target="_blank" rel="noopener noreferrer">
                                            {{ character.name }}
                                        </a>
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
                                    <td>
                                        <span v-if="character.created">
                                            {{ formatDate(character.created) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span v-if="character.validToken">valid</span>
                                        <span v-if="character.validToken === false">invalid</span>
                                        <span v-if="character.validToken === null">n/a</span>
                                    </td>
                                    <td>
                                        <span v-if="character.validTokenTime">
                                            {{ formatDate(character.validTokenTime) }}
                                        </span>
                                    </td>
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
                    </div>
                    <p class="small text-muted">* Time is GMT</p>

                    <h4>Group Membership</h4>
                    <p v-if="playerEditDeactivated" class="small text-info">
                        Groups for this account are disabled (or will be disabled soon)
                        because one or more characters do not have a valid ESI token.
                    </p>
                    <table class="table table-hover nc-table-sm" aria-describedby="Member of groups">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Visibility</th>
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
                    <table class="table table-hover nc-table-sm" aria-describedby="Manager of groups">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Visibility</th>
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
                    <table class="table table-hover nc-table-sm" aria-describedby="Manager of apps">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="managerApp in playerEdit.managerApps">
                                <td>{{ managerApp.id }}</td>
                                <td>{{ managerApp.name }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div>
                        <h4>Moved Characters</h4>
                        <div class="table-responsive">
                            <table class="table table-hover nc-table-sm" aria-describedby="'Moved Characters'">
                                <thead class="thead-dark">
                                    <tr>
                                        <th scope="col">Character ID</th>
                                        <th scope="col">Character Name</th>
                                        <th scope="col">Date moved (GMT)</th>
                                        <th scope="col">Reason</th>
                                        <th scope="col">Old/New Player</th>
                                        <th scope="col">Deleted by</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="movedCharacter in characterMovements">
                                        <td>{{ movedCharacter.characterId }}</td>
                                        <td>
                                            <a :href="'https://evewho.com/character/' + movedCharacter.characterId"
                                               title="Eve Who" target="_blank" rel="noopener noreferrer">
                                                {{ movedCharacter.characterName }}
                                            </a>
                                        </td>
                                        <td>
                                            <span v-if="movedCharacter.removedDate">
                                                {{ formatDate(movedCharacter.removedDate) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span v-if="movedCharacter.reason === 'moved'">
                                                <span v-if="movedCharacter.player.id !== playerEdit.id">incoming</span>
                                                <span v-else>removed</span>
                                            </span>
                                            <span v-else>{{ movedCharacter.reason }}</span>

                                        </td>
                                        <td>
                                            <a v-if="movedCharacter.player.id !== playerEdit.id"
                                               :href="'#UserAdmin/' + movedCharacter.player.id">
                                                {{ movedCharacter.player.name }} #{{ movedCharacter.player.id }}
                                            </a>
                                            <a v-if="movedCharacter.newPlayerId &&
                                                     movedCharacter.newPlayerId !== playerEdit.id"
                                               :href="'#UserAdmin/' + movedCharacter.newPlayerId">
                                                {{ movedCharacter.newPlayerName }} #{{ movedCharacter.newPlayerId }}
                                            </a>
                                        </td>
                                        <td>
                                            <a v-if="movedCharacter.deletedBy"
                                               :href="'#UserAdmin/' + movedCharacter.deletedBy.id">
                                                {{ movedCharacter.deletedBy.name }} #{{ movedCharacter.deletedBy.id }}
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
    </div>
</div>
</template>

<script>
import $ from 'jquery';
import {PlayerApi} from 'neucore-js-client';
import CharacterSearch from '../components/CharacterSearch.vue';
import Character from "../classes/Character";

export default {
    components: {
        CharacterSearch,
    },

    props: {
        route: Array,
        player: Object, // logged in player
        authChar: Object, // logged in character
        settings: Object,
    },

    data: function() {
        return {
            playersRole: [],
            playersChars: [],
            activeRole: '',
            activeList: '',
            playerId: null, // player ID from route
            characterMovements: [],

            /**
             * {@link module:model/Player}
             */
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
                'tracking-admin',
                'watchlist',
                'watchlist-manager',
                'watchlist-admin',
            ],
            autoRoles: [
                'app-manager',
                'group-manager',
                'tracking',
                'watchlist',
            ],
            newRole: '',
            searchResult: [],
            charToDelete: null,
            deleteReason: '',
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        this.setPlayerId();
    },

    watch: {
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
            for (const char of this.playerEdit.characters) {
                if (char.id === charId) {
                    return true;
                }
            }
            return false;
        },

        loadPlayer: function(playerId) {
            window.location.hash = `#UserAdmin/${playerId}`;
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
            new PlayerApi().withRole(roleName, function(error, data) {
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
            const api = new PlayerApi();
            api[listName].apply(api, [function(error, data) {
                if (error) {
                    return;
                }
                vm.playersChars = data;
            }]);
        },

        getPlayer: function() {
            const vm = this;
            const api = new PlayerApi();

            api.showById(this.playerId, function(error, data) {
                if (error) {
                    vm.playerEdit = null;
                    return;
                }
                vm.playerEdit = data;
                vm.characterMovements = buildCharacterMovements(data);
            });

            api.groupsDisabledById(this.playerId, function(error, data) {
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

        changePlayerAttribute: function(method, param) {
            if (! this.playerEdit) {
                return;
            }
            const playerId = this.playerEdit.id;
            const api = new PlayerApi();
            const vm = this;
            api[method].apply(api, [playerId, param, function(error) {
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
            (new Character(this)).updatePlayer(this.playerEdit, this.getPlayer);
        },

        askDeleteChar(characterId, characterName) {
            this.charToDelete = {
                id: characterId,
                name: characterName,
            };
            this.deleteReason = '';
            $('#deleteCharModal').modal('show');
        },

        deleteChar() {
            const vm = this;
            const character = (new Character(vm));
            character.deleteCharacter(this.charToDelete.id, this.deleteReason, function() {
                vm.getPlayer();
                if (vm.playerEdit.id === vm.player.id) {
                    character.updateCharacter(vm.authChar.id, function() {
                        vm.$root.$emit('playerChange');
                    });
                }
            });
            $('#deleteCharModal').modal('hide');
            this.charToDelete = null;
        },
    },
}

function buildCharacterMovements(data) {
    const movements = [];
    for (const removed of data.removedCharacters) {
        movements.push(removed);
    }
    for (const incoming of data.incomingCharacters) {
        movements.push(incoming);
    }
    return movements.sort((a, b) => a.removedDate - b.removedDate);
}
</script>

<style scoped>
    .update-char {
        float: right;
        cursor: pointer;
    }
    .groups-disabled {
        text-decoration: line-through;
    }
</style>
