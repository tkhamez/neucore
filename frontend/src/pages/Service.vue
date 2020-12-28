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
        }
    },

    mounted: function() {
        window.scrollTo(0, 0);
        getService(this);
    },

    watch: {
        route: function() {
            getService(this);
        }
    },

    methods: {

    }
}

function getService(vm) {
    const id = vm.route[1] ? parseInt(vm.route[1]) : null;
    if (!id) {
        return;
    }
    vm.service = null;
    new ServiceApi().serviceGet(id, function(error, data) {
        if (!error) {
            vm.service = data;
        }
    });
}
</script>
