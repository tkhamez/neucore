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
                           :href="`#ServiceAdmin/${service.id}`">
                            {{ service.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="edit" title="edit"
                                  class="fas fa-pencil-alt me-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showEditModal(service)"></span>
                            <span role="img" aria-label="delete" title="delete"
                                  class="far fa-trash-alt me-1"
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

                    <label class="col-form-label w-100">
                        PHP Class
                        <input type="text" class="form-control" v-model="activeService.configuration.phpClass">
                        <span class="form-text">
                            Full class name of class implementing Neucore\Plugin\ServiceInterface.
                        </span>
                    </label>
                    <label class="col-form-label w-100">
                        PSR-4 Prefix
                        <input type="text" class="form-control" v-model="activeService.configuration.psr4Prefix">
                        <span class="form-text">PHP namespace that should be autoloaded.</span>
                    </label>
                    <label class="col-form-label w-100">
                        PSR-4 Path
                        <input type="text" class="form-control" v-model="activeService.configuration.psr4Path">
                        <span class="form-text">
                            Full path to the directory containing the classes of the above namespace.
                        </span>
                    </label>

                    <div class="form-check">
                        <label class="form-check-label" for="configOneAccount">
                            Limit to one service account<br>
                            <span class="form-text">
                                Check this if the service allows only  one account per player instead of
                                one per character.
                            </span>
                        </label>
                        <input class="form-check-input" type="checkbox" id="configOneAccount"
                               v-model="activeService.configuration.oneAccount">
                    </div>

                    <label class="col-form-label w-100">
                        Required Groups
                        <input type="text" class="form-control" v-model="requiredGroups">
                        <span class="form-text">
                            Comma-separated list of group IDs that an account must have (one of them)
                            to see this service and not be kicked off the server (if kicks are enabled).
                        </span>
                    </label>
                    <label class="col-form-label w-100">
                        Account Properties
                        <input type="text" class="form-control" v-model="properties">
                        <span class="form-text">
                            Comma separated list of properties, possible values: username, password, email, status,
                            name
                        </span>
                    </label>

                    <div class="form-check">
                        <label class="form-check-label" for="configShowPassword">
                            Show password to user<br>
                            <span class="form-text">
                                If this is not enabled and the account contains a password (see Account Properties),
                                the user will be able to see it only once after it is reset (see Account Actions).
                            </span>
                        </label>
                        <input class="form-check-input" type="checkbox" id="configShowPassword"
                               v-model="activeService.configuration.showPassword">
                    </div>

                    <label class="col-form-label w-100">
                        Account Actions
                        <input type="text" class="form-control" v-model="actions">
                        <span class="form-text">
                            Comma separated list of actions: update-account, reset-password
                        </span>
                    </label>

                    <p class="mb-0">Link Buttons</p>
                    <small class="text-muted">Placeholders for URL: {username}, {password}, {email}</small><br>
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
                    <button class="btn btn-sm btn-primary mb-2" v-on:click.prevent="addUrl()">Add link</button>

                    <label class="col-form-label w-100">
                        Text Top
                        <textarea class="form-control" rows="5"
                                  v-model="activeService.configuration.textTop"></textarea>
                        <span class="form-text">Text above the list of accounts.</span>
                    </label>
                    <label class="col-form-label w-100">
                        Text Account
                        <textarea class="form-control" rows="5"
                                  v-model="activeService.configuration.textAccount"></textarea>
                        <span class="form-text">Text below account table.</span>
                    </label>
                    <label class="col-form-label w-100">
                        Text Register
                        <textarea class="form-control" rows="5"
                                  v-model="activeService.configuration.textRegister"></textarea>
                        <span class="form-text">Text below the registration form/button.</span>
                    </label>
                    <label class="col-form-label w-100">
                        Text Pending
                        <textarea class="form-control" rows="5"
                                  v-model="activeService.configuration.textPending"></textarea>
                        <span class="form-text">Text below an account with status "pending"</span>
                    </label>
                    <label class="col-form-label w-100">
                        Configuration Data
                        <textarea class="form-control" rows="10"
                                  v-model="activeService.configuration.configurationData"></textarea>
                        <span class="form-text">Additional configuration for the plugin.</span>
                    </label>

                    <button class="mt-3 btn btn-success" v-on:click.prevent="saveConfiguration">save</button>
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
