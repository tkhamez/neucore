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
            <div class="card border-secondary mb-3">
                <h4 class="card-header">
                    Services
                    <span class="far fa-plus-square add-service" title="Add group"
                          @mouseover="mouseover" @mouseleave="mouseleave"
                          v-on:click="showCreateModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="service in services" class="list-item-wrap"
                          :class="{ active: activeService && activeService.id === service.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: activeService && activeService.id === service.id }"
                           :href="'#ServiceAdmin/' + service.id">
                            {{ service.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="edit" title="edit"
                                  class="fas fa-pencil-alt mr-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showEditModal(service)"></span>
                            <span role="img" aria-label="delete" title="delete"
                                  class="far fa-trash-alt mr-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
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
                    <div class="form-group">
                        <label class="col-form-label w-100">
                            PHP Class
                            <input type="text" class="form-control" v-model="activeService.configuration.phpClass">
                            <small class="form-text text-muted">
                                Full class name of class implementing Neucore\Plugin\ServiceInterface.
                            </small>
                        </label>
                        <label class="col-form-label w-100">
                            PSR-4 Prefix
                            <input type="text" class="form-control" v-model="activeService.configuration.psr4Prefix">
                            <small class="form-text text-muted">PHP namespace that should be autoloaded.</small>
                        </label>
                        <label class="col-form-label w-100">
                            PSR-4 Path
                            <input type="text" class="form-control" v-model="activeService.configuration.psr4Path">
                            <small class="form-text text-muted">
                                Full path to the directory containing the classes of the above namespace.
                            </small>
                        </label>
                        <label class="col-form-label w-100">
                            Required Groups
                            <input type="text" class="form-control" v-model="requiredGroups">
                            <small class="form-text text-muted">
                                Comma-separated list of group IDs that an account must have (all of them)
                                to see this service.
                            </small>
                        </label>
                        <label class="col-form-label w-100">
                            Account Properties
                            <input type="text" class="form-control" v-model="properties">
                            <small class="form-text text-muted">
                                Comma separated list of properties, possible values: username, password, email, status
                            </small>
                        </label>
                    </div>

                    <div class="custom-control custom-checkbox mb-2">
                        <input class="custom-control-input" type="checkbox" id="configShowPassword"
                               v-model="activeService.configuration.showPassword">
                        <label class="custom-control-label" for="configShowPassword">
                            Show password to user<br>
                            <small class="form-text text-muted">
                                If this is not enabled and the account contains a password (see Account Properties),
                                the user will be able to see it only once after it is reset (see Account Actions).
                            </small>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="col-form-label w-100">
                            Account Actions
                            <input type="text" class="form-control" v-model="actions">
                            <small class="form-text text-muted">
                                Comma separated list of actions: update-account, reset-password
                            </small>
                        </label>
                    </div>

                    <p class="mb-0">Link Buttons</p>
                    <small class="text-muted">Placeholders for URL: {username}, {password}, {email}</small><br>
                    <div class="form-group row" v-for="(url, idx) in URLs">
                        <label class="text-muted col-sm-2 col-form-label" :for="'configUrl'+idx">URL</label>
                        <div class="col-sm-10">
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <input type="text" class="form-control" :id="'configUrl'+idx" v-model="url.url">
                        </div>
                        <label class="text-muted col-sm-2 col-form-label" :for="'configTitle'+idx">Title</label>
                        <div class="col-sm-10">
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <input type="text" class="form-control" :id="'configTitle'+idx" v-model="url.title">
                        </div>
                        <label class="text-muted col-sm-2 col-form-label" :for="'configTarget'+idx">Target</label>
                        <div class="col-sm-10">
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <input type="text" class="form-control" :id="'configTarget'+idx" v-model="url.target">
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary mb-2" v-on:click.prevent="addUrl()">Add link</button>

                    <div class="form-group">
                        <label class="col-form-label w-100">
                            Text Top
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configuration.textTop"></textarea>
                            <small class="form-text text-muted">Text above the list of accounts.</small>
                        </label>
                        <label class="col-form-label w-100">
                            Text Account
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configuration.textAccount"></textarea>
                            <small class="form-text text-muted">Text below account table.</small>
                        </label>
                        <label class="col-form-label w-100">
                            Text Register
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configuration.textRegister"></textarea>
                            <small class="form-text text-muted">Text below the registration form/button.</small>
                        </label>
                        <label class="col-form-label w-100">
                            Text Pending
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configuration.textPending"></textarea>
                            <small class="form-text text-muted">Text below an account with status "pending"</small>
                        </label>
                        <label class="col-form-label w-100">
                            Configuration Data
                            <textarea class="form-control" rows="5"
                                      v-model="activeService.configuration.configurationData"></textarea>
                            <small class="form-text text-muted">Additional configuration for the plugin.</small>
                        </label>
                    </div>
                    <button class="btn btn-success" v-on:click.prevent="saveConfiguration">save</button>
                </div> <!-- card-body -->
            </div> <!-- card -->
        </div> <!-- col -->
    </div>
</div>
</template>

<script>
import $ from "jquery";
import {ServiceApi, ServiceAdminApi} from "neucore-js-client";
import Edit from '../components/EntityEdit.vue';

export default {
    components: {
        Edit,
    },

    props: {
        route: Array,
        player: Object,
    },

    data () {
        return {
            services: [],
            activeService: null,
            requiredGroups: '',
            properties: '',
            actions: '',
            URLs: [],
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getList(this);
        getService(this);
    },

    watch: {
        player () {
            getService(this);
        },
        route () {
            getService(this);
        },
    },

    methods: {
        create (name) {
            const vm = this;
            new ServiceAdminApi().serviceAdminCreate(name, (error, data, response) => {
                if (response.status === 400) {
                    vm.message('Missing name.', 'error');
                } else if (error) {
                    vm.message('Error creating service.', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    vm.$refs.editModal.hideModal();
                    vm.message('Service created.', 'success');
                    window.location.hash = `#ServiceAdmin/${data.id}`;
                    getList(vm);
                }
            });
        },
        deleteIt (id) {
            const vm = this;
            new ServiceAdminApi().serviceAdminDelete(id, (error) => {
                if (error) {
                    vm.message('Error deleting service', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    vm.$refs.editModal.hideModal();
                    vm.message('Service deleted.', 'success');
                    window.location.hash = '#ServiceAdmin';
                    getList(vm);
                }
            });
        },
        rename (id, name) {
            const vm = this;
            new ServiceAdminApi().serviceAdminRename(id, name, (error, data, response) => {
                if (response.status === 400) {
                    vm.message('Missing name.', 'error');
                } else if (error) {
                    vm.message('Error renaming service.', 'error');
                } else {
                    this.emitter.emit('settingsChange');
                    vm.$refs.editModal.hideModal();
                    vm.message('Service renamed.', 'success');
                    getList(vm);
                    getService(vm);
                }
            });
        },
        saveConfiguration () {
            const vm = this;
            const configuration = vm.activeService.configuration;
            configuration.URLs = vm.URLs.filter(url => url.url || url.title || url.target);
            configuration.requiredGroups = vm.requiredGroups ? vm.requiredGroups.split(',') : [];
            configuration.properties = vm.properties ? vm.properties.split(',') : [];
            configuration.actions = vm.actions ? vm.actions.split(',') : [];
            new ServiceAdminApi().serviceAdminSaveConfiguration(
                this.activeService.id,
                {configuration: JSON.stringify(configuration)},
                (error, data, response) => {
                    if (response.status === 400) {
                        vm.message('Missing name.', 'error');
                    } else if (error) {
                        vm.message('Error updating configuration.', 'error');
                    } else {
                        this.emitter.emit('settingsChange');
                        vm.$refs.editModal.hideModal();
                        vm.message('Configuration updated.', 'success');
                        getList(vm);
                        getService(vm);
                    }
                }
            );
        },

        addUrl() {
            this.URLs.push({ url: '', title: '', target: '' });
        },

        mouseover (ele) {
            $(ele.target).addClass('text-warning');
        },
        mouseleave (ele) {
            $(ele.target).removeClass('text-warning');
        },
        showCreateModal: function() {
            this.$refs.editModal.showCreateModal();
        },
        showEditModal: function() {
            this.$refs.editModal.showEditModal(this.activeService);
        },
        showDeleteModal: function() {
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

function getService(vm) {
    vm.activeService = null;
    vm.requiredGroups = '';
    vm.properties = '';
    vm.actions = '';
    vm.URLs = [];
    if (!vm.route[1] || !vm.hasRole('service-admin')) { // configuration object is incomplete without this role
        return;
    }
    new ServiceApi().serviceGet(vm.route[1], {allowAdmin: 'true'}, (error, data) => {
        if (!error) {
            vm.activeService = data;
            if (vm.activeService.configuration) {
                vm.requiredGroups = vm.activeService.configuration.requiredGroups.join(',');
                vm.properties = vm.activeService.configuration.properties.join(',');
                vm.actions = vm.activeService.configuration.actions.join(',');
                vm.URLs = vm.activeService.configuration.URLs;
            } else {
                vm.activeService.configuration = {};
            }
        }
    });
}
</script>

<style scoped>
    .add-service {
        float: right;
        cursor: pointer;
    }
</style>
