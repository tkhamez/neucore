<template>
<div class="container-fluid">

    <edit :type="'EveLogin'" ref="editModal"
          :functionCreate="create"
          :functionDelete="deleteIt"></edit>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>EVE Logins Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="nc-menu card border-secondary mb-3">
                <h4 class="card-header">
                    EVE Logins
                    <span class="far fa-plus-square add-login" title="Add group"
                          @mouseover="U.addHighlight" @mouseleave="U.removeHighlight"
                          v-on:click="showCreateModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="login in logins" class="nc-list-item-wrap"
                          :class="{ active: activeLogin && activeLogin.id === login.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: activeLogin && activeLogin.id === login.id }"
                           :href="`#EVELoginAdmin/${login.id}/${contentType}`">
                            {{ login.name }}
                        </a>
                        <span v-cloak v-if="login.name.indexOf(Data.loginPrefixProtected) === -1"
                              class="entity-actions">
                            <span role="img" aria-label="Delete" title="Delete"
                                  class="far fa-trash-alt me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'danger')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'danger')"
                                  v-on:click="showDeleteModal(login)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div v-cloak v-if="activeLogin" class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{ activeLogin.name }}</h4>
            </div>

            <ul class="nc-nav nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'login' }"
                       :href="`#EVELoginAdmin/${activeLogin.id}/login`">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{
                           'active': contentType === 'tokens',
                           'disabled': activeLogin.name === Data.loginNames.default
                       }"
                       :href="`#EVELoginAdmin/${activeLogin.id}/tokens`">Tokens</a>
                </li>
            </ul>

            <div v-cloak v-if="contentType === 'login'" class="card border-secondary mb-3">
                <div class="card-body">
                    <p v-cloak v-if="activeLogin">
                        Login URL <a :href="loginUrl">{{ loginUrl }}</a>
                    </p>
                    <div v-cloak v-if="activeLogin">
                        <label class="col-form-label w-100 pb-1">
                            Name
                            <input type="text" class="form-control" :disabled="disabled"
                                   v-model="activeLogin.name" maxlength="20">
                        </label>
                        <p class="text-muted small lh-sm">
                            {{ Data.messages.itemNameAllowedCharsHelp }}<br>
                            Maximum length 20.
                        </p>
                        <label class="col-form-label w-100">
                            Description
                            <input type="text" class="form-control" :disabled="disabled"
                                   v-model="activeLogin.description" maxlength="1024">
                            <span class="form-text">Maximum length 1024.</span>
                        </label>
                        <label class="col-form-label w-100">
                            ESI Scopes
                            <input type="text" class="form-control" :disabled="disabled"
                                   v-model="activeLogin.esiScopes" maxlength="8192">
                            <span class="form-text">Separated by one space, maximum length 8192.</span>
                        </label>
                        <label class="col-form-label w-100 pb-1" for="eveLoginAdminRoles">
                            EVE Roles
                            <multiselect v-model="activeLogin.eveRoles" :options="allEveRoles" :multiple="true"
                                         id="eveLoginAdminRoles"
                                         :disabled="disabled" :loading="false" :searchable="true"
                                         placeholder="Select roles">
                            </multiselect>
                        </label>
                        <div class="form-text lh-sm">
                            Select required in-game roles. This requires the
                            <strong>esi-characters.read_corporation_roles.v1</strong> ESI scope.<br>
                            This is only for "normal" roles, not roles at base/hq/other.
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-success" :disabled="disabled" v-on:click.prevent="update">
                                Save
                            </button>
                        </div>
                    </div>
                </div> <!-- card-body -->
            </div> <!-- card -->

            <div v-cloak v-if="contentType === 'tokens'" class="card border-secondary mb-3 table-responsive">
                <table class="table table-hover mb-0 nc-table-sm" aria-describedby="ESI Tokens">
                    <thead>
                        <tr>
                            <th scope="col" colspan="2">Character</th>
                            <th scope="col">Account</th>
                            <th scope="col" colspan="2">Corporation</th>
                            <th scope="col" colspan="2">Alliance</th>
                            <th scope="col">Valid</th>
                            <th scope="col">Has Roles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="token in tokens">
                            <td>{{ token.character ? token.character.id : '' }}</td>
                            <td>{{ token.character ? token.character.name : '' }}</td>
                            <td>
                                <span v-if="h.hasRole('user-chars')">
                                    <a href="#" v-on:click.prevent="h.showCharacters(token.playerId)">
                                        {{ token.playerName }}
                                    </a>
                                </span>
                                <span v-else>{{ token.playerName }}</span>
                            </td>
                            <td>
                                {{
                                    token.character && token.character.corporation ?
                                        token.character.corporation.ticker :
                                        ''
                                }}
                            </td>
                            <td>
                                {{
                                    token.character && token.character.corporation ?
                                        token.character.corporation.name :
                                        ''
                                }}
                            </td>
                            <td>
                                {{
                                    token.character &&
                                    token.character.corporation &&
                                    token.character.corporation.alliance ?
                                        token.character.corporation.alliance.ticker :
                                        ''
                                }}
                            </td>
                            <td>
                                {{
                                    token.character &&
                                    token.character.corporation &&
                                    token.character.corporation.alliance ?
                                        token.character.corporation.alliance.name :
                                        ''
                                }}
                            </td>
                            <td>
                                <span v-if="token.validToken">Yes</span>
                                <span v-if="token.validToken === false">No</span>
                                <span v-if="token.validToken === null">n/a</span>
                            </td>
                            <td>
                                <span v-if="token.hasRoles">Yes</span>
                                <span v-if="token.hasRoles === false">No</span>
                                <span v-if="token.hasRoles === null">n/a</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div> <!-- card -->

        </div> <!-- col -->

    </div>
</div>
</template>

<script>
import {SettingsApi} from "neucore-js-client";
import Multiselect from '@suadelabs/vue3-multiselect';
import Data from '../../classes/Data';
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

    data() {
        return {
            Data: Data,
            h: new Helper(this),
            U: Util,
            logins: [],
            tokens: [],
            activeLogin: null,
            contentType: 'login',
            loginUrl: null,
            disabled: false,
            allEveRoles: [],
        }
    },

    mounted() {
        window.scrollTo(0,0);
        fetchLogins(this);
        fetchRoles(this);
    },

    watch: {
        route() {
            if (getLogin(this)) {
                fetchTokens(this);
            }
        },
    },

    methods: {
        showCreateModal() {
            this.$refs.editModal.showCreateModal();
        },

        showDeleteModal() {
            this.$refs.editModal.showDeleteModal(this.activeLogin);
        },

        create(id) {
            new SettingsApi().userSettingsEveLoginCreate(id, (error, data, response) => {
                if (response.status === 400) {
                    this.h.message('Missing or invalid name.', 'error');
                } else if (error) {
                    this.h.message('Error creating login.', 'error');
                } else {
                    this.$refs.editModal.hideModal();
                    this.h.message('Login created.', 'success');
                    window.location.hash = `#EVELoginAdmin/${data.id}`;
                    fetchLogins(this);
                }
            });
        },

        deleteIt(id) {
            new SettingsApi().userSettingsEveLoginDelete(id, error => {
                if (error) {
                    this.h.message('Error deleting login', 'error');
                } else {
                    this.$refs.editModal.hideModal();
                    this.h.message('Login deleted.', 'success');
                    window.location.hash = '#EVELoginAdmin';
                    fetchLogins(this);
                }
            });
        },

        update() {
            new SettingsApi().userSettingsEveLoginUpdate(this.activeLogin, (error, data, response) => {
                if (response.status === 400) {
                    this.h.message('Missing or invalid name.', 'error');
                } else if (error) {
                    this.h.message('Error saving login.', 'error');
                } else {
                    this.h.message('Login saved.', 'success');
                    fetchLogins(this);
                }
            });
        },
    },
}

function fetchRoles(vm) {
    new SettingsApi().userSettingsEveLoginRoles((error, data) => {
        if (!error) {
            vm.allEveRoles = data;
        }
    });
}

function fetchLogins(vm) {
    new SettingsApi().userSettingsEveLoginList((error, data) => {
        if (!error) {
            vm.logins = data;
            if (getLogin(vm)) {
                fetchTokens(vm);
            }
            fetchTokens(vm);
        }
    });
}

function getLogin(vm) {
    vm.activeLogin = null;
    if (!vm.route[1]) {
        return;
    }

    vm.contentType = vm.route[2] ? vm.route[2] : 'login';

    for (const login of vm.logins) {
        if (login.id === parseInt(vm.route[1], 10)) {
            vm.activeLogin = login;
            vm.loginUrl = `${Data.envVars.backendHost}/login/${vm.activeLogin.name}`
            vm.disabled = login.name.indexOf(Data.loginPrefixProtected) === 0;
            break;
        }
    }

    if (vm.activeLogin && vm.activeLogin.name === Data.loginNames.default && vm.contentType === 'tokens') {
        window.location.hash = `EVELoginAdmin/${vm.activeLogin.id}/login`;
        return false;
    }

    return true;
}

function fetchTokens(vm) {
    if (vm.contentType !== 'tokens') {
        return;
    }
    vm.tokens = [];
    new SettingsApi().userSettingsEveLoginTokens(vm.activeLogin.id, (error, data) => {
        if (!error) {
            vm.tokens = data;
        }
    });
}
</script>

<style lang="scss" scoped>
    .add-login {
        float: right;
        cursor: pointer;
    }

    // darkly theme does not have a distinct color, so use default
    .nav-link.disabled {
        color: var(--bs-nav-link-disabled-color);
    }
</style>
