<template>
<div class="container-fluid">

    <!--suppress HtmlUnknownTag -->
    <characters ref="charactersModal"></characters>

    <div class="row mb-3 mt-3">
        <div class="col-lg-6">
            <h1>Watchlist</h1>
            <label class="watchlist-selection ml-3 mb-0">
                <select class="form-control" v-model="selectedId">
                    <option v-for="watchlist in watchlists" v-bind:value="watchlist.id">{{ watchlist.name }}</option>
                </select>
            </label>
        </div>
        <div class="col-lg-6 text-right">
            <character-search v-on:result="searchResult = $event"></character-search>
            <div class="search-result border text-left bg-body" v-if="searchResult.length > 0">
                <table class="table table-hover table-sm mb-0" aria-describedby="search result">
                    <thead>
                        <tr>
                            <th scope="col">Character</th>
                            <th scope="col">Account</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="char in searchResult">
                            <td>
                                <img :src="characterPortrait(char.character_id, 32)" alt="portrait">
                                {{ char.character_name }}
                            </td>
                            <td>
                                <a href="#" @click.prevent="showCharacters(char.player_id)">
                                    {{ char.player_name }} #{{ char.player_id }}
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <ul v-cloak v-if="watchlistId" class="nav nav-pills nav-fill">
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'red' }"
               :href="'#Watchlist/'+watchlistId+'/red'">Red Flags</a>
        </li>
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'black' }"
               :href="'#Watchlist/'+watchlistId+'/black'">Blacklist</a>
        </li>
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'white' }"
               :href="'#Watchlist/'+watchlistId+'/white'">Whitelist</a>
        </li>
        <li v-if="hasRole('watchlist-manager')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'settings' }"
               :href="'#Watchlist/'+watchlistId+'/settings'">Settings</a>
        </li>
    </ul>

    <watchlistLists v-cloak v-if="watchlistId && tab !== 'settings'"
                    :id="watchlistId" :tab="tab"></watchlistLists>

    <watchlistSettings v-cloak v-if="watchlistId && tab === 'settings'"
                       :id="watchlistId" :settings="settings"></watchlistSettings>

</div>
</template>

<script>
import { WatchlistApi }  from 'neucore-js-client';
import WatchlistLists    from './Watchlist--Lists.vue';
import WatchlistSettings from './Watchlist--Settings.vue';
import Characters        from '../components/Characters.vue';
import CharacterSearch   from '../components/CharacterSearch.vue';

export default {
    components: {
        WatchlistLists,
        WatchlistSettings,
        Characters,
        CharacterSearch,
    },

    props: {
        route: Array,
        player: Object,
        settings: Object,
    },

    data: function() {
        return {
            watchlists: [],
            watchlistId: null,
            selectedId: '',
            tab: '',
            searchResult: [],
        }
    },

    mounted: function() {
        window.scrollTo(0,0);

        const vm = this;
        getWatchlists(vm, () => {
            // auto select 1st if route does not have an ID
            if (! vm.route[1] && vm.watchlists[0]) {
                window.location.hash = `#Watchlist/${vm.watchlists[0].id}`;
            } else {
                setTab(vm);
            }
        });
    },

    watch: {
        selectedId () {
            const tab = this.route[2] ? this.route[2] : '';
            window.location.hash = `#Watchlist/${this.selectedId}/${tab}`;
        },
        route () {
            setTab(this);
        },
        player () {
            const vm = this;
            getWatchlists(vm, () => {
                setTab(vm);
            });
        },
    },

    methods: {
        showCharacters: (playerId) => {
            this.$refs.charactersModal.showCharacters(playerId);
        },
    },
}

/**
 * @param vm
 * @param [callback]
 */
function getWatchlists(vm, callback) {
    (new WatchlistApi).watchlistListAvailable((error, data) => {
        if (! error) {
            vm.watchlists = data;
            if (typeof callback === typeof Function) {
                callback();
            }
        }
    });
}

function setTab(vm) {
    const tabs = ['red', 'black', 'white', 'settings'];
    if (vm.route[1]) {
        const idFromPath = parseInt(vm.route[1], 10);
        let found = false;
        for (const list of vm.watchlists) {
            if (list.id === idFromPath) {
                found = true;
                vm.watchlistId = idFromPath;
                vm.selectedId = idFromPath;
            }
        }
        if (! found) {
            vm.watchlistId = null;
            vm.selectedId = '';
        }
    }
    if (vm.route[2] && tabs.indexOf(vm.route[2]) !== -1) {
        vm.tab = vm.route[2];
    } else if (! vm.hasRole('watchlist') && vm.hasRole('watchlist-manager')) {
        vm.tab = 'settings';
    } else {
        vm.tab = 'red';
    }
}
</script>

<style type="text/css" scoped>
    h1 {
        display: inline-block;
    }
    .watchlist-selection {
        position: relative;
        top: -0.5rem;
    }
    @media (min-width: 992px) {
        .search-result {
            position: absolute;
            background-color: white;
            z-index: 10;
            height: calc(100vh - 140px);
            width: calc(100% - 30px);
            overflow: auto;
        }
    }
</style>
