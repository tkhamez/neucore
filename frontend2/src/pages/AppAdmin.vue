<template>
<div class="container-fluid">

    <create-delete :swagger="swagger" :type="'App'" ref="createDeleteModals"
           v-on:created="appCreated($event)" v-on:deleted="appDeleted()"></create-delete>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>App Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Apps
                    <i class="far fa-plus-square add-app" v-on:click="showCreateAppModal()"></i>
                </h3>
                <div class="list-group">
                    <span v-for="app in apps">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: appId === app.id }"
                           :href="'#AppAdmin/' + app.id + '/' + contentType">
                            {{ app.name }}
                            <i v-cloak v-if="appId === app.id"
                               class="far fa-trash-alt delete-app bg-danger"
                               v-on:click="showDeleteAppModal(app)" title="delete"></i>
                        </a>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active"
                       :class="{ 'bg-primary': contentType === 'managers' }"
                       :href="'#AppAdmin/' + appId + '/managers'">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active"
                       :class="{ 'bg-primary': contentType === 'groups' }"
                       :href="'#AppAdmin/' + appId + '/groups'">Groups</a>
                </li>
            </ul>

            <select-members v-cloak v-if="appId"
                 :player="player" :contentType="contentType" :typeId="appId"
                :swagger="swagger" :type="'App'"></select-members>

        </div>
    </div>
</div>
</template>

<script>
import CreateDelete  from '../components/GroupAppCreateDelete.vue';
import SelectMembers from '../components/GroupAppSelectMembers.vue';

module.exports = {
    components: {
        CreateDelete,
        SelectMembers,
    },

    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object],
    },

    data: function() {
        return {
            apps: [],
            appId: null, // current app
            contentType: "",
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.getApps();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.getApps();
            this.setAppIdAndContentType();
        },

        route: function() {
            this.setAppIdAndContentType();
        },
    },

    methods: {

        showCreateAppModal: function() {
            this.$refs.createDeleteModals.showCreateModal();
        },

        appCreated: function(newAppId) {
            window.location.hash = '#AppAdmin/' + newAppId;
            this.getApps();
        },

        showDeleteAppModal: function(app) {
            this.$refs.createDeleteModals.showDeleteModal(app);
        },

        appDeleted: function() {
            window.location.hash = '#AppAdmin';
            this.getApps();
        },

        getApps: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.AppApi().all(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.apps = data;
            });
        },

        setAppIdAndContentType: function() {
            this.appId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.appId) {
                this.contentType = this.route[2] ? this.route[2] : 'managers';
            }
        },
    },
}
</script>

<style scoped>
    .add-app {
        float: right;
        cursor: pointer;
    }
    .delete-app {
        float: right;
        padding: 4px 4px 5px 4px;
        border: 1px solid white;
    }
</style>
