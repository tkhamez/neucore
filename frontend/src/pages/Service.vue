<template>
    <div class="container-fluid">
        <div class="row mt-3">
            <div class="col-lg-12">
                <h1>Services <span v-if="service">- {{ service.name }}</span></h1>
            </div>
        </div>
        <div v-if="player" v-cloak class="row mb-3">
            <div class="col-lg-12">
                <div v-for="account in accounts" class="card border-secondary mb-3">
                    <div class="card-header">
                        {{ characterName(account.characterId) }}
                        <span v-if="account.characterId === getMainCharacterId()"> - Main</span>
                    </div>
                    <div class="card-body">

                        <!-- new password -->
                        <div v-if="newPassword[account.characterId]">
                            new password: {{ newPassword[account.characterId] }}
                        </div>

                        <!-- Account data -->
                        <div v-if="isAccount(account)">
                            username: {{ account.username }}<br>
                            password: {{ account.password }}<br>
                            email: {{ account.email }}<br>
                            status: {{ account.status }}<br>
                        </div>

                        <!-- update account -->
                        <button v-if="isAccount(account) && isActive(account)"
                                type="submit" class="btn btn-sm btn-primary"
                                v-on:click.prevent="updateAccount(account.characterId)"
                                :disabled="updateAccountButtonDisabled">
                            Update Account
                        </button>

                        <!-- Reset Password -->
                        <button v-if="isAccount(account) && isActive(account)"
                                type="submit" class="btn btn-sm btn-primary"
                                v-on:click.prevent="resetPassword(account.characterId)"
                                :disabled="resetPasswordButtonDisabled">
                            Reset Password
                        </button>

                        <!-- Register -->
                        <div v-if="account.characterId === getMainCharacterId() &&
                                   (!isAccount(account) || isInactive(account))">
                            <div class="form-group">
                                <label class="col-form-label col-form-label-sm" for="formEmail">E-Mail address</label>
                                <input class="form-control form-control-sm" type="text" id="formEmail"
                                       v-model="formEmail">
                            </div>
                            <button type="submit" class="btn btn-primary" v-on:click.prevent="register()"
                                    :disabled="registerButtonDisabled">
                                Register
                            </button>
                        </div>

                    </div>
                </div>
            </div> <!-- col -->
        </div> <!-- row -->
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
        getData(this);
    },

    watch: {
        route () {
            this.newPassword = {};
            this.formEmail = null;
            getData(this);
        },
    },

    methods: {
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
                    vm.message('Successfully registered with service.', 'success');
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
            new ServiceApi().serviceUpdateAccount(getServiceId(vm), characterId, (error, data, response) => {
                if (error) {
                    vm.message('Error. Please try again.', 'error');
                } else {
                    vm.message('Account successfully updated.', 'success');
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
                    vm.message('Successfully changed the password.', 'success');
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
    api.serviceAccounts(getServiceId(vm), (error, data) => {
        if (!error) {
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
