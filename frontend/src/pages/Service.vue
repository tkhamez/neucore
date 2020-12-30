<template>
    <div class="container-fluid">
        <div class="row mt-3">
            <div class="col-lg-12">
                <h1>Services <span v-if="service">- {{ service.name }}</span></h1>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-lg-12">
                <div v-for="account in accounts" class="card border-secondary mb-3">
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
        }
    },

    methods: {
        characterName(characterId) {
            for (const character of this.player.characters) {
                if (character.id === characterId) {
                    return character.name;
                }
            }
            return characterId; // should never reach this
        }
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
    api.serviceServiceAccounts(id, vm.player.id, (error, data) => {
        if (!error) {
            vm.accounts = data;
        }
    });
}
</script>
