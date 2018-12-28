<!--
Content page for group and app administration
-->
<template>
    <div class="card border-secondary mb-3">

        <characters :swagger="swagger" ref="charactersModal"></characters>

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
            <p v-if="type === 'App' && contentType === 'managers'">
                Managers can change the application secret.
            </p>
            <p v-if="contentType === 'alliances' || contentType === 'corporations'">
                Players in these {{contentType}} are automatically added to the group and removed when they leave.
            </p>
            <p v-if="contentType === 'groups'">
                Apps can only see the membership of players to groups that are listed here.
                <button class="btn btn-outline-warning float-right" v-on:click="addAllGroupsToApp()">
                    Add all groups to app
                </button>
            </p>
            <p v-if="contentType === 'roles'">
                See
                <a href="https://github.com/bravecollective/brvneucore/blob/master/doc/API.md" target="_blank">
                    doc/API.md</a> for permissions for each role.
            </p>

            <div class="input-group mb-1">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <span v-if="contentType === 'managers'">Add manager</span>
                        <span v-if="contentType === 'alliances'">Add alliance</span>
                        <span v-if="contentType === 'corporations'">Add corporation</span>
                        <span v-if="contentType === 'groups'">Add group</span>
                        <span v-if="contentType === 'roles'">Add role</span>
                    </span>
                </div>
                <select class="custom-select" v-model="newObject" title="">
                    <option v-if="contentType === 'managers'" value="">Select player ...</option>
                    <option v-if="contentType === 'alliances'" value="">Select alliance ...</option>
                    <option v-if="contentType === 'corporations'" value="">Select corporation ...</option>
                    <option v-if="contentType === 'groups'" value="">Select group ...</option>
                    <option v-if="contentType === 'roles'" value="">Select role ...</option>
                    <option v-for="option in selectContent" v-bind:value="option"
                            v-if="! tableHas(option)">
                        <template v-if="contentType === 'corporations' || contentType === 'alliances'">
                            [{{ option.ticker }}]
                        </template>
                        {{ option.name }}
                        <template v-if="contentType === 'corporations' && option.alliance">
                            ({{ option.alliance.name }})
                        </template>
                    </option>
                </select>
            </div>
        </div>

        <table v-cloak v-if="typeId" class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th v-if="contentType === 'managers'">Player ID</th>
                    <th v-if="contentType === 'corporations' || contentType === 'alliances'">EVE ID</th>
                    <th v-if="contentType === 'corporations' || contentType === 'alliances'">Ticker</th>
                    <th>Name</th>
                    <th v-if="contentType === 'managers'">Characters</th>
                    <th v-if="contentType === 'corporations'">Alliance</th>
                    <th v-if="contentType === 'corporations' || contentType === 'alliances'">Groups</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in tableContent">
                    <td v-if="contentType === 'managers'">{{ row.id }}</td>
                    <td v-if="contentType === 'corporations' || contentType === 'alliances'">{{ row.id }}</td>
                    <td v-if="contentType === 'corporations' || contentType === 'alliances'">{{ row.ticker }}</td>
                    <td>{{ row.name }}</td>
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
                    <td v-if="contentType === 'corporations' || contentType === 'alliances'">
                        <button class="btn btn-info btn-sm" v-on:click="showGroups(row.id)">
                            Show groups
                        </button>
                    </td>
                    <td>
                        <button v-if="contentType === 'managers'"
                                class="btn btn-danger btn-sm"
                                v-on:click="addOrRemoveManagerToGroupOrApp(row.id, 'remove')">
                            Remove manager
                        </button>
                        <button v-if="contentType === 'corporations' || contentType === 'alliances'"
                                class="btn btn-danger btn-sm"
                                v-on:click="addOrRemoveCorporationOrAllianceToGroup(row.id, 'remove')">
                            <span v-if="contentType === 'corporations'">Remove corporation</span>
                            <span v-if="contentType === 'alliances'">Remove alliance</span>
                        </button>
                        <button v-if="contentType === 'groups' || contentType === 'roles'"
                                class="btn btn-danger btn-sm"
                                v-on:click="addOrRemoveTypeToApp(
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
import Characters from '../components/Characters.vue';

module.exports = {
    components: {
        Characters,
    },

    props: {
        swagger: Object,
        contentType: '',
        type: '',
        typeId: null,
        player: [null, Object],
    },

    data: function() {
        return {
            newObject: "", // empty string to select the first entry in the drop-down
            selectContent: [],
            tableContent: [],
            showGroupsEntity: null, // one alliance or corporation object with groups
            withGroups: [], // all alliances or corporations with groups
        }
    },

    mounted: function() {
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
                this.addOrRemoveCorporationOrAllianceToGroup(this.newObject.id, 'add');
            } else if (this.contentType === 'groups' || this.contentType === 'roles') {
                this.addOrRemoveTypeToApp(
                    this.newObject.id,
                    this.contentType === 'groups' ? 'Group' : 'Role',
                    'add'
                );
            }
        },
    },

    methods: {

        getSelectContent: function() {
            const vm = this;
            vm.selectContent = [];

            let api;
            let method;
            if (this.contentType === 'managers') {
                api = new this.swagger.PlayerApi();
                if (this.type === 'Group') {
                    method = 'groupManagers';
                } else if (this.type === 'App') {
                    method = 'appManagers';
                }
            } else if (this.contentType === 'corporations') {
                api = new this.swagger.CorporationApi();
                method = 'all';
            } else if (this.contentType === 'alliances') {
                api = new this.swagger.AllianceApi();
                method = 'all';
            } else if (this.contentType === 'groups') {
                api = new this.swagger.GroupApi();
                method = 'all';
            } else if (this.contentType === 'roles') {
                vm.selectContent = [
                    { id: 'app-tracking', name: 'app-tracking' }
                ];
                return;
            }
            if (! api || ! method) {
                return;
            }

            vm.loading(true);
            api[method].apply(api, [function(error, data) {
                vm.loading(false);
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
                api = new this.swagger.GroupApi();
            } else if (this.type === 'App') {
                api = new this.swagger.AppApi();
            }
            if (this.contentType === 'managers') {
                method = 'managers';
            } else if (this.contentType === 'corporations') {
                method = 'corporations';
            } else if (this.contentType === 'alliances') {
                method = 'alliances';
            } else if (this.type === 'App' && (this.contentType === 'groups' || this.contentType === 'roles')) {
                method = 'show';
            } else if (this.contentType === 'groups') {
                method = 'groups';
            }
            if (! api || ! method) {
                return;
            }

            vm.loading(true);
            api[method].apply(api, [this.typeId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                if (vm.type === 'App' && vm.contentType === 'groups') {
                    vm.tableContent = data.groups;
                } else if (vm.type === 'App' && vm.contentType === 'roles') {
                    // transform string to object and remove "app" role
                    const roles = [];
                    for (let role of vm.fixRoles(data.roles)) {
                        if (role !== 'app') {
                            roles.push({ id: role, name: role });
                        }
                    }
                    vm.tableContent = roles;
                }  else {
                    vm.tableContent = data;
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
                api = new this.swagger.CorporationApi();
            } else if (this.contentType === 'alliances') {
                api = new this.swagger.AllianceApi();
            } else {
                return;
            }

            vm.loading(true);
            api['withGroups'].apply(api, [function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.withGroups = data;
            }]);
        },

        tableHas: function(option) {
            for (let row of this.tableContent) {
                if (row.id === option.id) {
                    return true;
                }
            }
            return false;
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
                window.jQuery('#showGroupsModal').modal('show');
            }, 10);
        },

        addOrRemoveManagerToGroupOrApp: function(playerId, action) {
            const vm = this;

            let api;
            let method;
            if (this.type === 'Group') {
                api = new this.swagger.GroupApi();
            } else if (this.type === 'App') {
                api = new this.swagger.AppApi();
            }
            if (action === 'add') {
                method = 'addManager';
            } else if (action === 'remove') {
                method = 'removeManager';
            }
            if (! api || ! method) {
                return;
            }

            vm.loading(true);
            api[method].apply(api, [this.typeId, playerId, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
                vm.getTableContent();
            }]);
        },

        addOrRemoveCorporationOrAllianceToGroup: function(id, action) {
            const vm = this;

            let api;
            let method;
            if (action === 'add') {
                method = 'addGroup';
            } else if (action === 'remove') {
                method = 'removeGroup';
            }
            if (this.contentType === 'corporations') {
                api = new this.swagger.CorporationApi();
            } else if (this.contentType === 'alliances') {
                api = new this.swagger.AllianceApi();
            }
            if (! api || ! method) {
                return;
            }

            vm.loading(true);
            api[method].apply(api, [id, this.typeId, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.getTableContent();
                vm.getWithGroups();
            }]);
        },

        addOrRemoveTypeToApp: function(id, type, action) {
            const vm = this;
            const api = new this.swagger.AppApi();
            const method = action + type; // add/remove + Group/Role

            vm.loading(true);
            api[method].apply(api, [this.typeId, id, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.getTableContent();
            }]);
        },

        addAllGroupsToApp: function() {
            const vm = this;

            if (! vm.typeId || vm.type !== 'App') {
                return;
            }

            const numGroups = vm.selectContent.length;
            let numAdded = 0;

            for (let group of vm.selectContent) {
                add(group);
            }

            function add(group) {
                vm.loading(true);
                new vm.swagger.AppApi().addGroup(vm.typeId, group.id, function(error) {
                    vm.loading(false);
                    if (error) { // 403 usually
                        return;
                    }
                    done();
                });
            }

            function done() {
                numAdded ++;
                if (numGroups === numAdded) {
                    vm.getTableContent();
                }
            }
        },
    },
}
</script>

<style scoped>

</style>
