<template>
<div class="container-fluid">

    <edit :type="'App'" ref="editModal"
          :functionCreate="create"
          :functionDelete="deleteIt"
          :functionRename="rename"></edit>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>App Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">
                    Apps
                    <span class="far fa-plus-square add-app" title="Add application"
                       @mouseover="mouseover"
                       @mouseleave="mouseleave"
                       v-on:click="showCreateAppModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="app in apps" class="list-item-wrap" :class="{ active: appId === app.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: appId === app.id }"
                           :href="'#AppAdmin/' + app.id + '/' + contentType">
                            {{ app.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="edit" title="edit"
                                  class="fas fa-pencil-alt mr-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showRenameAppModal(app)"></span>
                            <span role="img" aria-label="delete" title="delete"
                                  class="far fa-trash-alt mr-1"
                                  @mouseover="mouseover"  @mouseleave="mouseleave"
                                  v-on:click="showDeleteAppModal(app)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{appName}}</h4>
            </div>
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
                   :type="'App'"></admin>

        </div>
    </div>
</div>
</template>

<script>
import $ from 'jquery';
import {AppApi} from 'neucore-js-client';

import Edit  from '../components/EntityEdit.vue';
import Admin from '../components/EntityRelationEdit.vue';

export default {
    components: {
        Edit,
        Admin,
    },

    props: {
        route: Array,
        player: Object,
        settings: Object,
    },

    data: function() {
        return {
            apps: [],
            appId: null, // current app
            appName: '',
            contentType: '',
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        getApps(this);
        setAppIdAndContentType(this);
    },

    watch: {
        route: function() {
            setAppIdAndContentType(this);
        },
    },

    methods: {
        mouseover (ele) {
            $(ele.target).addClass('text-warning');
        },

        mouseleave (ele) {
            $(ele.target).removeClass('text-warning');
        },

        showCreateAppModal: function() {
            this.$refs.editModal.showCreateModal();
        },

        showDeleteAppModal: function(app) {
            this.$refs.editModal.showDeleteModal(app);
        },

        showRenameAppModal: function(app) {
            this.$refs.editModal.showEditModal(app);
        },

        create (name) {
            const vm = this;
            new AppApi().create(name, (error, data, response) => {
                if (response.status === 409) {
                    vm.message('An app with this name already exists.', 'error');
                } else if (response.status === 400) {
                    vm.message('Invalid name.', 'error');
                } else if (error) {
                    vm.message('Error creating app.', 'error');
                } else {
                    vm.$refs.editModal.hideModal();
                    vm.message('App created.', 'success');
                    window.location.hash = `#AppAdmin/${data.id}`;
                    getApps(vm);
                }
            });
        },

        deleteIt (id) {
            const vm = this;
            new AppApi().callDelete(id, (error) => {
                if (error) {
                    vm.message('Error deleting app', 'error');
                } else {
                    vm.$refs.editModal.hideModal();
                    vm.message('App deleted.', 'success');
                    window.location.hash = '#AppAdmin';
                    vm.$root.$emit('playerChange'); // current player could have been a manager
                    vm.appId = null;
                    vm.contentType = '';
                    getApps(vm);
                }
            });
        },

        rename (id, name) {
            const vm = this;
            new AppApi().rename(id, name, (error, data, response) => {
                if (response.status === 409) {
                    vm.message('An app with this name already exists.', 'error');
                } else if (response.status === 400) {
                    vm.message('Invalid app name.', 'error');
                } else if (error) {
                    vm.message('Error renaming app.', 'error');
                } else {
                    vm.message('App renamed.', 'success');
                    vm.$refs.editModal.hideModal();
                    vm.$root.$emit('playerChange');
                    getApps(vm);
                }
            });
        },
    },
}

function setAppIdAndContentType(vm) {
    vm.appId = vm.route[1] ? parseInt(vm.route[1], 10) : null;
    if (vm.appId) {
        setAppName(vm);
        vm.contentType = vm.route[2] ? vm.route[2] : 'managers';
    }
}

function getApps(vm) {
    new AppApi().all(function(error, data) {
        if (error) { // 403 usually
            return;
        }
        vm.apps = data;
        setAppName(vm);
    });
}

function setAppName(vm) {
    const activeApp = vm.apps.filter(app => app.id === vm.appId);
    if (activeApp.length === 1) { // not yet there on page refresh
        vm.appName = activeApp[0].name;
    }
}

</script>

<style type="text/scss" scoped>
    .add-app {
        float: right;
        cursor: pointer;
    }
</style>
