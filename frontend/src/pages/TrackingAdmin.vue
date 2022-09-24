<template>
    <div class="container-fluid">

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Member Tracking Administration</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 sticky-column">
                <div class="card border-secondary mb-3">
                    <h4 class="card-header">Corporations</h4>
                    <div class="list-group">
                        <a v-for="corporation in corporations" class="list-group-item list-group-item-action"
                           :class="{ active: corporationId === corporation.id }"
                           :href="`#TrackingAdmin/${corporation.id}`">
                            [{{ corporation.ticker }}] {{ corporation.name }}
                        </a>
                    </div>
                </div>
            </div>
            <div v-cloak v-if="corporationId" class="col-lg-8">
                <div class="card border-secondary mb-3" >
                    <h4 class="card-header">Groups</h4>
                </div>

                <admin v-cloak v-if="corporationId" ref="admin"
                       :contentType="'groups'" :typeId="corporationId"
                       :type="'Corporation'"></admin>

            </div>
        </div>
    </div>
</template>

<script>
import { CorporationApi } from 'neucore-js-client';
import Admin from '../components/EntityRelationEdit.vue';

export default {
    components: {
        Admin,
    },

    props: {
        route: Array,
    },

    data: function() {
        return {
            corporations: [],
            corporationId: null, // current corporation
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        this.getCorporations();
        this.setCorporationId();
    },

    watch: {
        route: function() {
            this.setCorporationId();
        },
    },

    methods: {
        getCorporations: function() {
            const vm = this;
            new CorporationApi().corporationAllTrackedCorporations(function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.corporations = data;
            });
        },

        setCorporationId: function() {
            this.corporationId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },
    },
}
</script>
