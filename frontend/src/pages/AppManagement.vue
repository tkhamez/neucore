<template>
    <div class="container-fluid">
        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>App Management</h1>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-4">
                <div class="card border-secondary mb-3" >
                    <h3 class="card-header">Apps</h3>
                    <div v-cloak v-if="player" class="list-group">
                        <a
                            v-for="app in player.managerApps"
                            class="list-group-item list-group-item-action"
                            :class="{ active: appId === app.id }"
                            :href="'#AppManagement/' + app.id">
                            {{ app.name }}
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-secondary mb-3">
                    <h3 class="card-header">
                        Details
                    </h3>
                    <div v-cloak v-if="appId" class="card-body">
                        <p>ID: {{ appId }}</p>
                        <p>Name: {{ appName }}</p>

                        <hr>

                        <h5>Application Secret</h5>
                        <p class="card-text">
                            Here you can generate a new application secret.
                            This will <em>invalidate</em> the existing secret.<br>
                            See also
                            <a href="https://github.com/bravecollective/brvneucore/tree/master/backend#app-auth"
                                target="_blank">Backend - App Auth</a>.
                        </p>
                        <p>
                            <button type="button" class="btn btn-warning" v-on:click="generateSecret()">
                                Generate new secret
                            </button>
                        </p>
                        <div v-cloak v-if="secret" class="alert alert-secondary mt-4">
                            <code>{{ secret }}</code>
                        </div>
                        <p v-cloak v-if="secret" class="card-text">
                            Please make a note of the new secret, it is not retrievable again!
                        </p>

                        <hr>

                        <h5>Groups</h5>
                        <table class="table table-hover table-sm">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>visibility</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="group in appGroups">
                                    <td>{{ group.id }}</td>
                                    <td>{{ group.name }}</td>
                                    <td>{{ group.visibility }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div> <!-- card -->
            </div> <!-- col -->
        </div> <!-- row  -->
    </div>
</template>

<script>
module.exports = {
    props: {
        route: Array,
        swagger: Object,
        player: [null, Object],
    },

    data: function() {
        return {
            appId: null,
            appName: null,
            appGroups: [],
            secret: null,
        }
    },

    watch: {
        player: function() {
            this.setRoute();
        },

        route: function() {
            this.setRoute();
        }
    },

    methods: {
        setRoute: function() {
            this.secret = null;
            this.appName = null;

            this.appId = this.route[1] ? parseInt(this.route[1], 10) : null;

            // set group name variable
            for (let app of this.player.managerApps) {
                if (app.id === this.appId) {
                    this.appName = app.name;
                    this.getGroups();
                    break;
                }
            }
        },

        getGroups: function() {
            const vm = this;
            vm.appGroups = [];

            vm.loading(true);
            new this.swagger.AppApi().groups(this.appId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.appGroups = data;
            });
        },

        generateSecret: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.AppApi().changeSecret(this.appId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.secret = data;
            });
        },
    },
}
</script>

<style scoped>
    table {
        font-size: 90%;
    }
</style>
