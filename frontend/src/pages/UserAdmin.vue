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
                <h4 class="card-header">Characters</h4>
                <div class="card-body">
                    <!--suppress HtmlUnknownTag -->
                    <character-search v-on:result="onSearchResult($event)" :admin="true"></character-search>
                    <span class="text-muted small">
                        Select a character to show it's player account.
                    </span>
                </div>
                <div class="list-group">
                    <button v-for="char in searchResult"
                        class="list-group-item list-group-item-action"
                        :class="{ active: belongsToActivePlayer(char) }"
                        @click="loadPlayer(char.player_id)">
                        {{ char.character_name }}
                    </button>
                </div>
            </div>

            <div class="card border-secondary mb-3" >
                <h4 class="card-header"><label for="roleList">Players by role</label></h4>
                <div class="card-body">
                    <p v-cloak>
                        See
                        <a :href="settings.customization_github + '/blob/master/doc/API.md'"
                           target="_blank" rel="noopener noreferrer">doc/API.md</a>
                        for permissions for each role.
                    </p>
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
                <h4 class="card-header"><label for="accountList">Player accounts ...</label></h4>
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
                    <div class="input-group mb-1">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="userAdminSelectRole">Add role</label>
                        </div>
                        <select class="custom-select" v-model="newRole" id="userAdminSelectRole">
                            <option value="">Select role ...</option>
                            <option v-for="role in assignableRoles" v-bind:value="role">
                                {{ role }}
                            </option>
                        </select>
                    </div>

                    <table class="table table-hover nc-table-sm" aria-describedby="Roles">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Role</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in playerEditRoles">
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
                            <thead class="thead-light">
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
                                        <character-name-changes :character="character"></character-name-changes>
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

                    <div>
                        <h4>Moved Characters</h4>
                        <div class="table-responsive">
                            <table class="table table-hover nc-table-sm" aria-describedby="'Moved Characters'">
                                <thead class="thead-light">
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
                                        <td>{{ movedCharacter.reason }}</td>
                                        <td>
                                            <a v-if="movedCharacter.playerName"
                                               :href="'#UserAdmin/' + movedCharacter.playerId">
                                                {{ movedCharacter.playerName }} #{{ movedCharacter.playerId }}
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

                    <h4>Group Membership</h4>
                    <p v-if="playerEditDeactivated" class="small text-info">
                        Groups for this account are disabled (or will be disabled soon)
                        because one or more characters do not have a valid ESI token.
                    </p>
                    <table class="table table-hover nc-table-sm" aria-describedby="Member of groups">
                        <thead class="thead-light">
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
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Visibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="managerGroup in playerEdit.managerGroups">
                                <td>{{ managerGroup.id }}</td>
                                <td>
                                    <a v-if="hasRole('group-admin')"
                                       :href="'#GroupAdmin/'+managerGroup.id+'/managers'"
                                       title="Group Administration">{{ managerGroup.name }}</a>
                                    <span v-else>{{ managerGroup.name }}</span>
                                </td>
                                <td>{{ managerGroup.visibility }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>App Manager</h4>
                    <table class="table table-hover nc-table-sm" aria-describedby="Manager of apps">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="managerApp in playerEdit.managerApps">
                                <td>{{ managerApp.id }}</td>
                                <td>
                                    <a v-if="hasRole('app-admin')"
                                       :href="'#AppAdmin/'+managerApp.id+'/managers'"
                                       title="App Administration">{{ managerApp.name }}</a>
                                    <span v-else>{{ managerApp.name }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Service Accounts</h4>
                    <table class="table table-hover nc-table-sm" aria-describedby="Manager of apps">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Service</th>
                                <th scope="col">Character</th>
                                <th scope="col">Username</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="serviceAccount in playerEdit.serviceAccounts">
                                <td>[{{ serviceAccount.serviceId }}] {{ serviceAccount.serviceName }}</td>
                                <td>[{{ serviceAccount.characterId }}] {{ characterName(serviceAccount.characterId) }}</td>
                                <td>{{ serviceAccount.username }}</td>
                                <td>{{ serviceAccount.status }}</td>
                            </tr>
                        </tbody>
                    </table>

                </div> <!-- card-body -->
            </div> <!-- card -->
        </div> <!-- col -->
    </div> <!-- row -->
</div>
</template>

<script>
import $ from 'jquery';
import {PlayerApi} from 'neucore-js-client';
import CharacterSearch from '../components/CharacterSearch.vue';
import CharacterNameChanges from '../components/CharacterNameChanges.vue';
import Character from "../classes/Character";

export default {
    components: {
        CharacterSearch,
        CharacterNameChanges,
    },

    props: {
        route: Array,
        player: Object, // logged in player
        authChar: Object, // logged in character
        settings: Object,
    },

    data() {
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
                'user-chars',
                'esi',
                'service-admin',
                'statistics',
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
                'watchlist-manager',
            ],
            newRole: '',
            searchResult: [],
            charToDelete: null,
            deleteReason: '',
        }
    },

    computed: {
        assignableRoles() {
            return this.availableRoles.filter(role =>
                !this.hasRole(role, this.playerEdit) &&
                this.autoRoles.indexOf(role) === -1
            );
        },

        playerEditRoles() {
            return this.playerEdit.roles.filter(role => role !== 'user');
        },
    },

    mounted() {
        window.scrollTo(0,0);
        this.setPlayerId();
    },

    watch: {
        route() {
            this.setPlayerId();
        },

        playerId() {
            if (this.playerId) {
                this.getPlayer();
            }
        },

        newRole() {
            if (this.playerEdit && this.newRole) {
                this.addRole(this.newRole);
                this.newRole = '';
            }
        },
    },

    methods: {
        setPlayerId() {
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },

        belongsToActivePlayer(charId) {
            if (! this.playerEdit) {
                return false;
            }
            return this.playerEdit.id === charId.player_id;
        },

        characterName(characterId) {
            for (const character of this.playerEdit.characters) {
                if (characterId === character.id) {
                    return character.name;
                }
            }
            return '';
        },

        loadPlayer(playerId) {
            window.location.hash = `#UserAdmin/${playerId}`;
        },

        onSearchResult(result) {
            this.searchResult = result;
            if (result.length > 0) {
                this.playersRole = [];
                this.playersChars = [];
                this.activeRole = '';
                this.activeList = '';
            }
        },

        getPlayerByRole(roleName) {
            const vm = this;
            if (roleName === '') {
                vm.playersRole = [];
                return;
            }
            vm.activeList = '';
            vm.playersChars = [];
            vm.searchResult = [];
            new PlayerApi().withRole(roleName, (error, data) => {
                if (error) {
                    return;
                }
                vm.playersRole = data;
            });
        },

        getPlayers(listName) {
            const vm = this;
            if (listName === '') {
                vm.playersChars = [];
                return;
            }
            vm.activeRole = '';
            vm.playersRole = [];
            vm.searchResult = [];
            const api = new PlayerApi();
            api[listName].apply(api, [(error, data) => {
                if (error) {
                    return;
                }
                vm.playersChars = data;
            }]);
        },

        getPlayer() {
            const vm = this;
            const api = new PlayerApi();

            api.showById(this.playerId, (error, data) => {
                if (error) {
                    vm.playerEdit = null;
                    return;
                }
                vm.playerEdit = data;
                vm.characterMovements = vm.buildCharacterMovements(data);
            });

            api.groupsDisabledById(this.playerId, (error, data) => {
                if (error) {
                    return;
                }
                vm.playerEditDeactivated = data;
            });
        },

        addRole(roleName) {
            this.changePlayerAttribute('addRole', roleName);
        },

        removeRole(roleName) {
            this.changePlayerAttribute('removeRole', roleName);
        },

        changePlayerAttribute(method, param) {
            if (! this.playerEdit) {
                return;
            }
            const playerId = this.playerEdit.id;
            const api = new PlayerApi();
            const vm = this;
            api[method].apply(api, [playerId, param, (error) => {
                if (error) {
                    return;
                }
                vm.getPlayer();
                if (playerId === vm.player.id) {
                    vm.emitter.emit('playerChange');
                }
            }]);
        },

        updateCharacters() {
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
            character.deleteCharacter(this.charToDelete.id, this.deleteReason, () => {
                vm.getPlayer();
                if (vm.playerEdit.id === vm.player.id) {
                    character.updateCharacter(vm.authChar.id, () => {
                        vm.emitter.emit('playerChange');
                    });
                }
            });
            $('#deleteCharModal').modal('hide');
            this.charToDelete = null;
        },
    },
}
</script>

<!--suppress CssUnusedSymbol -->
<style type="text/css" scoped>
    .update-char {
        float: right;
        cursor: pointer;
    }
    .groups-disabled {
        text-decoration: line-through;
    }
</style>
