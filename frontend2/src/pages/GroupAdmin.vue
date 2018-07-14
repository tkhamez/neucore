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
                <h3 class="card-header bg-warning">Groups</h3>
                <div v-cloak v-if="player" class="list-group">
                    <a
                        v-for="group in groups"
                        class="list-group-item list-group-item-action"
                        :class="{ active: groupId === group.id }"
                        :href="'#GroupAdmin/' + group.id">
                        {{ group.name }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-secondary mb-3">
                <h3 class="card-header bg-warning">
                    Manager
                    <span class="text-muted small">{{ groupName }}</span>
                </h3>

                <div v-cloak v-if="groupId" class="card-body add-member">
                    <div class="input-group mb-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="inputGroup-sizing-sm">Add manager</span>
                        </div>
                        <select class="custom-select" v-model="newManager">
                            <option value="">Select player ...</option>
                            <option v-for="manager in allManagers"
                                v-bind:value="{ id: manager.id, name: manager.name }">
                                {{ manager.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="newManager">
                        <span class="text-muted">Player account:</span>
                        [{{ newManager.id }}] {{ newManager.name }}
                        <button class="btn btn-info btn-sm"
                                v-on:click="showCharacters(newManager.id)">
                            Show characters
                        </button>
                        <button class="btn btn-success btn-sm"
                                v-on:click="addManager(newManager.id)">
                            Add manager
                        </button>
                    </div>
                </div>

                <table v-cloak v-if="groupId" class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="manager in groupManagers">
                            <td>{{ manager.id }}</td>
                            <td>{{ manager.name }}</td>
                            <td>
                                <button class="btn btn-info btn-sm"
                                    v-on:click="showCharacters(manager.id)">
                                    Show characters
                                </button>
                            </td>
                            <td>
                                <button class="btn btn-danger btn-sm" v-on:click="removeManager(manager.id)">
                                    Remove manager
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
            groupName: null,
            groupManagers: [],
            newManager: "",
            allManagers: [],
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.getGroups();
            this.getAllManager();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.getGroups();
            this.getGroupManager();
            this.getAllManager();
        },

        route: function() {
            this.getGroupManager();
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

        getGroupManager: function() {
            this.groupName = null;

            // group id
            this.groupId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.groupId === null) {
                return;
            }

            // get managers
            var vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().managers(this.groupId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.groupManagers = data;

                // set group name variable
                vm.groupName = null;
                for (var group of vm.groups) {
                    if (group.id === vm.groupId) {
                        vm.groupName = group.name;
                    }
                }
            });
        },

        getAllManager: function() {
            var vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().groupManagers(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.allManagers = data;
            });
        },

        showCharacters: function(managerId) {
            this.$refs.charactersModal.showCharacters(managerId);
        },

        addManager: function(playerId) {
           var vm = this;
           vm.loading(true);
           new this.swagger.GroupApi().addManager(this.groupId, playerId, function(error, data) {
               vm.loading(false);
               if (error) { // 403 usually
                   return;
               }
               if (playerId === vm.player.id) {
                   vm.$root.$emit('playerChange');
               }
               vm.getGroupManager();
           });
        },

        removeManager: function(playerId) {
            var vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().removeManager(this.groupId, playerId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
                vm.getGroupManager();
            });
        },
    },
}
</script>

<style scoped>

</style>
