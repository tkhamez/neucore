<template>
<div>
    <div v-cloak v-if="tab === 'red' || tab === 'black' || tab === 'white'" class="card">
        <div class="card-body">
            <span v-if="tab === 'red'">
                List of player accounts that have characters in one of the configured alliances or corporations
                and additionally other characters in another player (non-NPC) corporations (that are not on the
                whitelist) and have not been manually excluded.<br>
                <span class="text-muted small">
                    <strong>Alliances</strong>: {{ nameList(alliances) }}<br>
                    <strong>Corporations</strong>: {{ nameList(corporations) }}
                </span>
            </span>
            <span v-if="tab === 'black'">
                Player accounts from the "Red Flags" list are moved here if they have characters in one of the
                "black listed" alliances or corporations.<br>
                <span class="text-muted small">
                    <strong>Alliances</strong>: {{ nameList(blacklistAlliances) }}<br>
                    <strong>Corporations</strong>: {{ nameList(blacklistCorporations) }}
                </span>
            </span>
            <span v-if="tab === 'white'">
                Player accounts that were manually excluded from the "Red Flags" list.<br>
                Alliances and corporations that were put on the white list.
            </span>
        </div>
    </div>

    <div class="row" v-cloak v-if="tab === 'red' || tab === 'black' || tab === 'white'">
        <div :class="{'col-lg-6': tab === 'white', 'col-12': tab !== 'white' }">
            <h5 class="mt-4 bg-body">Players</h5>
            <table class="table table-hover nc-table-sm" aria-describedby="List of player accounts">
                <thead class="thead-dark">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col" v-if="manageIds.indexOf(id) !== -1">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="player in listContent.Player">
                        <td>{{ player.id }}</td>
                        <td>
                            <a href="#" v-on:click.prevent="showCharacters(player.id)">
                                {{ player.name }}
                            </a>
                        </td>
                        <td v-if="manageIds.indexOf(id) !== -1">
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
            <div v-for="(listName, index) in ['Alliance', 'Corporation', 'Corporation']">
                <h5 class="mt-4 bg-body">
                    {{listName}}s
                    <span v-if="index === 1">(manually added)</span>
                    <span v-if="index === 2">(automatically added*)</span>
                </h5>
                <table class="table table-hover nc-table-sm" aria-describedby="List of alliances or corporations">
                    <thead class="thead-dark">
                        <tr>
                            <th scope="col">Ticker</th>
                            <th scope="col">Name</th>
                            <th scope="col" v-if="listName === 'Corporation'">Alliance</th>
                            <th scope="col" v-if="listName === 'Corporation'">auto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entity in listContent[listName]"
                            v-if="
                                listName !== 'Corporation' ||
                                (index === 1 && ! entity.autoWhitelist) ||
                                (index === 2 && entity.autoWhitelist)">
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
                    <span v-if="index === 0">{{ listContent[listName].length }} alliances(s)</span>
                    <span v-if="index === 1">
                        {{ listContent[listName].filter(corporation => ! corporation.autoWhitelist).length }}
                        corporation(s)
                    </span>
                    <span v-if="index === 2">
                        {{ listContent[listName].filter(corporation => corporation.autoWhitelist).length }}
                        corporation(s)
                        <br>
                        * Corporations are automatically added if all their members belong to the same account.
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import { WatchlistApi }  from 'neucore-js-client';
import WatchlistSettings from './Watchlist--Settings.vue';

export default {
    components: {
        WatchlistSettings,
    },

    props: {
        id: Number,
        tab: String,
        manageIds: Array,
    },

    data () {
        return {
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

    mounted () {
        loadList(this);
    },

    watch: {
        id () {
            loadList(this);
        },
        tab () {
            loadList(this);
        },
    },

    methods: {
        addToWhitelist (playerId) {
            if (! this.id) {
                return;
            }
            const vm = this;
            new WatchlistApi().watchlistExemptionAdd(this.id, playerId, () => {
                loadList(vm);
            });
        },

         /**
         * @param {string} type Players, Alliances or Corporations
         * @param {number} id
         */
        removeFromWhitelist (type, id) {
            if (! this.id) {
                return;
            }
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

function loadList(vm) {
    if (! vm.id) {
        return;
    }
    const api = new WatchlistApi();

    function setPlayer(error, data) {
        vm.listContent.Player = [];
        vm.listContent.Alliance = [];
        vm.listContent.Corporation = [];
        if (! error) {
            vm.listContent.Player = data;
        }
    }

    // load table data
    if (vm.tab === 'red') {
        api.watchlistPlayers(vm.id, (error, data) => {
            setPlayer(error, data);
        });
    } else if (vm.tab === 'white') {
        api.watchlistExemptionList(vm.id, (error, data) => {
            setPlayer(error, data);
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
            setPlayer(error, data);
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

<style type="text/css" scoped>
    table thead th {
        position: sticky;
        top: 80px;
    }
</style>
