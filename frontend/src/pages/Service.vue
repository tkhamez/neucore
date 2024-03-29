<template>
<div class="container-fluid">
    <div class="row mt-3">
        <div class="col-lg-12">
            <h1>Services <span v-if="service">- {{ service.name }}</span></h1>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-0">
            <div v-if="failedToLoadAccounts" class="alert alert-danger">
                Failed to load Accounts. Please try again.
            </div>
            <p v-if="service && !service.configurationFile.oneAccount">
                A new account can only be registered for your main character.
            </p>
            <p v-if="service && service.configurationDatabase.textTop">
                <span style="white-space: pre-line;">{{ service.configurationDatabase.textTop }}</span>
            </p>
        </div>
    </div>

    <div v-if="player && service" v-for="account in getAccounts()" class="card border-secondary mb-3">
        <div class="card-header">
            {{ characterName(account.characterId) }}
            <span v-if="account.characterId === getMainCharacterId()"> - Main</span>
        </div>
        <div class="card-body">

            <!-- register -->
            <div v-if="account.characterId === getMainCharacterId() && (!isAccount(account) || isInactive(account))">
                <div v-if="hasProperty('email')">
                    <label class="col-form-label col-form-label-sm" for="formEmail">E-Mail address</label>
                    <input class="form-control form-control-sm" type="text" id="formEmail"
                           v-model="formEmail">
                </div>
                <br>
                <button type="submit" class="btn btn-success mb-1" v-on:click.prevent="register()"
                        :disabled="registerButtonDisabled">
                    Register
                </button>
                <p v-if="!isAccount(account)" class="small text-muted">Create or request a new account.</p>
                <p v-if="isInactive(account)" class="small text-muted">Reactivate account or request new account.</p>
                <p v-if="service.configurationDatabase.textRegister" class="mt-3">
                    <span style="white-space: pre-line;">{{ service.configurationDatabase.textRegister }}</span>
                </p>
            </div>

            <!-- new password -->
            <div v-if="!service.configurationFile.showPassword && newPassword[account.characterId]"
                 class="alert alert-success">
                New password:
                <strong>{{ newPassword[account.characterId] }}</strong><br>
                <small>Make a note of the password, it will not be displayed again!</small>
            </div>

            <!-- account -->
            <div class="table-responsive">
                <table v-if="isAccount(account)" v-cloak class="table table-bordered mb-0"
                       aria-describedby="Account data">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" v-if="hasProperty('name')">Name</th>
                            <th scope="col" v-if="hasProperty('username')">Username</th>
                            <th scope="col" v-if="hasProperty('password') && service.configurationFile.showPassword">
                                Password
                            </th>
                            <th scope="col" v-if="hasProperty('email')">E-mail</th>
                            <th scope="col" v-if="hasProperty('status')">Status</th>
                            <th scope="col" v-if="service.configurationDatabase.URLs.length > 0"></th>
                            <th scope="col" v-if="service.configurationFile.actions.length > 0"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td v-if="hasProperty('name')">{{ account.name }}</td>
                            <td v-if="hasProperty('username')">{{ account.username }}</td>
                            <td v-if="hasProperty('password') &&
                                      service.configurationFile.showPassword">{{ account.password }}</td>
                            <td v-if="hasProperty('email')">{{ account.email }}</td>
                            <td v-if="hasProperty('status')">{{ account.status }}</td>
                            <td v-if="service.configurationDatabase.URLs.length > 0">
                                <a v-for="url in service.configurationDatabase.URLs"
                                   :href="urlReplace(url.url, account)"
                                   class="btn btn-sm btn-primary me-1 mb-1"
                                   :target="url.target" rel="noopener noreferrer">
                                    {{ url.title }}
                                </a>
                            </td>
                            <td v-if="service.configurationFile.actions.length > 0">
                                <span v-if="hasAction('update-account')">
                                    <button
                                        type="submit" class="btn btn-sm btn-info"
                                        v-on:click.prevent="updateAccount(account.characterId)"
                                        :disabled="updateAccountButtonDisabled">
                                        Update Account
                                    </button>
                                    <br>
                                    <small class="text-muted">Update permissions etc.</small>
                                </span>
                                <span v-if="hasAction('update-account') &&
                                            hasAction('reset-password') &&
                                            isActive(account)">
                                    <br><br>
                                </span>
                                <span v-if="isActive(account) && hasAction('reset-password')">
                                    <button type="submit" class="btn btn-sm btn-warning"
                                            v-on:click.prevent="resetPassword(account.characterId)"
                                            :disabled="resetPasswordButtonDisabled">
                                        Reset Password
                                    </button>
                                    <br>
                                    <small class="text-muted">Generate a new password.</small>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="isAccount(account) && service.configurationDatabase.textAccount" class="mt-3 mb-0">
                <span style="white-space: pre-line;">{{ service.configurationDatabase.textAccount }}</span>
            </p>
            <p v-if="isAccount(account) &&
                    account.status === 'Pending' &&
                    service.configurationDatabase.textPending" class="mt-3 mb-0">
                <span style="white-space: pre-line;">{{ service.configurationDatabase.textPending }}</span>
            </p>

        </div>
    </div><!-- card -->

</div>
</template>

<script>
import {toRef} from "vue";
import {ServiceApi} from "neucore-js-client";
import Helper from "../classes/Helper";
import Util from "../classes/Util";

export default {
    inject: ['store'],

    props: {
        route: Array,
    },

    data() {
        return {
            h: new Helper(this),
            player: toRef(this.store.state, 'player'),
            service: null,
            accounts: [],
            failedToLoadAccounts: false,
            registerSuccess: false,
            mainCharacterId: null,
            updateAccountButtonDisabled: false,
            resetPasswordButtonDisabled: false,
            registerButtonDisabled: false,
            newPassword: {},
            formEmail: null,
        }
    },

    mounted() {
        window.scrollTo(0, 0);
        this.registerSuccess = false;
        getData(this);

        // Message from redirect
        const message = Util.getHashParameter('message');
        Util.removeHashParameter('message');
        if (message) {
            this.h.message(message);
        }
    },

    watch: {
        route() {
            this.newPassword = {};
            this.formEmail = null;
            this.registerSuccess = false;
            getData(this);
        },
    },

    methods: {
        hasProperty(name) {
            return this.service.configurationFile.properties.indexOf(name) !== -1;
        },
        hasAction(name) {
            return this.service.configurationFile.actions.indexOf(name) !== -1;
        },
        urlReplace(url, account) {
            url = url.replace('{plugin_id}', getServiceId(this));
            url = url.replace('{username}', encodeURIComponent(account.username));
            url = url.replace('{password}', encodeURIComponent(account.password));
            url = url.replace('{email}', encodeURIComponent(account.email));
            return url;
        },
        getMainCharacterId() {
            if (this.mainCharacterId !== null) {
                return this.mainCharacterId;
            }
            for (const character of this.player.characters) {
                if (character.main) {
                    this.mainCharacterId = character.id;
                    break;
                }
            }
            return this.mainCharacterId;
        },
        characterName(characterId) {
            for (const character of this.player.characters) {
                if (character.id === characterId) {
                    return character.name;
                }
            }
            return characterId; // should never reach this
        },

        /**
         * This does not return an empty main account for registration
         * if "oneAccount" option is active and there is already another service account.
         */
        getAccounts() {
            if (!this.service.configurationFile.oneAccount || this.accounts.length === 1) {
                return this.accounts;
            }

            const accounts = [];
            for (const account of this.accounts) {
                if (
                    account.characterId !== this.getMainCharacterId() ||
                    (this.isAccount(account) && !this.isInactive(account))
                ) {
                    accounts.push(account);
                }
            }
            return accounts;
        },

        isAccount(account) {
            return account.username !== null || account.email !== null;
        },
        isActive(account) {
            // other status are: Pending, Deactivated, Unknown
            return account.status === null || account.status === 'Active';
        },
        isInactive(account) {
            // other status are: Pending, null
            return account.status === 'Deactivated' || account.status === 'Unknown';
        },

        register() {
            this.registerButtonDisabled = true;
            this.newPassword = {};
            const api = new ServiceApi();
            api.serviceRegister(getServiceId(this), {email: this.formEmail}, (error, data, response) => {
                if (response.statusCode === 200) {
                    this.h.message('Account successfully registered or initialized.', 'success', 2500);
                    this.registerSuccess = true;
                    this.newPassword[data.characterId] = data.password;
                    getAccountData(this, api);
                } else if ([403, 404].indexOf(response.statusCode) !== -1) {
                    this.h.message('Service not found or not authorized.', 'warning');
                    this.registerButtonDisabled = false;
                } else if (response.statusCode === 409) {
                    const body = JSON.parse(response.text);
                    if (body === 'no_main') {
                        this.h.message('This account does not have a main character.', 'warning');
                    } else if (body === 'already_registered') {
                        this.h.message('There is already an account for this character.', 'warning');
                    } else if (body === 'missing_email') {
                        this.h.message('Please provide an e-mail address.', 'warning');
                    } else if (body === 'email_mismatch') {
                        this.h.message('This e-mail address belongs to another account.', 'warning');
                    } else if (body === 'invite_wait') {
                        this.h.message("You've already requested an invite recently, please wait.", 'warning');
                    } else if (body === 'second_account') {
                        this.h.message('You cannot register a second account.', 'warning');
                    } else {
                        this.h.message(body, 'warning');
                    }
                    this.registerButtonDisabled = false;
                } else { // 500
                    this.h.message('Error. Please try again.', 'error');
                    this.registerButtonDisabled = false;
                }
            });
        },
        updateAccount(characterId) {
            this.updateAccountButtonDisabled = true;
            this.newPassword = {}
            const api = new ServiceApi();
            api.serviceUpdateAccount(getServiceId(this), characterId, (error, data, response) => {
                if (error) {
                    if (response.statusCode === 409) {
                        this.h.message(JSON.parse(response.text), 'warning');
                    } else if (response.statusCode === 404) {
                        this.h.message('Account not found.', 'error');
                    } else {
                        this.h.message('Error. Please try again.', 'error');
                    }
                } else {
                    this.h.message('Account successfully updated.', 'success', 2500);
                    getAccountData(this, api);
                }
                this.updateAccountButtonDisabled = false;
            });
        },
        resetPassword(characterId) {
            this.resetPasswordButtonDisabled = true;
            this.newPassword = {}
            const api = new ServiceApi();
            api.serviceResetPassword(getServiceId(this), characterId, (error, data, response) => {
                if (response.statusCode === 200) {
                    if (this.service.configurationFile.showPassword) {
                        this.h.message('Password successfully changed.', 'success', 2500);
                    }
                    this.newPassword[characterId] = data;
                    getAccountData(this, api);
                } else if (response.statusCode === 404) {
                    this.h.message('Service or account not found.', 'warning');
                    this.resetPasswordButtonDisabled = false;
                } else if (response.statusCode === 500) {
                    this.h.message('Error. Please try again.', 'error');
                    this.resetPasswordButtonDisabled = false;
                }
            });
        }
    }
}

function getServiceId(vm) {
    return vm.route[1] ? parseInt(vm.route[1], 10) : 0;
}

function getData(vm) {
    const api = new ServiceApi();

    vm.service = null;
    api.serviceGet(getServiceId(vm), (error, data) => {
        if (!error) {
            vm.service = data;
            if (!vm.service.configurationDatabase) {
                vm.service.configurationDatabase = {};
            }
            if (!vm.service.configurationFile) {
                vm.service.configurationFile = {};
            }
        }
    });

    getAccountData(vm, api);
}

/**
 * @param vm
 * @param {ServiceApi} api
 */
function getAccountData(vm, api) {
    vm.accounts = [];
    vm.failedToLoadAccounts = false;
    api.serviceAccounts(getServiceId(vm), (error, data) => {
        if (error) {
            vm.failedToLoadAccounts = true;
        } else {
            sortAccounts(vm, data);
        }
        vm.updateAccountButtonDisabled = false;
        vm.resetPasswordButtonDisabled = false;
        vm.registerButtonDisabled = false;
    });
}

function sortAccounts(vm, accounts) {
    vm.accounts = [];
    for (const account of accounts) {
        if (account.characterId === vm.getMainCharacterId()) {
            vm.accounts.push(account);
            break;
        }
    }
    if (vm.accounts.length === 0) {
        vm.accounts.push({
            characterId: vm.getMainCharacterId(),
            username: null,
            password: null,
            email: null,
            status: null,
        });
    }
    for (const account of accounts) {
        if (account.characterId !== vm.getMainCharacterId()) {
            vm.accounts.push(account);
        }
    }
}
</script>
