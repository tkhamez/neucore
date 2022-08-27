<template>
    <div class="container-fluid">
        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>App Management</h1>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-4 sticky-column">
                <div class="card border-secondary mb-3" >
                    <h4 class="card-header">Apps</h4>
                    <div v-cloak v-if="player" class="list-group">
                        <a
                            v-for="playerApp in player.managerApps"
                            class="list-group-item list-group-item-action"
                            :class="{ active: app && app.id === playerApp.id }"
                            :href="`#AppManagement/${playerApp.id}`">
                            {{ playerApp.name }}
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-secondary mb-3">
                    <h3 class="card-header">
                        Details
                    </h3>
                    <div v-cloak v-if="app" class="card-body">
                        <p>ID: {{ app.id }}</p>
                        <p>Name: {{ app.name }}</p>

                        <hr>

                        <h5>Application Secret</h5>
                        <p class="card-text">
                            Here you can generate a new application secret.
                            This will <em>invalidate</em> the existing secret.<br>
                            See also
                            <a v-cloak target="_blank" rel="noopener noreferrer"
                               :href="`${settings.repository}/blob/master/doc/Documentation.md#authentication`">
                                Authentication for applications
                                <span role="img" style="color: grey;"
                                      class="small fa-solid fa-arrow-up-right-from-square"></span></a>.
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
                        <div class="table-responsive">
                            <table class="table table-hover nc-table-sm" aria-describedby="groups">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">visibility</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="group in app.groups">
                                        <td>{{ group.id }}</td>
                                        <td>{{ group.name }}</td>
                                        <td>{{ group.visibility }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h5>Roles</h5>
                        <table class="table table-hover nc-table-sm" aria-describedby="roles">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="role in appRoles">
                                    <td>{{ role }}</td>
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
import {AppApi} from 'neucore-js-client';

export default {
    props: {
        route: Array,
        player: Object,
        settings: Object,
    },

    data: function() {
        return {
            app: null,
            secret: null,
        }
    },

    computed: {
        appRoles() {
            return this.app.roles.filter(role => role !== 'app');
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
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
            this.app = null;

            const appId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (appId && this.isManagerOf(appId)) {
                this.getApp(appId);
            }
        },

        /**
         * Check if player is manager of requested app.
         * (app-admins may see other apps, but cannot change the secret)
         *
         * @param appId
         * @returns {boolean}
         */
        isManagerOf(appId) {
            let isManager = false;
            for (const app of this.player.managerApps) {
                if (app.id === appId) {
                    isManager = true;
                }
            }
            return isManager;
        },

        getApp: function(id) {
            const vm = this;

            new AppApi().show(id, function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.app = data;
            });
        },

        generateSecret: function() {
            const vm = this;
            new AppApi().changeSecret(this.app.id, function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.secret = data;
            });
        },
    },
}
</script>
