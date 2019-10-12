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
                    <h3 class="card-header">Corporations</h3>
                    <div class="list-group">
                        <a v-for="corporation in corporations" class="list-group-item list-group-item-action"
                           :class="{ active: corporationId === corporation.id }"
                           :href="'#TrackingAdmin/' + corporation.id + '/' + contentType">{{ corporation.name }}</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <ul class="nav nav-pills nav-fill">
                    <li class="nav-item">
                        <a class="nav-link"
                           :class="{ 'active': contentType === 'groups' }"
                           :href="'#TrackingAdmin/' + corporationId + '/groups'">Groups</a>
                    </li>
                </ul>

                <!--suppress HtmlUnknownTag -->
                <admin v-cloak v-if="corporationId" ref="admin"
                       :player="player" :contentType="contentType" :typeId="corporationId" :settings="settings"
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
        settings: Object,
        route: Array,
        player: Object,
    },

    data: function() {
        return {
            corporations: [],
            corporationId: null, // current corporation
            contentType: '',
        }
    },

    mounted: function() {
        this.getCorporations();
        this.setCorporationIdAndContentType();
    },

    watch: {
        route: function() {
            this.setCorporationIdAndContentType();
        },
    },

    methods: {
        getCorporations: function() {
            const vm = this;
            new CorporationApi().trackedCorporations(function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.corporations = data;
            });
        },

        setCorporationIdAndContentType: function() {
            this.corporationId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.corporationId) {
                this.contentType = this.route[2] ? this.route[2] : 'groups';
            }
        },
    },
}
</script>

<style scoped>

</style>
