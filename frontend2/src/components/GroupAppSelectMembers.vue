<!--
Content page for groups and apps
-->
<template>
    <div class="card border-secondary mb-3">

        <characters :swagger="swagger" ref="charactersModal"></characters>

        <div class="card-body">
            <div class="input-group mb-1">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <span v-if="contentType === 'managers'">Add manager</span>
                        <span v-if="contentType === 'corporations'">Add corporation</span>
                        <span v-if="contentType === 'alliances'">Add alliance</span>
                        <span v-if="contentType === 'groups'">Add group</span>
                    </span>
                </div>
                <select class="custom-select" v-model="newObject" title="">
                    <option v-if="contentType === 'managers'" value="">Select player ...</option>
                    <option v-if="contentType === 'corporations'" value="">Select corporation ...</option>
                    <option v-if="contentType === 'alliances'" value="">Select alliance ...</option>
                    <option v-if="contentType === 'groups'" value="">Select group ...</option>
                    <option v-for="option in selectContent" v-bind:value="option"
                            v-if="! tableHas(option)">
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
                <th v-if="contentType === 'managers'">ID</th>
                <th v-if="contentType === 'corporations' || contentType === 'alliances'">Ticker</th>
                <th>Name</th>
                <th v-if="contentType === 'managers'">Characters</th>
                <th v-if="contentType === 'corporations'">Alliance</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="row in tableContent">
                <td v-if="contentType === 'managers'">{{ row.id }}</td>
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
                    <button v-if="contentType === 'groups'"
                            class="btn btn-danger btn-sm"
                            v-on:click="addOrRemoveGroupToApp(row.id, 'remove')">
                        Remove group
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
        }
    },

    mounted: function() {
        this.getSelectContent();
        if (this.typeId) {
            this.getTableContent();
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
            } else if (this.contentType === 'groups') {
                this.addOrRemoveGroupToApp(this.newObject.id, 'add');
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
                vm.tableContent = data;
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
            }]);
        },

        addOrRemoveGroupToApp: function(id, action) {
            const vm = this;
            const api = new this.swagger.AppApi();

            let method;
            if (action === 'add') {
                method = 'addGroup';
            } else if (action === 'remove') {
                method = 'removeGroup';
            } else {
                return;
            }

            vm.loading(true);
            api[method].apply(api, [this.typeId, id, function(error) {
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
