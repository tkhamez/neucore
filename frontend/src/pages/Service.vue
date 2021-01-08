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
            <p>A new account can only be registered for your main character.</p>
            <p v-if="service && service.textTop">
                <span style="white-space: pre-line;">{{ service.textTop }}</span>
            </p>
        </div>
    </div>

    <div v-if="player && service" v-for="account in accounts" class="card border-secondary mb-3">
        <div class="card-header">
            {{ characterName(account.characterId) }}
            <span v-if="account.characterId === getMainCharacterId()"> - Main</span>
        </div>
        <div class="card-body">

            <!-- register -->
            <div v-if="account.characterId === getMainCharacterId() &&
                       (!isAccount(account) || isInactive(account))">
                <div v-if="property('email')" class="form-group">
                    <label class="col-form-label col-form-label-sm" for="formEmail">E-Mail address</label>
                    <input class="form-control form-control-sm" type="text" id="formEmail"
                           v-model="formEmail">
                </div>
                <button type="submit" class="btn btn-success mb-1" v-on:click.prevent="register()"
                        :disabled="registerButtonDisabled">
                    Register
                </button>
                <p v-if="!isAccount(account)" class="small text-muted">Create or request a new account.</p>
                <p v-if="isInactive(account)" class="small text-muted">Reactivate account.</p>
                <p v-if="service.textRegister" class="mt-3">
                    <span style="white-space: pre-line;">{{ service.textRegister }}</span>
                </p>
            </div>

            <!-- new password -->
            <div v-if="!service.showPassword && newPassword[account.characterId]" class="alert alert-success">
                New password:
                <strong>{{ newPassword[account.characterId] }}</strong><br>
                <small>Make a note of the password, it will not be displayed again!</small>
            </div>

            <!-- account -->
            <table v-if="isAccount(account)" v-cloak class="table table-bordered mb-0"
                   aria-describedby="Account data">
                <thead class="thead-light">
                    <tr class="table-active">
                        <th scope="col" v-if="property('username')">Username</th>
                        <th scope="col" v-if="property('password') && service.showPassword">Password</th>
                        <th scope="col" v-if="property('email')">E-mail</th>
                        <th scope="col" v-if="property('status')">Status</th>
                        <th scope="col" v-if="service.URLs.length > 0"></th>
                        <th scope="col" v-if="service.actions.length > 0"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td v-if="property('username')">{{ account.username }}</td>
                        <td v-if="property('password') && service.showPassword">{{ account.password }}</td>
                        <td v-if="property('email')">{{ account.email }}</td>
                        <td v-if="property('status')">{{ account.status }}</td>
                        <td v-if="service.URLs.length > 0">
                            <a v-for="url in service.URLs" :href="urlReplace(url.url, account)"
                               class="btn btn-sm btn-primary mr-1 mb-1"
                               :target="url.target" rel="noopener noreferrer">
                                {{ url.title }}
                            </a>
                        </td>
                        <td v-if="service.actions.length > 0">
                            <button v-if="isActive(account) && action('update-account')"
                                    type="submit" class="btn btn-sm btn-info"
                                    v-on:click.prevent="updateAccount(account.characterId)"
                                    :disabled="updateAccountButtonDisabled">
                                Update Account
                            </button>
                            <br>
                            <small class="text-muted">
                                Update groups and corporation affiliation.
                            </small>
                            <br>
                            <br>
                            <button v-if="isActive(account) && action('reset-password')"
                                    type="submit" class="btn btn-sm btn-warning"
                                    v-on:click.prevent="resetPassword(account.characterId)"
                                    :disabled="resetPasswordButtonDisabled">
                                Reset Password
                            </button>
                            <br>
                            <small class="text-muted">
                                Generate a new password.
                            </small>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="isAccount(account) && service.textAccount" class="mt-3 mb-0">
                <span style="white-space: pre-line;">{{ service.textAccount }}</span>
            </p>
            <p v-if="isAccount(account) && account.status === 'Pending' && service.textPending" class="mt-3 mb-0">
                <span style="white-space: pre-line;">{{ service.textPending }}</span>
            </p>

        </div>
    </div><!-- card -->

</div>
</template>

<script>
import {ServiceApi} from "neucore-js-client";

export default {
    props: {
        route: Array,
        player: Object,
    },

    data () {
        return {
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

    mounted () {
        window.scrollTo(0, 0);
        this.registerSuccess = false;
        getData(this);
    },

    watch: {
        route () {
            this.newPassword = {};
            this.formEmail = null;
            this.registerSuccess = false;
            getData(this);
        },
    },

    methods: {
        property(name) {
            return this.service.properties.indexOf(name) !== -1;
        },
        action(name) {
            return this.service.actions.indexOf(name) !== -1;
        },
        urlReplace(url, account) {
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
            const vm = this;
            vm.registerButtonDisabled = true;
            vm.newPassword = {};
            const api = new ServiceApi();
            api.serviceRegister(getServiceId(vm), {email: vm.formEmail}, (error, data, response) => {
                if (response.statusCode === 200) {
                    vm.message('Successfully registered with service.', 'success', 2500);
                    vm.registerSuccess = true;
                    vm.newPassword[data.characterId] = data.password;
                    getAccountData(vm, api);
                } else if ([403, 404].indexOf(response.statusCode) !== -1) {
                    vm.message('Service not found or not authorized.', 'warning');
                    vm.registerButtonDisabled = false;
                } else if (response.statusCode === 409) {
                    if (response.statusText === 'no_main') {
                        vm.message('This account does not have a main character.', 'warning');
                    } else if (response.statusText === 'already_registered') {
                        vm.message('There is already an account for this character.', 'warning');
                    } else if (response.statusText === 'missing_email') {
                        vm.message('Please provide an e-mail address.', 'warning');
                    } else if (response.statusText === 'email_mismatch') {
                        vm.message('This e-mail address belongs to another account.', 'warning');
                    } else if (response.statusText === 'invite_wait') {
                        vm.message("You've already requested an invite recently, please wait.", 'warning');
                    } else {
                        vm.message(response.statusText, 'warning');
                    }
                    vm.registerButtonDisabled = false;
                } else { // 500
                    vm.message('Error. Please try again.', 'error');
                    vm.registerButtonDisabled = false;
                }
            });
        },
        updateAccount(characterId) {
            const vm = this;
            vm.updateAccountButtonDisabled = true;
            vm.newPassword = {}
            new ServiceApi().serviceUpdateAccount(getServiceId(vm), characterId, (error) => {
                if (error) {
                    vm.message('Error. Please try again.', 'error');
                } else {
                    vm.message('Account successfully updated.', 'success', 2500);
                }
                vm.updateAccountButtonDisabled = false;
            });
        },
        resetPassword(characterId) {
            const vm = this;
            vm.resetPasswordButtonDisabled = true;
            vm.newPassword = {}
            const api = new ServiceApi();
            api.serviceResetPassword(getServiceId(vm), characterId, (error, data, response) => {
                if (response.statusCode === 200) {
                    if (vm.service.showPassword) {
                        vm.message('Password successfully changed.', 'success', 2500);
                    }
                    vm.newPassword[characterId] = data;
                    getAccountData(vm, api);
                } else if (response.statusCode === 404) {
                    vm.message('Service or account not found.', 'warning');
                    vm.resetPasswordButtonDisabled = false;
                } else if (response.statusCode === 500) {
                    vm.message('Error. Please try again.', 'error');
                    vm.resetPasswordButtonDisabled = false;
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

            // TODO move to backend
            const configuration = JSON.parse(data.configuration);
            vm.service.properties = configuration.properties.split(',');
            vm.service.showPassword = configuration.showPassword;
            vm.service.actions = [];
            if (configuration.actions) {
                vm.service.actions = configuration.actions.split(',');
            }
            vm.service.URLs = configuration.URLs;
            vm.service.textAccount = configuration.textAccount;
            vm.service.textTop = configuration.textTop;
            vm.service.textRegister = configuration.textRegister;
            vm.service.textPending = configuration.textPending;
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
