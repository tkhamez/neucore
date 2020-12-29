<template>
    <div class="container-fluid">
        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Services <span v-if="service">- {{service.name}}</span></h1>
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

    data: function() {
        return {
            service: null,
            accounts: [],
        }
    },

    mounted: function() {
        window.scrollTo(0, 0);
        getData(this);
    },

    watch: {
        route: function() {
            getData(this);
        }
    },

    methods: {

    }
}

function getData(vm) {
    const id = vm.route[1] ? parseInt(vm.route[1]) : null;
    if (!id) {
        return;
    }
    const api = new ServiceApi();

    vm.service = null;
    api.serviceService(id, function(error, data) {
        if (!error) {
            vm.service = data;
        }
    });

    vm.accounts = [];
    api.serviceServiceAccounts(id, vm.player.id, function(error, data) {
        if (!error) {
            vm.accounts = data;
        }
    });
}
</script>
