<template>

<edit :type="'EveLogin'" ref="editModal"
      :functionCreate="create"
      :functionDelete="deleteIt"></edit>

<div class="row">
    <div class="col-lg-4 sticky-column">
        <div class="card border-secondary mb-3">
            <h4 class="card-header">
                EVE Logins
                <span class="far fa-plus-square add-login" title="Add group"
                      @mouseover="mouseover" @mouseleave="mouseleave"
                      v-on:click="showCreateModal()"></span>
            </h4>
            <div class="list-group">
                <span v-for="login in logins" class="list-item-wrap"
                      :class="{ active: activeLogin && activeLogin.id === login.id }">
                    <a class="list-group-item list-group-item-action"
                       :class="{ active: activeLogin && activeLogin.id === login.id }"
                       :href="'#SystemSettings/EveLogins/' + login.id">
                        {{ login.name }}
                    </a>
                    <span v-if="login.name.indexOf(protectedLoginsPrefix) === -1" v-cloak class="entity-actions">
                        <span role="img" aria-label="delete" title="delete"
                              class="far fa-trash-alt mr-1"
                              @mouseover="mouseover" @mouseleave="mouseleave"
                              v-on:click="showDeleteModal(login)"></span>
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-secondary mb-3">
            <h4 v-if="activeLogin" v-cloak class="card-header">{{ activeLogin.name }}</h4>
            <div class="card-body">
                <p v-if="activeLogin" v-cloak>
                    Login URL <a :href="loginUrl">{{ loginUrl }}</a>.
                </p>
                <div v-if="activeLogin" v-cloak class="form-group mb-0">
                    <label class="col-form-label w-100">
                        Name
                        <input type="text" class="form-control" :disabled="disabled"
                               v-model="activeLogin.name" maxlength="20">
                        <small class="form-text text-muted small-line-height">
                            {{ messages.itemNameAllowedCharsHelp }}<br>
                            Maximum length 20.
                        </small>
                    </label>
                    <label class="col-form-label w-100">
                        Description
                        <input type="text" class="form-control" :disabled="disabled"
                               v-model="activeLogin.description" maxlength="1024">
                        <small class="form-text text-muted">Maximum length 1024.</small>
                    </label>
                    <label class="col-form-label w-100">
                        ESI Scopes
                        <input type="text" class="form-control" :disabled="disabled"
                               v-model="activeLogin.esiScopes" maxlength="8192">
                        <small class="form-text text-muted">Separated by one space, maximum length 8192.</small>
                    </label>
                    <label class="col-form-label w-100 pb-1">
                        EVE Roles
                        <multiselect v-model="activeLogin.eveRoles" :options="allEveRoles" :multiple="true"
                                     :disabled="disabled" :loading="false" :searchable="true" placeholder="Select roles">
                        </multiselect>
                    </label>
                    <p class="text-muted small small-line-height">
                        Select required in-game roles. This requires the
                        <strong>esi-characters.read_corporation_roles.v1</strong> ESI scope.<br>
                        This is only for "normal" roles, not roles at base/hq/other.
                    </p>
                    <div class="mt-3">
                        <button class="btn btn-success" :disabled="disabled" v-on:click.prevent="update">save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</template>

<script>

import $ from "jquery";
import {SettingsApi} from "neucore-js-client";
import Multiselect from '@suadelabs/vue3-multiselect';
import Edit from '../components/EntityEdit.vue';

export default {
    components: {
        Edit,
        Multiselect,
    },

    props: {
        route: Array,
    },

    data () {
        return {
            logins: [],
            protectedLoginsPrefix: 'core.',
            activeLogin: null,
            loginUrl: null,
            disabled: false,
            allEveRoles: [],
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getLogins(this);
        getRoles(this);
    },

    watch: {
        route () {
            getLogin(this);
        },
    },

    methods: {
        mouseover (ele) {
            $(ele.target).addClass('text-warning');
        },

        mouseleave (ele) {
            $(ele.target).removeClass('text-warning');
        },

        showCreateModal: function() {
            this.$refs.editModal.showCreateModal();
        },

        showDeleteModal: function() {
            this.$refs.editModal.showDeleteModal(this.activeLogin);
        },

        create (id) {
            const vm = this;
            new SettingsApi().userSettingsEveLoginCreate(id, (error, data, response) => {
                if (response.status === 400) {
                    vm.message('Missing or invalid name.', 'error');
                } else if (error) {
                    vm.message('Error creating login.', 'error');
                } else {
                    vm.$refs.editModal.hideModal();
                    vm.message('Login created.', 'success');
                    window.location.hash = `#SystemSettings/EveLogins/${data.id}`;
                    getLogins(vm);
                }
            });
        },

        deleteIt (id) {
            const vm = this;
            new SettingsApi().userSettingsEveLoginDelete(id, (error) => {
                if (error) {
                    vm.message('Error deleting login', 'error');
                } else {
                    vm.$refs.editModal.hideModal();
                    vm.message('Login deleted.', 'success');
                    window.location.hash = '#SystemSettings/EveLogins';
                    getLogins(vm);
                }
            });
        },

        update () {
            const vm = this;
            new SettingsApi().userSettingsEveLoginUpdate(vm.activeLogin, (error, data, response) => {
                if (response.status === 400) {
                    vm.message('Missing or invalid name.', 'error');
                } else if (error) {
                    vm.message('Error saving login.', 'error');
                } else {
                    vm.message('Login saved.', 'success');
                    getLogins(vm);
                }
            });
        }
    },
}

function getRoles(vm) {
    new SettingsApi().userSettingsEveLoginRoles((error, data) => {
        if (!error) {
            vm.allEveRoles = data;
        }
    });
}

function getLogins(vm) {
    new SettingsApi().userSettingsEveLoginList((error, data) => {
        if (!error) {
            vm.logins = data;
            getLogin(vm);
        }
    });
}

function getLogin(vm) {
    vm.activeLogin = null;
    if (!vm.route[2]) {
        return;
    }
    for (const login of vm.logins) {
        if (login.id === parseInt(vm.route[2], 10)) {
            vm.activeLogin = { ...login };
            vm.loginUrl = `${vm.$root.envVars.backendHost}/login/${vm.activeLogin.name}`
            vm.disabled = login.name.indexOf(vm.protectedLoginsPrefix) === 0;
        }
    }
}
</script>

<style scoped>
    .add-login {
        float: right;
        cursor: pointer;
    }

    .small-line-height {
        line-height: 1.25;
    }
</style>
