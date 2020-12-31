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
                        <div v-if="mainAccount" >
                            username: {{ mainAccount.username }}<br>
                            password: {{ mainAccount.password }}<br>
                            email: {{ mainAccount.email }}<br>
                            status: {{ mainAccount.status }}<br>
                        </div>
                        <div v-else-if="accountsLoaded">
                            [register]
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
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        if (this.player !== null) {
            getData(this);
        }
    },

    watch: {
        route () {
            getData(this);
        },
        player () {
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
                    return this.mainCharacterId;
                }
            }
        },
        characterName(characterId) {
            for (const character of this.player.characters) {
                if (character.id === characterId) {
                    return character.name;
                }
            }
            return characterId; // should never reach this
        },
    }
}

function getData(vm) {
    const id = vm.route[1] ? parseInt(vm.route[1], 10) : null;
    if (!id) {
        return;
    }
    const api = new ServiceApi();

    vm.service = null;
    api.serviceService(id, (error, data) => {
        if (!error) {
            vm.service = data;
        }
    });

    vm.accounts = [];
    vm.accountsLoaded = false;
    api.serviceServiceAccounts(id, vm.player.id, (error, data) => {
        if (!error) {
            vm.accountsLoaded = true;
            vm.accounts = data;
        }
    });
}

function getMainAccount(vm) {
    for (const account of vm.accounts) {
        if (account.characterId === vm.getMainCharacterId()) {
            return account;
        }
    }
}
</script>
