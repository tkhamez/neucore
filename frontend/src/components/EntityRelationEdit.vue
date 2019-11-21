<!--
Select and table to add and remove objects from other objects.
-->

<template>
    <div class="card border-secondary mb-3">

        <!--suppress HtmlUnknownTag -->
        <characters ref="charactersModal"></characters>

        <div v-cloak v-if="showGroupsEntity" class="modal fade" id="showGroupsModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            [{{ showGroupsEntity.ticker }}]
                            {{ showGroupsEntity.name }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <ul class="list-group">
                        <li v-for="group in showGroupsEntity.groups" class="list-group-item">
                            {{ group.name }}
                        </li>
                    </ul>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-cloak class="card-body">
            <p v-if="type === 'Group' && contentType === 'managers'">
                Managers can add and remove players to a group.
            </p>
            <p v-if="type === 'Group' && contentType === 'groups'">
                Add groups that are a prerequisite (all of them) for being a member of this group.
            </p>
            <p v-if="type === 'App' && contentType === 'managers'">
                Managers can change the application secret.
            </p>
            <p v-if="
                (type === 'Group' || type === 'App') &&
                (contentType === 'alliances' || contentType === 'corporations')
            ">
                Players in these {{contentType}} are automatically added to the group and removed when they leave.
            </p>
            <div class="row mb-1" v-if="type === 'App' && contentType === 'groups'">
                <p class="col-9">
                    The application can only see the membership of players to groups that are listed here.
                </p>
                <div class="col-3 text-right">
                    <button class="btn btn-sm btn-outline-warning" v-on:click="addAllGroupsToApp()">
                        Add all groups to app
                    </button>
                </div>
            </div>
            <p v-cloak v-if="contentType === 'roles'">
                See
                <a :href="settings.customization_github + '/blob/master/doc/API.md'"
                   target="_blank" rel="noopener noreferrer">doc/API.md</a>
                for permissions for each role.
            </p>
            <p v-if="type === 'Corporation' && contentType === 'groups'">
                Members of these groups can view the tracking data of the selected corporation.
            </p>
            <multiselect v-model="newObject" :options="currentSelectContent"
                         v-bind:placeholder="placeholder"
                         label="name" track-by="id"
                         :loading="false"
                         :custom-label="customLabel">
            </multiselect>
        </div>

        <table v-cloak v-if="typeId" class="table table-hover mb-0">
            <thead>
                <tr>
                    <th v-if="contentType === 'managers' || contentType === 'groups'">ID</th>
                    <th v-if="contentType === 'corporations' || contentType === 'alliances'">EVE ID</th>
                    <th v-if="contentType === 'corporations' || contentType === 'alliances'">Ticker</th>
                    <th>Name</th>
                    <th v-if="contentType === 'managers'">has {{ type.toLowerCase() }}-manager role</th>
                    <th v-if="contentType === 'managers'">Characters</th>
                    <th v-if="contentType === 'corporations'">Alliance</th>
                    <th v-if="
                        (type === 'Group' || type === 'App') &&
                        (contentType === 'corporations' || contentType === 'alliances')">Groups</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in tableContent">
                    <td v-if="contentType === 'managers' || contentType === 'groups'">{{ row.id }}</td>
                    <td v-if="contentType === 'corporations' || contentType === 'alliances'">{{ row.id }}</td>
                    <td v-if="contentType === 'corporations' || contentType === 'alliances'">{{ row.ticker }}</td>
                    <td>{{ row.name }}</td>
                    <td v-if="contentType === 'managers'">
                        <span :class="{ 'text-danger': hasRequiredRole(row) === 'no' }">
                            {{ hasRequiredRole(row) }}
                        </span>
                    </td>
                    <td v-if="contentType === 'managers'">
                        <button class="btn btn-info btn-sm" v-on:click="showCharacters(row.id)">
                            Show characters
                        </button>
                    </td>
                    <td v-if="contentType === 'corporations'">
                        <span v-if="row.alliance">
                            [{{ row.alliance.ticker }}]
                            {{ row.alliance.name }}
                        </span>
                    </td>
                    <td v-if="
                            (type === 'Group' || type === 'App') &&
                            (contentType === 'corporations' || contentType === 'alliances')">
                        <button class="btn btn-info btn-sm" v-on:click="showGroups(row.id)">Show groups</button>
                    </td>
                    <td>
                        <button v-if="contentType === 'managers'"
                                class="btn btn-danger btn-sm"
                                v-on:click="addOrRemoveManagerToGroupOrApp(row.id, 'remove')">
                            Remove manager
                        </button>
                        <button v-if="contentType === 'corporations' || contentType === 'alliances'"
                                class="btn btn-danger btn-sm"
                                v-on:click="addOrRemoveCorporationOrAllianceToEntity(row.id, 'remove')">
                            <span v-if="contentType === 'corporations'">Remove corporation</span>
                            <span v-if="contentType === 'alliances'">Remove alliance</span>
                        </button>
                        <button v-if="contentType === 'groups' || contentType === 'roles'"
                                class="btn btn-danger btn-sm"
                                v-on:click="addOrRemoveEntityToEntity(
                                    row.id,
                                    contentType === 'groups' ? 'Group' : 'Role',
                                    'remove'
                                )">
                            Remove {{ contentType === 'groups' ? 'group' : 'role '}}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div> <!-- card -->
</template>

<script>
import $ from 'jquery';
import { AllianceApi } from 'neucore-js-client';
import { AppApi } from 'neucore-js-client';
import { CorporationApi } from 'neucore-js-client';
import { GroupApi } from 'neucore-js-client';
import { PlayerApi } from 'neucore-js-client';
import { WatchlistApi } from 'neucore-js-client';

import Characters from '../components/Characters.vue';

export default {
    components: {
        Characters,
    },

    props: {
        /**
         * Content of the select box, e. g. "managers"
         */
        contentType: '',

        /**
         * Type of entity to add/remove items from, e. g. "Group"
         */
        type: '',

        /**
         * ID of entity to add/remove items from, e. g. 12
         */
        typeId: Number,

        player: Object,

        settings: Object,
    },

    data: function() {
        return {
            newObject: "", // empty string to select the first entry in the drop-down
            placeholder: "", // placeholder for the multi-select
            selectContent: [], // all options from backend
            currentSelectContent: [], // copy of selectContent without items from the table
            tableContent: [],
            showGroupsEntity: null, // one alliance or corporation object with groups
            withGroups: [], // all alliances or corporations with groups
        }
    },

    mounted: function() {
        this.customPlaceholder();
        this.getSelectContent();
        if (this.typeId) {
            this.getTableContent();
            this.getWithGroups();
        }
    },

    watch: {
        typeId: function() {
            this.newObject = "";
            if (this.typeId) {
                this.getTableContent();
            }
        },

        contentType: function() {
            this.newObject = "";
            this.customPlaceholder();
            this.getSelectContent();
            if (this.typeId) {
                this.getTableContent();
                this.getWithGroups();
            }
        },

        newObject: function() {
            if (this.newObject === "") {
                return;
            }
            if (this.contentType === 'managers') {
                this.addOrRemoveManagerToGroupOrApp(this.newObject.id, 'add');
            } else if (this.contentType === 'corporations' || this.contentType === 'alliances') {
                this.addOrRemoveCorporationOrAllianceToEntity(this.newObject.id, 'add');
            } else if (this.contentType === 'groups' || this.contentType === 'roles') {
                this.addOrRemoveEntityToEntity(
                    this.newObject.id,
                    this.contentType === 'groups' ? 'Group' : 'Role',
                    'add'
                );
            }
            this.newObject = "";
        },

        selectContent: function() {
            this.removeSelectedOptions();
        },

        tableContent: function() {
            this.removeSelectedOptions();
        },
    },

    methods: {
        customPlaceholder: function() {
            if (this.contentType === 'managers') {
                this.placeholder = 'Add manager';
            } else if (this.contentType === 'alliances') {
                this.placeholder = 'Add alliance';
            } else if (this.contentType === 'corporations') {
                this.placeholder = 'Add corporation';
            } else if (this.contentType === 'groups') {
                this.placeholder = 'Add group';
            } else if (this.contentType === 'roles') {
                this.placeholder = 'Add role';
            }
        },

        customLabel: function(option) {
            let label = option.name;
            if (this.contentType === 'managers') {
                label += ' #' + option.id
            } else if (this.contentType === 'corporations' || this.contentType === 'alliances') {
                label += ' [' + option.ticker + ']';
            }
            if (this.contentType === 'corporations' && option.alliance) {
                label += ' - ' + option.alliance.name +  ' [' + option.alliance.ticker + ']';
            }
            return label
        },

        getSelectContent: function() {
            const vm = this;
            vm.selectContent = [];

            let api;
            let method;
            if (this.contentType === 'managers') {
                api = new PlayerApi();
                if (this.type === 'Group') {
                    method = 'groupManagers';
                } else if (this.type === 'App') {
                    method = 'appManagers';
                }
            } else if (this.contentType === 'corporations') {
                api = new CorporationApi();
                method = 'all';
            } else if (this.contentType === 'alliances') {
                api = new AllianceApi();
                method = 'all';
            } else if (this.contentType === 'groups') {
                api = new GroupApi();
                method = 'all';
            } else if (this.contentType === 'roles') {
                vm.selectContent = [
                    { id: 'app-groups', name: 'app-groups' },
                    { id: 'app-chars', name: 'app-chars' },
                    { id: 'app-tracking', name: 'app-tracking' },
                    { id: 'app-esi', name: 'app-esi' }
                ];
                return;
            }
            if (! api || ! method) {
                return;
            }

            api[method].apply(api, [function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.selectContent = data;
            }]);
        },

        getTableContent: function() {
            const vm = this;
            vm.tableContent = [];

            let api;
            let method;
            if (this.type === 'Group') {
                api = new GroupApi();
            } else if (this.type === 'App') {
                api = new AppApi();
            } else if (this.type === 'Player') {
                api = new PlayerApi();
            } else if (this.type === 'Corporation') {
                api = new CorporationApi();
            } else if (this.type === 'Watchlist') {
                api = new WatchlistApi();
            }
            if ((this.type === 'Group' || this.type === 'App') && this.contentType === 'managers') {
                method = 'managers';
            } else if (this.type === 'Group' && this.contentType === 'corporations') {
                method = 'corporations';
            } else if (this.type === 'Group' && this.contentType === 'alliances') {
                method = 'alliances';
            } else if (this.type === 'App' && (this.contentType === 'groups' || this.contentType === 'roles')) {
                method = 'show';
            } else if ((this.type === 'App' || this.type === 'Player') && this.contentType === 'groups') {
                method = 'showById';
            } else if (this.type === 'Group' && this.contentType === 'groups') {
                method = 'requiredGroups';
            } else if (this.type === 'Corporation' && this.contentType === 'groups') {
                method = 'getGroupsTracking';
            } else if (this.type === 'Watchlist' && this.contentType === 'groups') {
                method = 'watchlistGroupList';
            } else if (this.type === 'Watchlist' && this.contentType === 'alliances') {
                method = 'watchlistAllianceList';
            } else if (this.type === 'Watchlist' && this.contentType === 'corporations') {
                method = 'watchlistCorporationList';
            }
            if (! api || ! method) {
                return;
            }

            api[method].apply(api, [this.typeId, function(error, data) {
                if (error) { // 403 usually
                    if (vm.type === 'Player') {
                        vm.$emit('activePlayer', null); // pass data to parent
                    }
                    return;
                }
                if (vm.type === 'App' && vm.contentType === 'groups') {
                    vm.tableContent = data.groups;
                } else if (vm.type === 'App' && vm.contentType === 'roles') {
                    // transform string to object and remove "app" role
                    const roles = [];
                    for (let role of data.roles) {
                        if (role !== 'app') {
                            roles.push({ id: role, name: role });
                        }
                    }
                    vm.tableContent = roles;
                }  else if (vm.type === 'Player') {
                    vm.tableContent = data.groups;
                    vm.$emit('activePlayer', data); // pass data to parent
                }  else {
                    vm.tableContent = data;
                }
                if ((vm.type === 'Player' && data.id === vm.player.id) || vm.type === 'Watchlist') {
                    vm.$root.$emit('playerChange');
                }
            }]);
        },

        getWithGroups: function() {
            if (this.type !== 'Group') {
                return;
            }

            const vm = this;
            vm.withGroups = [];

            let api;
            if (this.contentType === 'corporations') {
                api = new CorporationApi();
            } else if (this.contentType === 'alliances') {
                api = new AllianceApi();
            } else {
                return;
            }

            api['withGroups'].apply(api, [function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.withGroups = data;
            }]);
        },

        removeSelectedOptions: function() {
            this.currentSelectContent = [...this.selectContent]; // copy by value
            let removed = 0;
            for (const [index, option] of this.selectContent.entries()) {
                for (const row of this.tableContent) {
                    if (row.id === option.id) {
                        this.currentSelectContent.splice(index - removed, 1);
                        removed ++;
                        break;
                    }
                }
            }
        },

        hasRequiredRole: function(row) {
            if ((this.type === 'App' && row.roles.indexOf('app-manager') !== -1) ||
                (this.type === 'Group' && row.roles.indexOf('group-manager') !== -1)
            ) {
                return 'yes';
            } else {
                return 'no';
            }
        },

        showCharacters: function(managerId) {
            this.$refs.charactersModal.showCharacters(managerId);
        },

        showGroups: function(corpOrAllianceId) {
            this.showGroupsEntity = null;
            for (let entity of this.withGroups) {
                if (entity.id === corpOrAllianceId) {
                    this.showGroupsEntity = entity;
                    break;
                }
            }
            window.setTimeout(function() {
                $('#showGroupsModal').modal('show');
            }, 10);
        },

        addOrRemoveManagerToGroupOrApp: function(playerId, action) {
            const vm = this;

            let api;
            let method;
            if (this.type === 'Group') {
                api = new GroupApi();
            } else if (this.type === 'App') {
                api = new AppApi();
            }
            if (action === 'add') {
                method = 'addManager';
            } else if (action === 'remove') {
                method = 'removeManager';
            }
            if (! api || ! method) {
                return;
            }

            this.callApi(api, method, this.typeId, playerId, function() {
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
                vm.getTableContent();
            });
        },

        addOrRemoveCorporationOrAllianceToEntity: function(id, action) {
            const vm = this;

            let api;
            let method;
            let param1;
            let param2;
            if (this.type === 'Watchlist') {
                api = new WatchlistApi();
                if (this.contentType === 'corporations') {
                    method = 'watchlistCorporation' + upperCaseFirst(action);
                } else if (this.contentType === 'alliances') {
                    method = 'watchlistAlliance' + upperCaseFirst(action);
                }

                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Group') {
                if (this.contentType === 'corporations') {
                    api = new CorporationApi();
                } else if (this.contentType === 'alliances') {
                    api = new AllianceApi();
                }
                method = action + this.type; // addGroup, removeGroup
                param1 = id;
                param2 = this.typeId;
            }
            if (! api || ! method) {
                return;
            }

            this.callApi(api, method, param1, param2, function() {
                vm.getTableContent();
                vm.getWithGroups();
            });
        },

        /**
         * @param {number} id ID of the entity to be added or removed
         * @param {string} type of the entity to be added or removed: "Group" or "Role" atm.
         * @param {string} action "add" or "remove"
         */
        addOrRemoveEntityToEntity: function(id, type, action) {
            const vm = this;
            let api;
            let method;
            let param1;
            let param2;
            if (this.type === 'App') {
                api = new AppApi();
                method = action + type; // add/remove + Group/Role
                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Player') {
                api = new GroupApi();
                method = action + 'Member';
                param1 = id;
                param2 = this.typeId;
            } else if (this.type === 'Group') {
                api = new GroupApi();
                method = action + 'RequiredGroup';
                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Corporation') {
                api = new CorporationApi();
                method = action + 'GroupTracking';
                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Watchlist') {
                api = new WatchlistApi();
                method = 'watchlist' + type + upperCaseFirst(action);
                param1 = this.typeId;
                param2 = id;
            }
            if (! api || ! method) {
                return;
            }

            this.callApi(api, method, param1, param2, function() {
                vm.getTableContent();
            });
        },

        addAllGroupsToApp: function() {
            if (! this.typeId || this.type !== 'App') {
                return;
            }
            const vm = this;
            const groups = [...vm.currentSelectContent];
            const api = new AppApi();

            function addGroup() {
                if (groups.length > 0) {
                    const id = groups[0].id;
                    groups.splice(0, 1);
                    vm.callApi(api, 'addGroup', vm.typeId, id, function() {
                        addGroup();
                    });
                } else {
                    vm.getTableContent();
                }
            }
            addGroup();
        },

        callApi: function(api, method, param1, param2, callback) {
            api[method].apply(api, [param1, param2, function(error) {
                if (error) { // 403 usually
                    return;
                }
                callback();
            }]);
        },
    },
}

function upperCaseFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

</script>

<style scoped>

</style>
