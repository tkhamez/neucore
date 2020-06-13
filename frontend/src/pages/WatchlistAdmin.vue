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
                    <h4 class="card-header">Watchlists</h4>
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
                           :href="'#WatchlistAdmin/' + watchlistId + '/groups'">View</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                           :class="{ 'active': contentType === 'groupsManage' }"
                           :href="'#WatchlistAdmin/' + watchlistId + '/groupsManage'">Manage</a>
                    </li>
                </ul>

                <!--suppress HtmlUnknownTag -->
                <admin v-cloak v-if="watchlistId && contentType !== ''"
                       :contentType="contentType" :type="'Watchlist'" :typeId="watchlistId"></admin>

            </div>
        </div>
    </div>
</template>

<script>
import { WatchlistApi }  from 'neucore-js-client';
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

    data () {
        return {
            watchlists: [],
            watchlistId: null, // current watchlist
            contentType: '',
        }
    },

    mounted () {
        window.scrollTo(0,0);
        getWatchlists(this);
        setWatchlistIdAndContentType(this);
    },

    watch: {
        route () {
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
    (new WatchlistApi).watchlistListAll((error, data) => {
        if (! error) {
            vm.watchlists = data;
        }
    });
}
</script>
