<template>
<div class="container-fluid">

    <edit :type="'Plugin'" ref="editModal"
          :functionCreate="create"
          :functionDelete="deleteIt"
          :functionRename="rename"></edit>

    <div class="row mt-3">
        <div class="col-lg-12">
            <h1>Plugin Administration</h1>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-4 sticky-column">
            <div class="nc-menu card border-secondary mb-3">
                <h4 class="card-header">
                    Plugins
                    <span class="far fa-plus-square add-plugin" title="Add group"
                          @mouseover="U.addHighlight" @mouseleave="U.removeHighlight"
                          v-on:click="showCreateModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="plugin in plugins" class="nc-list-item-wrap"
                          :class="{ active: activePlugin && activePlugin.id === plugin.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: activePlugin && activePlugin.id === plugin.id }"
                           :href="`#PluginAdmin/${plugin.id}`">
                            {{ plugin.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="Edit" title="Edit"
                                  class="fa-regular fa-pen-to-square me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'warning')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'warning')"
                                  v-on:click="showEditModal(plugin)"></span>
                            <span role="img" aria-label="Delete" title="Delete"
                                  class="far fa-trash-alt me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'danger')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'danger')"
                                  v-on:click="showDeleteModal(plugin)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div v-if="activePlugin" v-cloak class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{ activePlugin.name }}</h4>
            </div>
            <div class="card border-secondary mb-3">
                <div v-cloak class="card-body">
                    <h5>Configuration</h5>

                    <label class="col-form-label w-100 mb-3">
                        Plugin
                        <select class="form-select" v-model="activePlugin.configurationDatabase.directoryName"
                                v-on:change="updateConfiguration()">
                            <option value=""></option>
                            <option v-for="option in configurations" v-bind:value="option.directoryName">
                                {{ option.type }}: {{ option.name }} ({{ option.directoryName }})
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
                               v-model="activePlugin.configurationDatabase.active">
                    </div>

                    <div class="col-form-label w-100 mb-2">
                        Required Groups
                        <multiselect v-model="requiredGroups" :options="allGroups" label="name" track-by="id"
                                     :disabled="!formEnabled"
                                     :multiple="true" :loading="false" :searchable="true" placeholder="Select groups">
                        </multiselect>
                        <div class="form-text lh-sm">
                            Groups that an account must have (one of them) to see this plugin. This is also passed
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
                                      v-model="activePlugin.configurationDatabase.textTop"></textarea>
                            <span class="form-text lh-sm d-block">Text above the list of accounts.</span>
                        </label>
                        <label class="col-form-label w-100">
                            Text Account
                            <textarea class="form-control" rows="5"
                                      v-model="activePlugin.configurationDatabase.textAccount"></textarea>
                            <span class="form-text lh-sm d-block">Text below account table.</span>
                        </label>
                        <label class="col-form-label w-100">
                            Text Register
                            <textarea class="form-control" rows="5"
                                      v-model="activePlugin.configurationDatabase.textRegister"></textarea>
                            <span class="form-text lh-sm d-block">Text below the registration form/button.</span>
                        </label>
                        <label class="col-form-label w-100">
                            Text Pending
                            <textarea class="form-control" rows="5"
                                      v-model="activePlugin.configurationDatabase.textPending"></textarea>
                            <span class="form-text lh-sm d-block">Text below an account with status "pending".</span>
                        </label>
                        <label class="col-form-label w-100">
                            Configuration Data
                            <textarea class="form-control" rows="15"
                                      v-model="activePlugin.configurationDatabase.configurationData"></textarea>
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
import {PluginAdminApi, GroupApi} from "neucore-js-client";
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
            plugins: [],
            allGroups: null,
            configurations: null,
            activePlugin: null,
            requiredGroups: '',
            URLs: [],
        }
    },

    computed: {
        formEnabled() {
            return (
                this.activePlugin.configurationDatabase.directoryName &&
                this.activePlugin.configurationDatabase.directoryName.length > 0
            );
        }
    },

    mounted() {
        window.scrollTo(0, 0);
        getList(this);
        getGroups(this, () => getPlugin(this));
        getConfigurations(this, () => getPlugin(this));
    },

    watch: {
        route() {
            getPlugin(this);
        },
    },

    methods: {
        create(name) {
            new PluginAdminApi().pluginAdminCreate(name, (error, data, response) => {
                if (response.status === 400) {
                    this.h.message('Missing name.', 'error');
                } else if (error) {
                    this.h.message('Error creating plugin.', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    this.$refs.editModal.hideModal();
                    this.h.message('Plugin created.', 'success');
                    window.location.hash = `#PluginAdmin/${data.id}`;
                    getList(this);
                }
            });
        },

        deleteIt(id) {
            new PluginAdminApi().pluginAdminDelete(id, error => {
                if (error) {
                    this.h.message('Error deleting plugin', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    this.$refs.editModal.hideModal();
                    this.h.message('Plugin deleted.', 'success');
                    window.location.hash = '#PluginAdmin';
                    getList(this);
                }
            });
        },

        rename(id, name) {
            new PluginAdminApi().pluginAdminRename(id, name, (error, data, response) => {
                if (response.status === 400) {
                    this.h.message('Missing name.', 'error');
                } else if (error) {
                    this.h.message('Error renaming plugin.', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    this.$refs.editModal.hideModal();
                    this.h.message('Plugin renamed.', 'success');
                    getList(this);
                    getPlugin(this);
                }
            });
        },

        updateConfiguration() {
            if (this.activePlugin.configurationDatabase.directoryName === '') {
                this.activePlugin.configurationDatabase.URLs = [];
                this.activePlugin.configurationDatabase.textTop = '';
                this.activePlugin.configurationDatabase.textAccount = '';
                this.activePlugin.configurationDatabase.textRegister = '';
                this.activePlugin.configurationDatabase.textPending = '';
                this.activePlugin.configurationDatabase.configurationData = '';
                this.URLs = [];

                return;
            }

            for (const config of this.configurations) {
                if (config.directoryName !== this.activePlugin.configurationDatabase.directoryName) {
                    continue;
                }

                this.activePlugin.configurationDatabase.URLs = config.URLs;
                this.activePlugin.configurationDatabase.textTop = config.textTop;
                this.activePlugin.configurationDatabase.textAccount = config.textAccount;
                this.activePlugin.configurationDatabase.textRegister = config.textRegister;
                this.activePlugin.configurationDatabase.textPending = config.textPending;
                this.activePlugin.configurationDatabase.configurationData = config.configurationData;
                this.URLs = this.activePlugin.configurationDatabase.URLs;

                return;
            }
        },

        saveConfiguration() {
            const config = this.activePlugin.configurationDatabase;
            config.URLs = this.URLs.filter(url => url.url || url.title || url.target);
            config.requiredGroups = Util.buildIdList(this.requiredGroups);
            new PluginAdminApi().pluginAdminSaveConfiguration(
                this.activePlugin.id,
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
                        getPlugin(this);
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
            this.$refs.editModal.showEditModal(this.activePlugin);
        },

        showDeleteModal() {
            this.$refs.editModal.showDeleteModal(this.activePlugin);
        },
    }
}

function getList(vm) {
    new PluginAdminApi().pluginAdminList((error, data) => {
        if (!error) {
            vm.plugins = data;
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
    new PluginAdminApi().pluginAdminConfigurations((error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.configurations = data;
        callback();
    });
}

function getPlugin(vm) {
    if (vm.allGroups === null || vm.configurations === null) {
        return; // wait for both
    }

    vm.activePlugin = null;
    vm.requiredGroups = '';
    vm.URLs = [];

    if (!vm.route[1] || !vm.h.hasRole('plugin-admin')) { // configuration object is incomplete without this role
        return;
    }

    new PluginAdminApi().pluginAdminGet(vm.route[1], (error, data) => {
        if (!error) {
            vm.activePlugin = data;
            if (vm.activePlugin.configurationDatabase) {
                vm.requiredGroups = findSelectedGroups(vm, vm.activePlugin.configurationDatabase.requiredGroups);
                vm.URLs = vm.activePlugin.configurationDatabase.URLs;
            } else {
                vm.activePlugin.configurationDatabase = {};
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
    .add-plugin {
        float: right;
        cursor: pointer;
    }
</style>
