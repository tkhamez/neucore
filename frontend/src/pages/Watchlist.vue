<template>
<div class="container-fluid">

    <!--suppress HtmlUnknownTag -->
    <characters ref="charactersModal"></characters>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Watchlist</h1>
        </div>
    </div>

    <ul class="nav nav-pills nav-fill">
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'red' }"
               :href="'#Watchlist/'+id+'/red'">Red Flags</a>
        </li>
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'white' }"
               :href="'#Watchlist/'+id+'/white'">Whitelist</a>
        </li>
        <li v-if="hasRole('watchlist-admin')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'settings' }"
               :href="'#Watchlist/'+id+'/settings'">Settings</a>
        </li>
    </ul>

    <div v-cloak v-if="tab === 'red' || tab === 'white'" class="card">
        <div class="card-body">
            <span v-if="tab === 'red'">
                List of player accounts that have characters in one of the configured alliances or companies
                and additionally have other characters in another player (not NPC) company
                and have not been  manually excluded.
            </span>
            <span v-if="tab === 'white'">
                Player accounts that have been manually excluded from the "Red Flags" list.
            </span>
        </div>
    </div>
    <table v-cloak v-if="tab === 'red' || tab === 'white'"
           class="table table-hover table-sm" aria-describedby="Red flagged player accounts">
        <thead class="thead-dark">
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th v-if="hasRole('watchlist-admin')" scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="player in list">
                <td>{{ player.id }}</td>
                <td><a href="#" v-on:click.prevent="showCharacters(player.id)">{{ player.name }}</a></td>
                <td v-if="hasRole('watchlist-admin')">
                    <button v-if="tab === 'red'" class="btn btn-primary btn-sm"
                            v-on:click="addToWhitelist(player.id)">
                        Add to Whitelist
                    </button>
                    <button v-if="tab === 'white'" class="btn btn-primary btn-sm"
                            v-on:click="removeFromWhitelist(player.id)">
                        Remove from Whitelist
                    </button>
                </td>
            </tr>
        </tbody>
    </table>

    <div v-cloak v-if="tab === 'settings'" class="card">
        <div class="card-header">Access</div>
        <div class="card-body">
            <p>Select groups whose members are allowed to view the lists.</p>
            <admin ref="admin" :contentType="'groups'" :type="'Watchlist'" :typeId="id"></admin>
        </div>

        <div class="card-header">Alliances</div>
        <div class="card-body">
            <p>Select alliances whose members will be added to the list.</p>
            <admin ref="admin" :contentType="'alliances'" :type="'Watchlist'" :typeId="id"></admin>
        </div>

        <div class="card-header">Corporations</div>
        <div class="card-body">
            <p>Select corporations whose members will be added to the list.</p>
            <admin ref="admin" :contentType="'corporations'" :type="'Watchlist'" :typeId="id"></admin>
        </div>
    </div>

</div>
</template>

<script>
import { WatchlistApi } from 'neucore-js-client';
import Admin from '../components/EntityRelationEdit.vue';
import Characters from '../components/Characters.vue';

export default {
    components: {
        Admin,
        Characters,
    },

    props: {
        route: Array,
    },

    data: function() {
        return {
            id: 1,
            tab: 'red',
            list: [],
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        setTab(this);
        loadList(this);
    },

    watch: {
        route () {
            setTab(this);
            loadList(this);
        },
    },

    methods: {
        showCharacters: function(playerId) {
            this.$refs.charactersModal.showCharacters(playerId);
        },

        addToWhitelist: function(playerId) {
            const vm = this;
            new WatchlistApi().watchlistExemptionAdd(this.id, playerId, () => {
                loadList(vm);
            });
        },

        removeFromWhitelist: function(playerId) {
            const vm = this;
            new WatchlistApi().watchlistExemptionRemove(this.id, playerId, () => {
                loadList(vm);
            });
        },
    },
}

function setTab(vm) {
    const tabs = ['red', 'white', 'settings'];
    if (vm.route[1]) {
        vm.id = parseInt(vm.route[1], 10);
    }
    if (vm.route[2] && tabs.indexOf(vm.route[2]) !== -1) {
        vm.tab = vm.route[2];
    }
}

function loadList(vm, method) {
    if (vm.tab === 'red') {
        method = 'watchlistPlayers';
    } else if (vm.tab === 'white') {
        method = 'watchlistExemptionList';
    } else {
        return;
    }
    vm.list = [];
    const api = new WatchlistApi();
    api[method].apply(api, [vm.id, (error, data) => {
        if (error) {
            return;
        }
        vm.list = data;
    }]);
}
</script>

<style scoped>

</style>
