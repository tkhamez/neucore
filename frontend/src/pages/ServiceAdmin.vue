<template>
    <div class="container-fluid">

        <edit :type="'Service'" ref="editModal"
              :functionCreate="create"
              :functionDelete="deleteIt"
              :functionRename="rename"></edit>

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
                        <div class="form-group">
                            <label for="configuration" class="col-form-label">Configuration</label><br>
                            <textarea v-model="configuration" class="form-control"
                                      id="configuration" rows="15"></textarea>
                            <button class="btn btn-success" v-on:click="saveConfiguration">save</button>
                        </div>
                    </div>
                </div>
            </div>
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
    },

    data () {
        return {
            services: [],
            activeService: null,
            configuration: '',
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getList(this);
        getService(this);
    },

    watch: {
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
                    vm.$refs.editModal.hideModal();
                    vm.message('Service renamed.', 'success');
                    getList(vm);
                    getService(vm);
                }
            });
        },
        saveConfiguration () {
            const vm = this;
            new ServiceAdminApi().serviceAdminSaveConfiguration(
                this.activeService.id,
                {configuration: vm.configuration},
                (error, data, response) => {
                    if (response.status === 400) {
                        vm.message('Missing name.', 'error');
                    } else if (error) {
                        vm.message('Error renaming service.', 'error');
                    } else {
                        vm.$refs.editModal.hideModal();
                        vm.message('Service renamed.', 'success');
                        getList(vm);
                        getService(vm);
                    }
                }
            );
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
    vm.configuration = '';
    if (!vm.route[1]) {
        return;
    }
    new ServiceApi().serviceGet(vm.route[1], (error, data) => {
        if (!error) {
            vm.activeService = data;
            vm.configuration = data.configuration;
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
