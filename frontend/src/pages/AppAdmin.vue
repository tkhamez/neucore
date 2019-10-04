<template>
<div class="container-fluid">

    <edit :swagger="swagger" :type="'App'" ref="editModal"
        v-on:created="appCreated($event)"
        v-on:deleted="appDeleted()"
        v-on:itemChange="appChanged()"></edit>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>App Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Apps
                    <span class="far fa-plus-square add-app"
                       @mouseover="mouseover"
                       @mouseleave="mouseleave"
                       v-on:click="showCreateAppModal()"></span>
                </h3>
                <div class="list-group">
                    <span v-for="app in apps">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: appId === app.id }"
                           :href="'#AppAdmin/' + app.id + '/' + contentType">
                            {{ app.name }}
                            <i v-cloak v-if="appId === app.id"
                               class="far fa-trash-alt mr-1 delete-app"
                               @mouseover="mouseover"
                               @mouseleave="mouseleave"
                               v-on:click="showDeleteAppModal(app)" title="delete"></i>
                            <i v-cloak v-if="appId === app.id"
                               class="fas fa-pencil-alt mr-1 rename-app"
                               @mouseover="mouseover"
                               @mouseleave="mouseleave"
                               v-on:click="showRenameAppModal(app)" title="rename"></i>
                        </a>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <ul class="nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'managers' }"
                       :href="'#AppAdmin/' + appId + '/managers'">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'groups' }"
                       :href="'#AppAdmin/' + appId + '/groups'">Groups</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'roles' }"
                       :href="'#AppAdmin/' + appId + '/roles'">Roles</a>
                </li>
            </ul>

            <admin v-cloak v-if="appId" ref="admin"
                 :player="player" :contentType="contentType" :typeId="appId" :settings="settings"
                :swagger="swagger" :type="'App'"></admin>

        </div>
    </div>
</div>
</template>

<script>
import Edit  from '../components/GroupAppEdit.vue';
import Admin from '../components/EntityRelationEdit.vue';

module.exports = {
    components: {
        Edit,
        Admin,
    },

    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object],
        settings: Object,
    },

    data: function() {
        return {
            apps: [],
            appId: null, // current app
            contentType: "",
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change + refresh
            this.getApps();
            this.setAppIdAndContentType();
        }
    },

    watch: {
        initialized: function() { // currently not triggered
            this.getApps();
            this.setAppIdAndContentType();
        },

        route: function() {
            this.setAppIdAndContentType();
        },
    },

    methods: {
        mouseover (ele) {
            window.$(ele.target).addClass('text-warning');
        },

        mouseleave (ele) {
            window.$(ele.target).removeClass('text-warning');
        },

        showCreateAppModal: function() {
            this.$refs.editModal.showCreateModal();
        },

        appCreated: function(newAppId) {
            window.location.hash = '#AppAdmin/' + newAppId;
            this.getApps();
        },

        showDeleteAppModal: function(app) {
            this.$refs.editModal.showDeleteModal(app);
        },

        appDeleted: function() {
            window.location.hash = '#AppAdmin';
            this.appId = null;
            this.contentType = '';
            this.getApps();
            this.$root.$emit('playerChange'); // current player could have been a manager
        },

        showRenameAppModal: function(app) {
            this.$refs.editModal.showEditModal(app);
        },

        appChanged: function() {
            this.$refs.editModal.hideEditModal();
            this.getApps();
        },

        getApps: function() {
            const vm = this;
            new this.swagger.AppApi().all(function(error, data) {
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

    .delete-app,
    .rename-app {
        float: right;
        padding: 4px 4px 5px 4px;
        border: 1px solid white;
    }
</style>
