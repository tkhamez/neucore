<template>
<div class="container-fluid">

    <characters :swagger="swagger" ref="charactersModal"></characters>

    <div class="row">
        <div class="col-lg-12">
            <h1>Group Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">Groups</h3>
                <div class="list-group">
                    <a
                        v-for="group in groups"
                        class="list-group-item list-group-item-action"
                        :class="{ active: groupId === group.id }"
                        :href="'#GroupAdmin/' + group.id + '/' + contentType">
                        {{ group.name }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active"
                        :class="{ 'bg-primary': contentType == 'managers' }"
                        :href="'#GroupAdmin/' + groupId + '/managers'">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active"
                        :class="{ 'bg-primary': contentType == 'corporations' }"
                        :href="'#GroupAdmin/' + groupId + '/corporations'">Corporations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active"
                        :class="{ 'bg-primary': contentType == 'alliances' }"
                        :href="'#GroupAdmin/' + groupId + '/alliances'">Alliances</a>
                </li>
            </ul>

            <div class="card border-secondary mb-3">
                <div v-cloak v-if="groupId" class="card-body">
                    <div class="input-group mb-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text">
                                <span v-if="contentType == 'managers'">Add manager</span>
                                <span v-if="contentType == 'corporations'">Add corporation</span>
                                <span v-if="contentType == 'alliances'">Add alliance</span>
                            </span>
                        </div>
                        <select class="custom-select" v-model="newObject">
                            <option value="">
                                <span v-if="contentType == 'managers'">Select player ...</span>
                                <span v-if="contentType == 'corporations'">Select corporation ...</span>
                                <span v-if="contentType == 'alliances'">Select alliance ...</span>
                            </option>
                            <option v-for="option in selectContent"
                                v-bind:value="{ id: option.id, name: option.name, ticker: option.ticker }">
                                {{ option.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="newObject">
                        <span v-if="contentType == 'managers'" class="text-muted">Player account:</span>
                        <span v-if="contentType == 'corporations'" class="text-muted">Corporation:</span>
                        <span v-if="contentType == 'alliances'" class="text-muted">Alliance:</span>
                        <span v-if="contentType == 'managers'">[{{ newObject.id }}]</span>
                        <span v-if="contentType != 'managers'">[{{ newObject.ticker }}]</span>
                        {{ newObject.name }}
                        <button v-if="contentType == 'managers'"
                            class="btn btn-info btn-sm" v-on:click="showCharacters(newObject.id)">
                            Show characters
                        </button>
                        <button v-if="contentType == 'managers'"
                            class="btn btn-success btn-sm" v-on:click="addOrRemoveManager(newObject.id, 'add')">
                            Add manager
                        </button>
                        <button v-if="contentType == 'corporations' || contentType == 'alliances'"
                            class="btn btn-success btn-sm" v-on:click="addOrRemoveToGroup(newObject.id, 'add')">
                            <span v-if="contentType == 'corporations'">Add corporation</span>
                            <span v-if="contentType == 'alliances'">Add alliance</span>
                        </button>
                    </div>
                </div>

                <table v-cloak v-if="groupId" class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th v-if="contentType == 'managers'">ID</th>
                            <th v-if="contentType != 'managers'">Ticker</th>
                            <th>Name</th>
                            <th v-if="contentType == 'managers'"></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tableContent">
                            <td v-if="contentType == 'managers'">{{ row.id }}</td>
                            <td v-if="contentType != 'managers'">{{ row.ticker }}</td>
                            <td>{{ row.name }}</td>
                            <td v-if="contentType == 'managers'">
                                <button class="btn btn-info btn-sm" v-on:click="showCharacters(row.id)">
                                    Show characters
                                </button>
                            </td>
                            <td>
                                <button v-if="contentType == 'managers'"
                                    class="btn btn-danger btn-sm" v-on:click="addOrRemoveManager(row.id, 'remove')">
                                    Remove manager
                                </button>
                                <button v-if="contentType == 'corporations' || contentType == 'alliances'"
                                    class="btn btn-danger btn-sm" v-on:click="addOrRemoveToGroup(row.id, 'remove')">
                                    <span v-if="contentType == 'corporations'">Remove corporation</span>
                                    <span v-if="contentType == 'alliances'">Remove alliance</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div> <!-- card -->
        </div> <!-- col  -->
    </div> <!-- row -->
</div>
</template>

<script>
import Characters from '../components/Characters.vue';

module.exports = {
    components: {
        Characters,
    },

    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object],
    },

    data: function() {
        return {
            groups: [],
            groupId: null,
            contentType: "",
            selectContent: [],
            tableContent: [],
            newObject: "", // empty string to select the first entry in the dropdown
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.getGroups();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.getGroups();
            this.setGroupIdAndContentType();
        },

        route: function() {
            this.setGroupIdAndContentType();
        },

        groupId: function() {
            if (this.groupId) {
                this.getTableContent();
            }
        },

        contentType: function() {
            this.newObject = "";
            this.getSelectContent();
            if (this.groupId) {
                this.getTableContent();
            }
        },
    },

    methods: {
        getGroups: function() {
            var vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().all(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.groups = data;
            });
        },

        setGroupIdAndContentType: function() {
            this.groupId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.groupId) {
                this.contentType = this.route[2] ? this.route[2] : 'managers';
            }
        },

        getSelectContent: function() {
            var vm = this;
            vm.selectContent = [];

            var api;
            var method;
            if (this.contentType === 'managers') {
                api = new this.swagger.PlayerApi();
                method = 'groupManagers';
            } else if (this.contentType === 'corporations') {
                api = new this.swagger.CorporationApi();
                method = 'all';
            } else if (this.contentType === 'alliances') {
                api = new this.swagger.AllianceApi();
                method = 'all';
            } else {
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
            var vm = this;
            vm.tableContent = [];

            var method;
            if (this.contentType === 'managers') {
                method = 'managers';
            } else if (this.contentType === 'corporations') {
                method = 'corporations';
            } else if (this.contentType === 'alliances') {
                method = 'alliances';
            } else {
                return;
            }

            var api = new this.swagger.GroupApi();

            vm.loading(true);
            api[method].apply(api, [this.groupId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.tableContent = data;
            }]);
        },

        showCharacters: function(managerId) {
            this.$refs.charactersModal.showCharacters(managerId);
        },

        addOrRemoveManager: function(playerId, action) {
            var api = new this.swagger.GroupApi();
            var method;
            if (action === 'add') {
                method = 'addManager';
            } else if (action === 'remove') {
                method = 'removeManager';
            } else {
                return;
            }

            var vm = this;
            vm.loading(true);
            api[method].apply(api, [this.groupId, playerId, function(error, data) {
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

        addOrRemoveToGroup: function(id, action) {
            var api;
            var method;
            if (action === 'add') {
                method = 'addGroup';
            } else if (action === 'remove') {
                method = 'removeGroup';
            } else {
                return;
            }
            if (this.contentType === 'corporations') {
                api = new this.swagger.CorporationApi();
            } else if (this.contentType === 'alliances') {
                api = new this.swagger.AllianceApi();
            } else {
                return;
            }

            var vm = this;
            vm.loading(true);
            api[method].apply(api, [id, this.groupId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.getTableContent();
            }]);
        },
    },
}
</script>

<style scoped>

</style>
