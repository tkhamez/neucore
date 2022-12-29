<template>
<div class="container-fluid">

    <edit :type="'Service'" ref="editModal"
          :functionCreate="create"
          :functionDelete="deleteIt"
          :functionRename="rename"></edit>

    <div class="row mt-3">
        <div class="col-lg-12">
            <h1>Service Administration</h1>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-4 sticky-column">
            <div class="nc-menu card border-secondary mb-3">
                <h4 class="card-header">
                    Services
                    <span class="far fa-plus-square add-service" title="Add group"
                          @mouseover="U.addHighlight" @mouseleave="U.removeHighlight"
                          v-on:click="showCreateModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="service in services" class="nc-list-item-wrap"
                          :class="{ active: activeService && activeService.id === service.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: activeService && activeService.id === service.id }"
                           :href="`#ServiceAdmin/${service.id}`">
                            {{ service.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="Edit" title="Edit"
                                  class="fa-regular fa-pen-to-square me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'warning')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'warning')"
                                  v-on:click="showEditModal(service)"></span>
                            <span role="img" aria-label="Delete" title="Delete"
                                  class="far fa-trash-alt me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'danger')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'danger')"
                                  v-on:click="showDeleteModal(service)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div v-if="activeService" v-cloak class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{ activeService.name }}</h4>
            </div>
            <div class="card border-secondary mb-3">
                <div v-cloak class="card-body">
                    <h5>Configuration</h5>

                    <label class="col-form-label w-100 mb-3">
                        Plugin
                        <select class="form-select" v-model="activeService.configurationDatabase.directoryName"
                                v-on:change="updateConfiguration()">
                            <option value=""></option>
                            <option v-for="option in configurations" v-bind:value="option.directoryName">
                                {{ option.name }}
                                ({{ option.directoryName }})
                            </option>
                        </select>
                        <span class="form-text lh-sm d-block text-warning">
                            Attention: changing this will update the "Presets" values below
                            with the default values from the plugin.
                        </span>
                    </label>

                    <div class="form-check mb-2">
                        <label class="form-check-label" for="configActive">
                            Active<br>
                            <span class="form-text lh-sm d-block">
                                Uncheck to disable for cron job and hide from users.
                            </span>
                        </label>
                        <input class="form-check-input" type="checkbox" id="configActive" :disabled="!formEnabled"
                               v-model="activeService.configurationDatabase.active">
                    </div>

                    <div class="col-form-label w-100 mb-2">
                        Required Groups
                        <multiselect v-model="requiredGroups" :options="allGroups" label="name" track-by="id"
                                     :disabled="!formEnabled"
                                     :multiple="true" :loading="false" :searchable="true" placeholder="Select groups">
                        </multiselect>
                        <div class="form-text lh-sm">
                            Groups that an account must have (one of them) to see this service. This is also passed
                            to the plugin so accounts can be removed if a player loses all groups.
                        </div>
                    </div>

                    <fieldset class="border p-2 mb-3">
                        <legend class="float-none w-auto p-2 mb-0 fs-6">Presets</legend>
                        <p class="small">
                            The following configuration values can optionally be preset by the plugin and
                            overwritten here.
                        </p>

                        <p class="mb-0">Link Buttons</p>
                        <small class="text-muted">
                            Placeholders for URL: {plugin_id}, {username}, {password}, {email}
                        </small>
                        <br>
                        <div class="row mb-2" v-for="(url, idx) in URLs">
                            <label class="text-muted col-sm-2 col-form-label" :for="`configUrl${idx}`">URL</label>
                            <div class="col-sm-10">
                                <!--suppress HtmlFormInputWithoutLabel -->
                                <input type="text" class="form-control" :id="`configUrl${idx}`" v-model="url.url">
                            </div>
                            <label class="text-muted col-sm-2 col-form-label" :for="`configTitle${idx}`">Title</label>
                            <div class="col-sm-10">
                                <!--suppress HtmlFormInputWithoutLabel -->
                                <input type="text" class="form-control" :id="`configTitle${idx}`" v-model="url.title">
                            </div>
                            <label class="text-muted col-sm-2 col-form-label" :for="`configTarget${idx}`">Target</label>
                            <div class="col-sm-10">
                                <!--suppress HtmlFormInputWithoutLabel -->
                                <input type="text" class="form-control" :id="`configTarget${idx}`" v-model="url.target">
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary mb-2" v-on:click.prevent="addUrl()"
                                :disabled="!formEnabled">Add link</button><br>
                        <small class="text-muted">Note: To remove a link button clear all fields and save.</small>

                        <label class="col-form-label w-100 mt-2">
                            Text Top
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configurationDatabase.textTop"></textarea>
                            <span class="form-text lh-sm d-block">Text above the list of accounts.</span>
                        </label>
                        <label class="col-form-label w-100">
                            Text Account
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configurationDatabase.textAccount"></textarea>
                            <span class="form-text lh-sm d-block">Text below account table.</span>
                        </label>
                        <label class="col-form-label w-100">
                            Text Register
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configurationDatabase.textRegister"></textarea>
                            <span class="form-text lh-sm d-block">Text below the registration form/button.</span>
                        </label>
                        <label class="col-form-label w-100">
                            Text Pending
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configurationDatabase.textPending"></textarea>
                            <span class="form-text lh-sm d-block">Text below an account with status "pending".</span>
                        </label>
                        <label class="col-form-label w-100">
                            Configuration Data
                            <textarea class="form-control" rows="15"
                                      v-model="activeService.configurationDatabase.configurationData"></textarea>
                            <span class="form-text lh-sm d-block">Additional configuration for the plugin.</span>
                        </label>
                    </fieldset>

                    <button class="mt-3 btn btn-success" :disabled="!formEnabled"
                            v-on:click.prevent="saveConfiguration">Save</button>
                    <span v-if="!formEnabled" class="small align-bottom"> Choose a plugin first</span>
                    <span class="form-text text-warning lh-sm d-block mt-1">
                        If you have changed the plugin, make sure that this does not delete any configuration
                        values that you still need.
                    </span>
                </div> <!-- card-body -->
            </div> <!-- card -->
        </div> <!-- col -->
    </div>
</div>
</template>

<script>
import {toRef} from "vue";
import Multiselect from '@suadelabs/vue3-multiselect';
import {ServiceApi, ServiceAdminApi, GroupApi} from "neucore-js-client";
import Helper from "../../classes/Helper";
import Util from "../../classes/Util";
import Edit from '../../components/EntityEdit.vue';

export default {
    components: {
        Edit,
        Multiselect,
    },

    props: {
        route: Array,
    },

    inject: ['store'],

    data() {
        return {
            h: new Helper(this),
            U: Util,
            settings: toRef(this.store.state, 'settings'),
            services: [],
            allGroups: null,
            configurations: null,
            activeService: null,
            requiredGroups: '',
            URLs: [],
        }
    },

    computed: {
        formEnabled() {
            return (
                this.activeService.configurationDatabase.directoryName &&
                this.activeService.configurationDatabase.directoryName.length > 0
            );
        }
    },

    mounted() {
        window.scrollTo(0, 0);
        getList(this);
        getGroups(this, () => getService(this));
        getConfigurations(this, () => getService(this));
    },

    watch: {
        route() {
            getService(this);
        },
    },

    methods: {
        create(name) {
            new ServiceAdminApi().serviceAdminCreate(name, (error, data, response) => {
                if (response.status === 400) {
                    this.h.message('Missing name.', 'error');
                } else if (error) {
                    this.h.message('Error creating service.', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    this.$refs.editModal.hideModal();
                    this.h.message('Service created.', 'success');
                    window.location.hash = `#ServiceAdmin/${data.id}`;
                    getList(this);
                }
            });
        },

        deleteIt(id) {
            new ServiceAdminApi().serviceAdminDelete(id, error => {
                if (error) {
                    this.h.message('Error deleting service', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    this.$refs.editModal.hideModal();
                    this.h.message('Service deleted.', 'success');
                    window.location.hash = '#ServiceAdmin';
                    getList(this);
                }
            });
        },

        rename(id, name) {
            new ServiceAdminApi().serviceAdminRename(id, name, (error, data, response) => {
                if (response.status === 400) {
                    this.h.message('Missing name.', 'error');
                } else if (error) {
                    this.h.message('Error renaming service.', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    this.$refs.editModal.hideModal();
                    this.h.message('Service renamed.', 'success');
                    getList(this);
                    getService(this);
                }
            });
        },

        updateConfiguration() {
            if (this.activeService.configurationDatabase.directoryName === '') {
                this.activeService.configurationDatabase.URLs = [];
                this.activeService.configurationDatabase.textTop = '';
                this.activeService.configurationDatabase.textAccount = '';
                this.activeService.configurationDatabase.textRegister = '';
                this.activeService.configurationDatabase.textPending = '';
                this.activeService.configurationDatabase.configurationData = '';
                this.URLs = [];

                return;
            }

            for (const config of this.configurations) {
                if (config.directoryName !== this.activeService.configurationDatabase.directoryName) {
                    continue;
                }

                this.activeService.configurationDatabase.URLs = config.URLs;
                this.activeService.configurationDatabase.textTop = config.textTop;
                this.activeService.configurationDatabase.textAccount = config.textAccount;
                this.activeService.configurationDatabase.textRegister = config.textRegister;
                this.activeService.configurationDatabase.textPending = config.textPending;
                this.activeService.configurationDatabase.configurationData = config.configurationData;
                this.URLs = this.activeService.configurationDatabase.URLs;

                return;
            }
        },

        saveConfiguration() {
            const config = this.activeService.configurationDatabase;
            config.URLs = this.URLs.filter(url => url.url || url.title || url.target);
            config.requiredGroups = Util.buildIdList(this.requiredGroups);
            new ServiceAdminApi().serviceAdminSaveConfiguration(
                this.activeService.id,
                {configuration: JSON.stringify(config)},
                (error, data, response) => {
                    if (response.status === 400) {
                        this.h.message('Missing name.', 'error');
                    } else if (error) {
                        this.h.message('Error updating configuration.', 'error');
                    } else {
                        this.emitter.emit('settingsChange');
                        this.$refs.editModal.hideModal();
                        this.h.message('Configuration updated.', 'success');
                        getList(this);
                        getService(this);
                    }
                }
            );
        },

        addUrl() {
            this.URLs.push({ url: '', title: '', target: '' });
        },

        showCreateModal() {
            this.$refs.editModal.showCreateModal();
        },

        showEditModal() {
            this.$refs.editModal.showEditModal(this.activeService);
        },

        showDeleteModal() {
            this.$refs.editModal.showDeleteModal(this.activeService);
        },
    }
}

function getList(vm) {
    new ServiceAdminApi().serviceAdminList((error, data) => {
        if (!error) {
            vm.services = data;
        }
    });
}

function getGroups(vm, callback) {
    new GroupApi().userGroupAll((error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.allGroups = data;
        callback();
    });
}

function getConfigurations(vm, callback) {
    new ServiceAdminApi().serviceAdminConfigurations((error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.configurations = data;
        callback();
    });
}

function getService(vm) {
    if (vm.allGroups === null || vm.configurations === null) {
        return; // wait for both
    }

    vm.activeService = null;
    vm.requiredGroups = '';
    vm.URLs = [];

    if (!vm.route[1] || !vm.h.hasRole('service-admin')) { // configuration object is incomplete without this role
        return;
    }

    new ServiceApi().serviceGet(vm.route[1], {allowAdmin: 'true'}, (error, data) => {
        if (!error) {
            vm.activeService = data;
            if (vm.activeService.configurationDatabase) {
                vm.requiredGroups = findSelectedGroups(vm, vm.activeService.configurationDatabase.requiredGroups);
                vm.URLs = vm.activeService.configurationDatabase.URLs;
            } else {
                vm.activeService.configurationDatabase = {};
            }
        }
    });
}

/**
 * @param vm
 * @param {array} selectedIds
 * @return {array}
 */
function findSelectedGroups(vm, selectedIds) {
    const groups = [];
    for (const group of vm.allGroups) {
        if (selectedIds && selectedIds.indexOf(group.id) !== -1) {
            groups.push(group);
        }
    }
    return groups;
}
</script>

<style scoped>
    .add-service {
        float: right;
        cursor: pointer;
    }
</style>
