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

    <div v-cloak v-if="tab === 'red' || tab === 'black' || tab === 'white'" class="card">
        <div class="card-body">
            <span v-if="tab === 'red'">
                List of player accounts that have characters in one of the configured alliances or corporations
                and additionally other characters in another player (non-NPC) corporations (that are not on the
                whitelist) and have not been manually excluded.<br>
                <span class="text-muted small">
                    Alliances: {{ nameList(alliances) }}<br>
                    Corporations: {{ nameList(corporations) }}
                </span>
            </span>
            <span v-if="tab === 'black'">
                Player accounts from the "Red Flags" list are moved here if they have characters in one of the
                "black listed" alliances or corporations.<br>
                <span class="text-muted small">
                    Alliances: {{ nameList(blacklistAlliances) }}<br>
                    Corporations: {{ nameList(blacklistCorporations) }}
                </span>
            </span>
            <span v-if="tab === 'white'">
                Player accounts that were manually excluded from the "Red Flags" list.<br>
                Alliances and corporations that were manually put on the white list.
            </span>
        </div>
    </div>

    <div class="row" v-cloak v-if="tab === 'red' || tab === 'black' || tab === 'white'">
        <div :class="{'col-lg-6': tab === 'white', 'col-12': tab !== 'white' }">
            <h5 class="mt-4">Players</h5>
            <table class="table table-hover table-sm" aria-describedby="List of player accounts">
                <thead class="thead-dark">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th v-if="hasRole('watchlist-admin')" scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="player in listContent.Player">
                        <td>{{ player.id }}</td>
                        <td><a href="#" v-on:click.prevent="showCharacters(player.id)">{{ player.name }}</a></td>
                        <td v-if="hasRole('watchlist-admin')">
                            <button v-if="tab === 'red' || tab === 'black'" class="btn btn-primary btn-sm"
                                    v-on:click="addToWhitelist(player.id)">
                                Add to Whitelist
                            </button>
                            <button v-if="tab === 'white'" class="btn btn-primary btn-sm"
                                    v-on:click="removeFromWhitelist('Players', player.id)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="small text-muted">{{ listContent.Player.length }} player account(s)</p>
        </div>
        <div v-cloak v-if="tab === 'white'" class="col-lg-6">
            <div v-for="listName in ['Alliance', 'Corporation']">
                <h5 class="mt-4">{{listName}}s</h5>
                <table class="table table-hover table-sm" aria-describedby="List of alliances or corporations">
                    <thead class="thead-dark">
                        <tr>
                            <th scope="col">Ticker</th>
                            <th scope="col">Name</th>
                            <th scope="col" v-if="listName === 'Corporation'">Alliance</th>
                            <th scope="col" v-if="listName === 'Corporation'">auto *</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entity in listContent[listName]">
                            <td>{{ entity.ticker }}</td>
                            <td>
                                <a v-if="listName === 'Alliance'"
                                   :href="'https://evewho.com/alliance/' + entity.id"
                                   target="_blank" rel="noopener noreferrer">{{ entity.name }}</a>
                                <a v-if="listName === 'Corporation'"
                                   :href="'https://evewho.com/corporation/' + entity.id"
                                   target="_blank" rel="noopener noreferrer">{{ entity.name }}</a>
                            </td>
                            <td v-if="listName === 'Corporation'">
                                <span v-if="entity.alliance">
                                    [{{ entity.alliance.ticker }}]
                                    {{ entity.alliance.name }}
                                </span>
                            </td>
                            <td v-if="listName === 'Corporation'">{{ entity.autoWhitelist }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="small text-muted">
                    {{ listContent[listName].length }} {{ listName.toLowerCase() }}(s)
                    <span v-if="listName === 'Corporation'">
                        <br>
                        * Corporations are automatically added if all their members belong to the same account.
                    </span>
                </p>
            </div>
        </div>
    </div>

    <div v-cloak v-if="tab === 'settings'" class="card">
        <div class="card-header">
            <strong>Access</strong>: Groups whose members are allowed to view the lists.
        </div>
        <div class="card-body">
            <admin ref="admin" :contentType="'groups'" :type="'Watchlist'" :typeId="id"></admin>
        </div>

        <div class="card-header">
            <strong>Red Flags</strong>: Alliances and corporations whose members are included in the list if they
            also have characters in other (not NPC) corporations.
        </div>
        <div class="card-body">
            <admin ref="admin" :contentType="'alliances'" :type="'Watchlist'" :typeId="id"></admin>
            <admin ref="admin" :contentType="'corporations'" :type="'Watchlist'" :typeId="id"></admin>
        </div>

        <div class="card-header">
            <strong>Blacklist</strong>: Accounts from the Red Flags list are moved to the blacklist
            if they have a character in one of these alliances or corporations.
        </div>
        <div class="card-body">
            <p class="small text-muted">
                You can add missing alliances and corporations on the <a href="#GroupAdmin">Group Administration</a>
                page.
            </p>
            <admin ref="admin" :contentType="'alliances'" :type="'WatchlistBlacklist'" :typeId="id"></admin>
            <admin ref="admin" :contentType="'corporations'" :type="'WatchlistBlacklist'" :typeId="id"></admin>
        </div>

        <div class="card-header">
            <strong>Whitelist</strong>: Alliances and corporations that should be treated like NPC corporations
            (usually <strong>P</strong>ersonal <strong>A</strong>lt <strong>C</strong>orp<strong>s</strong>).
        </div>
        <div class="card-body">
            <admin ref="admin" :contentType="'alliances'" :type="'WatchlistWhitelist'" :typeId="id"></admin>
            <admin ref="admin" :contentType="'corporations'" :type="'WatchlistWhitelist'" :typeId="id"></admin>
            <p class="small text-muted">
                * Corporations are automatically added (and removed accordingly) if all their members belong to
                the same account.
            </p>
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
        player: Object,
    },

    data: function() {
        return {
            id: 1,
            tab: 'red',
            listContent: {
                Player: [],
                Alliance: [],
                Corporation: [],
            },
            alliances: [],
            corporations: [],
            blacklistAlliances: [],
            blacklistCorporations: [],
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
        player () {
            setTab(this);
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

         /**
         * @param {string} type Players, Alliances or Corporations
         * @param {number} id
         */
        removeFromWhitelist: function(type, id) {
            const vm = this;
            const api = new WatchlistApi();
            let method;
            if (type === 'Players') {
                method = 'watchlistExemptionRemove';
            } else if (type === 'Alliances') {
                 method = 'watchlistWhitelistAllianceRemove';
             } else if (type === 'Corporations') {
                 method = 'watchlistWhitelistCorporationRemove';
             } else {
                return;
            }
            api[method].apply(api, [this.id, id, () => {
                loadList(vm);
            }]);
        },

        /**
         * @param {array} entities
         * @returns {string}
         */
        nameList (entities) {
            return entities.map((entity) => {
                return entity.name;
            }).join(', ');
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

function loadList(vm) {
    const api = new WatchlistApi();

    vm.listContent.Player = [];
    vm.listContent.Alliance = [];
    vm.listContent.Corporation = [];

    // load table data
    if (vm.tab === 'red') {
        api.watchlistPlayers(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Player = data;
            }
        });
    } else if (vm.tab === 'white') {
        api.watchlistExemptionList(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Player = data;
            }
        });
        api.watchlistWhitelistAllianceList(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Alliance = data;
            }
        });
        api.watchlistWhitelistCorporationList(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Corporation = data;
            }
        });
    } else if (vm.tab === 'black') {
        api.watchlistPlayersBlacklist(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Player = data;
            }
        });
    }

    // load alliance and corporation config
    if (vm.tab === 'red') {
        vm.alliances = [];
        api.watchlistAllianceList(vm.id, (error, data) => {
            if (! error) {
                vm.alliances = data;
            }
        });

        vm.corporations = [];
        api.watchlistCorporationList(vm.id, (error, data) => {
            if (! error) {
                vm.corporations = data;
            }
        });
    }

    // load blacklist alliance and corporation config
    if (vm.tab === 'black') {
        vm.blacklistAlliances = [];
        api.watchlistBlacklistAllianceList(vm.id, (error, data) => {
            if (! error) {
                vm.blacklistAlliances = data;
            }
        });

        vm.blacklistCorporations = [];
        api.watchlistBlacklistCorporationList(vm.id, (error, data) => {
            if (! error) {
                vm.blacklistCorporations = data;
            }
        });
    }
}
</script>

<style scoped>

</style>
