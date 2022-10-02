<template>
<div>
    <div v-cloak v-if="tab === 'warnings' || tab === 'kick' || tab === 'allow'" class="card">
        <div class="card-body">
            <span v-if="tab === 'warnings'">
                List of player accounts that have characters in one of the configured alliances or corporations
                and additionally other characters in another player (non-NPC) corporations (that are not on the
                allowlist) and have not been manually excluded.<br>
                <span class="text-muted small">
                    <strong>Alliances</strong>: {{ nameList(alliances) }}<br>
                    <strong>Corporations</strong>: {{ nameList(corporations) }}
                </span>
            </span>
            <span v-if="tab === 'kick'">
                Player accounts from the warning list are moved here if they have characters in one of the
                alliances or corporations from the kicklist.<br>
                <span class="text-muted small">
                    <strong>Alliances</strong>: {{ nameList(kicklistAlliances) }}<br>
                    <strong>Corporations</strong>: {{ nameList(kicklistCorporations) }}
                </span>
            </span>
            <span v-if="tab === 'allow'">
                Player accounts that were manually excluded from the warning list or kicklist.<br>
                Alliances and corporations that were put on the allowlist.
            </span>
        </div>
    </div>

    <div class="row" v-cloak v-if="tab === 'warnings' || tab === 'kick' || tab === 'allow'">
        <div :class="{'col-lg-6': tab === 'allow', 'col-12': tab !== 'allow' }">
            <h5 class="mt-4">Players</h5>
            <table class="table table-hover nc-table-sm" aria-describedby="List of player accounts">
                <thead class="table-light">
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
                            <a href="#" v-on:click.prevent="h.showCharacters(player.id)">
                                {{ player.name }}
                            </a>
                        </td>
                        <td v-if="manageIds.indexOf(id) !== -1">
                            <button v-if="tab === 'warnings' || tab === 'kick'" class="btn btn-primary btn-sm"
                                    v-on:click="addToAllowlist(player.id)">
                                Add to allowlist
                            </button>
                            <button v-if="tab === 'allow'" class="btn btn-primary btn-sm"
                                    v-on:click="removeFromAllowlist('Players', player.id)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="small text-muted">{{ listContent.Player.length }} player account(s)</p>
        </div>
        <div v-cloak v-if="tab === 'allow'" class="col-lg-6">
            <div v-for="(listName, index) in ['Alliance', 'Corporation', 'Corporation']">
                <h5 class="mt-4">
                    {{listName}}s
                    <span v-if="index === 1">(manually added)</span>
                    <span v-if="index === 2">(automatically added*)</span>
                </h5>
                <table class="table table-hover nc-table-sm" aria-describedby="List of alliances or corporations">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Ticker</th>
                            <th scope="col">Name</th>
                            <th scope="col" v-if="listName === 'Corporation'">Alliance</th>
                            <th scope="col" v-if="listName === 'Corporation'">auto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entity in getListContentFor(listName, index)">
                            <td>{{ entity.ticker }}</td>
                            <td>
                                <a v-if="listName === 'Alliance'" class="external"
                                   :href="`https://evewho.com/alliance/${entity.id}`"
                                   target="_blank" rel="noopener noreferrer">{{ entity.name }}</a>
                                <a v-if="listName === 'Corporation'" class="external"
                                   :href="`https://evewho.com/corporation/${entity.id}`"
                                   target="_blank" rel="noopener noreferrer">{{ entity.name }}</a>
                            </td>
                            <td v-if="listName === 'Corporation'">
                                <span v-if="entity.alliance">
                                    [{{ entity.alliance.ticker }}]
                                    {{ entity.alliance.name }}
                                </span>
                            </td>
                            <td v-if="listName === 'Corporation'">{{ entity.autoAllowlist }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="small text-muted">
                    <span v-if="index === 0">{{ listContent[listName].length }} alliances(s)</span>
                    <span v-if="index === 1">
                        {{ listContent[listName].filter(corporation => ! corporation.autoAllowlist).length }}
                        corporation(s)
                    </span>
                    <span v-if="index === 2">
                        {{ listContent[listName].filter(corporation => corporation.autoAllowlist).length }}
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
import Helper from "../../classes/Helper";
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

    data() {
        return {
            h: new Helper(this),
            listContent: {
                Player: [],
                Alliance: [],
                Corporation: [],
            },
            alliances: [],
            corporations: [],
            kicklistAlliances: [],
            kicklistCorporations: [],
        }
    },

    mounted() {
        loadList(this);
    },

    watch: {
        id() {
            loadList(this);
        },
        tab() {
            loadList(this);
        },
    },

    methods: {
        getListContentFor(listName, index) {
            return this.listContent[listName].filter(entity =>
                listName !== 'Corporation' ||
                (index === 1 && !entity.autoAllowlist) ||
                (index === 2 && entity.autoAllowlist)
            );
        },

        addToAllowlist(playerId) {
            if (! this.id) {
                return;
            }
            new WatchlistApi().watchlistExemptionAdd(this.id, playerId, () => {
                loadList(this);
            });
        },

         /**
         * @param {string} type Players, Alliances or Corporations
         * @param {number} id
         */
         removeFromAllowlist(type, id) {
            if (! this.id) {
                return;
            }
            const api = new WatchlistApi();
            let method;
            if (type === 'Players') {
                method = 'watchlistExemptionRemove';
            } else if (type === 'Alliances') {
                 method = 'watchlistAllowlistAllianceRemove';
             } else if (type === 'Corporations') {
                 method = 'watchlistAllowlistCorporationRemove';
             } else {
                return;
            }
            api[method].apply(api, [this.id, id, () => {
                loadList(this, true);
            }]);
        },

        /**
         * @param {array} entities
         * @returns {string}
         */
        nameList(entities) {
            return entities.map(entity => {
                return entity.name;
            }).join(', ');
        },
    },
}

/**
 * @param vm
 * @param {boolean} [onlyPlayers] for allowlist, reload players only
 */
function loadList(vm, onlyPlayers) {
    if (! vm.id) {
        return;
    }
    const api = new WatchlistApi();

    function setPlayer(error, data) {
        vm.listContent.Player = []; // not before, so that the list does not scroll up
        if (! error) {
            vm.listContent.Player = data;
        }
    }

    // load table data
    if (vm.tab === 'warnings') {
        api.watchlistPlayers(vm.id, (error, data) => {
            setPlayer(error, data);
        });
    } else if (vm.tab === 'allow') {
        api.watchlistExemptionList(vm.id, (error, data) => {
            setPlayer(error, data);
        });
        if (onlyPlayers) {
            return;
        }

        vm.listContent.Alliance = [];
        api.watchlistAllowlistAllianceList(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Alliance = data;
            }
        });

        vm.listContent.Corporation = [];
        api.watchlistAllowlistCorporationList(vm.id, (error, data) => {
            if (! error) {
                vm.listContent.Corporation = data;
            }
        });
    } else if (vm.tab === 'kick') {
        api.watchlistPlayersKicklist(vm.id, (error, data) => {
            setPlayer(error, data);
        });
    }

    // load alliance and corporation config
    if (vm.tab === 'warnings') {
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

    // load kicklist alliance and corporation config
    if (vm.tab === 'kick') {
        vm.kicklistAlliances = [];
        api.watchlistKicklistAllianceList(vm.id, (error, data) => {
            if (! error) {
                vm.kicklistAlliances = data;
            }
        });

        vm.kicklistCorporations = [];
        api.watchlistKicklistCorporationList(vm.id, (error, data) => {
            if (! error) {
                vm.kicklistCorporations = data;
            }
        });
    }
}
</script>

<style scoped>
    table thead th {
        position: sticky;
        top: 51px;
    }
</style>
