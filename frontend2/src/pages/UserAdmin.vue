<template>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <h1>User Administration</h1>
        </div>
    </div>

    <div v-cloak v-if="player" class="row">
        <div class="col-lg-4">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Characters
                </h3>
                <div class="card-body">
                    <character-search :swagger="swagger" v-on:result="searchResult = $event"></character-search>
                </div>
                <div class="list-group">
                    <span v-for="char in searchResult"
                        class="list-group-item list-group-item-action btn"
                        v-on:click="findPlayer(char.id)">
                        {{ char.name }}
                    </span>
                </div>
            </div>

            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Players by role
                </h3>
                <div class="card-body">
                    <span v-for="role in availableRoles"
                        class="badge btn mr-1"
                        :class="{ 'badge-secondary': activeRole !== role, 'badge-primary': activeRole === role }"
                        v-on:click="getPlayerByRole(role)">
                        {{ role }}
                    </span>
                </div>
                <div class="list-group">
                     <a v-for="pr in playersRole" class="list-group-item list-group-item-action"
                        :class="{ active: playerEdit && playerEdit.id === pr.id }"
                        :href="'#UserAdmin/' + pr.id">
                        {{ pr.name }}
                    </a>
                </div>
            </div>
        </div>

        <div v-cloak v-if="playerEdit" class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header" :title="'ID: ' + playerEdit.id">
                    Player: {{ playerEdit.name }}
                </h3>

                <div class="card-body">
                    <h4>Roles</h4>

                    <div class="input-group input-group-sm mb-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Add tole</span>
                        </div>
                        <select class="custom-select" v-model="newRole">
                            <option value="">Select role ...</option>
                            <option v-for="role in availableRoles"
                                v-if="! hasRole(role, playerEdit)" v-bind:value="role">
                                {{ role }}
                            </option>
                        </select>
                    </div>

                    <div v-for="role in playerEdit.roles"
                        v-if="role !== 'user'"
                        class="list-group-item">
                        <button type="button" class="btn btn-danger mr-5"
                                :disabled="role === 'user-admin' && playerEdit.id === player.id"
                                v-on:click="removeRole(role)">
                            <i class="fas fa-minus-circle"></i>
                            remove
                        </button>
                        {{ role }}
                    </div>

                    <hr>

                    <h4>Characters</h4>
                    <table class="table table-striped">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Corporation</th>
                            <th>Alliance</th>
                        </tr>
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
                        </tr>
                    </table>

                    <h4>Group Manager</h4>
                    <table class="table table-striped">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>visibility</th>
                        </tr>
                        <tr v-for="managerGroup in playerEdit.managerGroups">
                            <td>{{ managerGroup.id }}</td>
                            <td>{{ managerGroup.name }}</td>
                            <td>{{ managerGroup.visibility }}</td>
                        </tr>
                    </table>

                    <h4>App Manager</h4>
                    <table class="table table-striped">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                        </tr>
                        <tr v-for="managerApp in playerEdit.managerApps">
                            <td>{{ managerApp.id }}</td>
                            <td>{{ managerApp.name }}</td>
                        </tr>
                    </table>

                    <h4>Group Applications</h4>
                    <table class="table table-striped">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>visibility</th>
                        </tr>
                        <tr v-for="application in playerEdit.applications">
                            <td>{{ application.id }}</td>
                            <td>{{ application.name }}</td>
                            <td>{{ application.visibility }}</td>
                        </tr>
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
            activeRole: '',
            playerId: null, // player ID from route
            playerEdit: null,// player being edited
            availableRoles: ['app-admin', 'app-manager', 'group-admin', 'group-manager', 'user-admin'],
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

        findPlayer: function(characterId) {
            var vm = this;
            vm.loading(true);
            new this.swagger.CharacterApi().findPlayerOf(characterId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                window.location.hash = '#UserAdmin/' + data.id;
            });
        },

        getPlayerByRole: function(roleName) {
            this.activeRole = roleName;
            var vm = this;
            var api = new this.swagger.PlayerApi();

            var method;
            if (roleName === 'group-manager') {
                method = 'groupManagers';
            } else if (roleName === 'app-manager') {
                method = 'appManagers';
            } else {
                vm.playersRole = [];
                return;
            }

            vm.loading(true);
            api[method].apply(api, [function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.playersRole = data;
            }]);
        },

        getPlayer: function() {
            var vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().showById(this.playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                data.roles = vm.fixRoles(data.roles);
                vm.playerEdit = data;
            });
        },

        addRole: function(playerId, roleName) {
            var vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().addRole(playerId, roleName, function(error, data) {
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
            var playerId = this.playerEdit.id;
            var vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().removeRole(playerId, roleName, function(error, data) {
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
    },
}
</script>

<style scoped>
    .remove-role {
        float: right;
    }
</style>
