<template>
    <div class="container-fluid">

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Watchlist Administration</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 sticky-column">
                <div class="card border-secondary mb-3">
                    <h3 class="card-header">Watchlists</h3>
                    <div class="list-group">
                        <a v-for="watchlist in watchlists" class="list-group-item list-group-item-action"
                           :class="{ active: watchlistId === watchlist.id }"
                           :href="'#WatchlistAdmin/' + watchlist.id + '/' + contentType">{{ watchlist.name }}</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <ul class="nav nav-pills nav-fill">
                    <li class="nav-item">
                        <a class="nav-link"
                           :class="{ 'active': contentType === 'groups' }"
                           :href="'#WatchlistAdmin/' + watchlistId + '/groups'">Groups</a>
                    </li>
                </ul>

                <!--suppress HtmlUnknownTag -->
                <admin v-cloak v-if="watchlistId"
                       :contentType="'groups'" :type="'Watchlist'" :typeId="watchlistId"></admin>

            </div>
        </div>
    </div>
</template>

<script>
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
            watchlists: [],
            watchlistId: null, // current watchlist
            contentType: '',
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        getWatchlists(this);
        setWatchlistIdAndContentType(this);
    },

    watch: {
        route: function() {
            setWatchlistIdAndContentType(this);
        },
    },
}

function setWatchlistIdAndContentType(vm) {
    vm.watchlistId = vm.route[1] ? parseInt(vm.route[1], 10) : null;
    if (vm.watchlistId) {
        vm.contentType = vm.route[2] ? vm.route[2] : 'groups';
    }
}

function getWatchlists(vm) {
    vm.watchlists = [{id: 1, name: 'auto-red-flags'}];
}
</script>

<style scoped>

</style>
