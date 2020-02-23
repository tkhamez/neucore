<template>
<div class="container-fluid">

    <!--suppress HtmlUnknownTag -->
    <characters ref="charactersModal"></characters>

    <div class="row mb-3 mt-3">
        <div class="col-lg-6">
            <h1>Watchlist</h1>
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

    <ul class="nav nav-pills nav-fill">
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'red' }"
               :href="'#Watchlist/'+id+'/red'">Red Flags</a>
        </li>
        <li v-if="hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'black' }"
               :href="'#Watchlist/'+id+'/black'">Blacklist</a>
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

    <watchlistLists :id="id" :tab="tab"></watchlistLists>

    <watchlistSettings v-cloak v-if="tab === 'settings'" :id="id"></watchlistSettings>

</div>
</template>

<script>
import WatchlistLists from './Watchlist--Lists.vue';
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
    },

    data: function() {
        return {
            id: 1,
            tab: 'red',
            searchResult: [],
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        setTab(this);
    },

    watch: {
        route () {
            setTab(this);
        },
        player () {
            setTab(this);
        },
    },

    methods: {
        showCharacters: function(playerId) {
            this.$refs.charactersModal.showCharacters(playerId);
        },
    },
}

function setTab(vm) {
    const tabs = ['red', 'black', 'white', 'settings'];
    if (vm.route[1]) {
        vm.id = parseInt(vm.route[1], 10);
    }
    if (vm.route[2] && tabs.indexOf(vm.route[2]) !== -1) {
        vm.tab = vm.route[2];
    } else if (! vm.hasRole('watchlist') && vm.hasRole('watchlist-admin')) {
        vm.tab = 'settings';
    }
}
</script>

<style type="text/css" scoped>
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
