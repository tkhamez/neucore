<template>
    <div class="container-fluid">
        <div class="row mt-3">
            <div class="col-lg-12">
                <h1>Services <span v-if="service">- {{ service.name }}</span></h1>
            </div>
        </div>
        <div v-if="player" v-cloak class="row mb-3">
            <div class="col-lg-12">

                <!-- main character and registration -->
                <div class="card border-secondary mb-3">
                    <div class="card-header">{{ characterName(getMainCharacterId()) }} - Main</div>
                    <div class="card-body">
                        <div v-if="newPassword">new password: {{ newPassword }}</div>
                        <div v-if="mainAccount" >
                            username: {{ mainAccount.username }}<br>
                            password: {{ mainAccount.password }}<br>
                            email: {{ mainAccount.email }}<br>
                            status: {{ mainAccount.status }}<br>
                        </div>

                        <div v-if="accountsLoaded && (
                            mainAccount === null ||
                            mainAccount.status === 'Deactivated' ||
                            mainAccount.status === 'Unknown'
                        )">

                            <div class="form-group">
                                <label class="col-form-label col-form-label-sm" for="email">E-Mail address</label>
                                <input class="form-control form-control-sm" type="text" id="email" v-model="email" >
                            </div>
                            <button type="submit" class="btn btn-primary" v-on:click.prevent="register()"
                                    :disabled="registerButtonDisabled">
                                Register
                            </button>

                        </div>
                    </div>
                </div>

                <!-- additional accounts from alts -->
                <div v-for="account in accounts" v-if="account.characterId !== getMainCharacterId()"
                     class="card border-secondary mb-3">
                    <div class="card-header">{{ characterName(account.characterId) }}</div>
                    <div class="card-body">
                        username: {{ account.username }}<br>
                        password: {{ account.password }}<br>
                        email: {{ account.email }}<br>
                        status: {{ account.status }}<br>
                    </div>
                </div>

            </div>
        </div>
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
            accountsLoaded: false,
            mainAccount: null,
            registerButtonDisabled: false,
            email: null,
            newPassword: null,
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getData(this);
    },

    watch: {
        route () {
            this.newPassword = null;
            this.email = null;
            getData(this);
        },
        accounts () {
            this.mainAccount = getMainAccount(this);
        }
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
        register() {
            this.registerButtonDisabled = true;
            const vm = this;
            const api = new ServiceApi();
            api.serviceRegister(getServiceId(vm), {email: vm.email}, (error, data, response) => {
                if (response.statusCode === 200) {
                    vm.message('Successfully registered with service.', 'success');
                    getAccountData(vm, api, () => {
                        vm.registerButtonDisabled = false;
                    });
                    vm.newPassword = data.password;
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
 * @param {function} [callback]
 */
function getAccountData(vm, api, callback) {
    vm.accounts = [];
    vm.accountsLoaded = false;
    api.serviceAccounts(getServiceId(vm), (error, data) => {
        if (!error) {
            vm.accountsLoaded = true;
            vm.accounts = data;
        }
        if (typeof callback === typeof Function) {
            callback();
        }
    });
}

function getMainAccount(vm) {
    for (const account of vm.accounts) {
        if (account.characterId === vm.getMainCharacterId()) {
            return account;
        }
    }
    return null;
}
</script>
