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
                           :href="`#AppAdmin/${app.id}/${contentType}`">
                            {{ app.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="edit" title="edit"
                                  class="fas fa-pencil-alt me-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showRenameAppModal(app)"></span>
                            <span role="img" aria-label="delete" title="delete"
                                  class="far fa-trash-alt me-1"
                                  @mouseover="mouseover"  @mouseleave="mouseleave"
                                  v-on:click="showDeleteAppModal(app)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div v-cloak v-if="appId" class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{appName}}</h4>
            </div>
            <ul class="nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'managers' }"
                       :href="`#AppAdmin/${appId}/managers`">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'groups' }"
                       :href="`#AppAdmin/${appId}/groups`">Groups</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'roles' }"
                       :href="`#AppAdmin/${appId}/roles`">Roles</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'eveLogins' }"
                       :href="`#AppAdmin/${appId}/eveLogins`">EVE Logins</a>
                </li>
            </ul>

            <admin v-cloak v-if="appId" ref="admin"
                   :contentType="contentType" :typeId="appId"
                   :type="'App'" :searchCurrentOnly="true"></admin>

        </div>
    </div>
</div>
</template>

<script>
import $ from 'jquery';
import {AppApi} from 'neucore-js-client';
import Helper from "../../classes/Helper";
import Edit  from '../../components/EntityEdit.vue';
import Admin from '../../components/EntityRelationEdit.vue';

export default {
    components: {
        Edit,
        Admin,
    },

    props: {
        route: Array,
    },

    data() {
        return {
            h: new Helper(this),
            apps: [],
            appId: null, // current app
            appName: '',
            contentType: '',
        }
    },

    mounted() {
        window.scrollTo(0,0);
        getApps(this);
        setAppIdAndContentType(this);
    },

    watch: {
        route() {
            setAppIdAndContentType(this);
        },
    },

    methods: {
        mouseover(ele) {
            $(ele.target).addClass('text-warning');
        },

        mouseleave(ele) {
            $(ele.target).removeClass('text-warning');
        },

        showCreateAppModal() {
            this.$refs.editModal.showCreateModal();
        },

        showDeleteAppModal(app) {
            this.$refs.editModal.showDeleteModal(app);
        },

        showRenameAppModal(app) {
            this.$refs.editModal.showEditModal(app);
        },

        create(name) {
            new AppApi().create(name, (error, data, response) => {
                if (response.status === 409) {
                    this.h.message('An app with this name already exists.', 'error');
                } else if (response.status === 400) {
                    this.h.message('Invalid name.', 'error');
                } else if (error) {
                    this.h.message('Error creating app.', 'error');
                } else {
                    this.$refs.editModal.hideModal();
                    this.h.message('App created.', 'success');
                    window.location.hash = `#AppAdmin/${data.id}`;
                    getApps(this);
                }
            });
        },

        deleteIt(id) {
            new AppApi().callDelete(id, error => {
                if (error) {
                    this.h.message('Error deleting app', 'error');
                } else {
                    this.$refs.editModal.hideModal();
                    this.h.message('App deleted.', 'success');
                    window.location.hash = '#AppAdmin';
                    this.emitter.emit('playerChange'); // current player could have been a manager
                    this.appId = null;
                    this.contentType = '';
                    getApps(this);
                }
            });
        },

        rename(id, name) {
            new AppApi().rename(id, name, (error, data, response) => {
                if (response.status === 409) {
                    this.h.message('An app with this name already exists.', 'error');
                } else if (response.status === 400) {
                    this.h.message('Invalid app name.', 'error');
                } else if (error) {
                    this.h.message('Error renaming app.', 'error');
                } else {
                    this.h.message('App renamed.', 'success');
                    this.$refs.editModal.hideModal();
                    this.emitter.emit('playerChange');
                    getApps(this);
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
    new AppApi().userAppAll((error, data) => {
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

<style scoped>
    .add-app {
        float: right;
        cursor: pointer;
    }
</style>
