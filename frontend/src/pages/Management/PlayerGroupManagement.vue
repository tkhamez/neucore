<template>
    <div class="container-fluid">
        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Player Groups Management</h1>
                <p class="mb-0">
                    Login URL:
                    <a :href="`${backendHost}/login/${loginNames.managed}`">
                        {{ backendHost }}/login/{{ loginNames.managed }}
                    </a>
                </p>
                <p class="small text-muted">
                    This login URL does not require ESI scopes. It disables groups for the player account
                    if the "Deactivate Groups" feature is enabled, unless their account status is "managed".
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 sticky-column">
                <div class="card border-secondary mb-3" >
                    <h4 class="card-header">Characters</h4>
                    <div class="card-body">
                        <character-search v-on:result="onSearchResult($event)" :admin="true"
                                          :currentOnly="true"></character-search>
                    </div>
                    <div class="list-group">
                        <a v-for="char in searchResult"
                           class="list-group-item list-group-item-action"
                           :class="{ active: playerId === char.player_id }"
                           :href="`#PlayerGroupManagement/${char.player_id}`">
                            {{ char.character_name }}
                        </a>
                    </div>
                </div>
                <div class="card border-secondary mb-3" >
                    <h4 class="card-header">
                        Players
                        <span class="hdl-small">status = managed</span>
                    </h4>
                    <div class="list-group">
                        <span v-for="managedPlayer in players">
                            <a class="list-group-item list-group-item-action"
                               :class="{ active: playerId === managedPlayer.id }"
                               :href="`#PlayerGroupManagement/${managedPlayer.id}`">
                                {{ managedPlayer.name }} #{{ managedPlayer.id }}
                            </a>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-secondary" >
                    <h3 class="card-header">Groups</h3>
                    <div v-if="playerData" class="card-body">
                        <h4>
                            <span v-if="h.hasRole('user-admin')">
                                <a :href="`#UserAdmin/${playerData.id}`">{{ playerData.name }} #{{ playerData.id }}</a>
                            </span>
                            <span v-if="!h.hasRole('user-admin')">{{ playerData.name }} #{{ playerData.id }}</span>
                        </h4>
                        <p>
                            Status: {{ playerData.status }}
                            <a class="btn btn-info nc-btn-xs ms-1" href=""
                               v-on:click.prevent="h.showCharacters(playerData.id)">
                                Show characters
                            </a>
                        </p>
                        <p v-if="playerData.status === 'standard'" class="text-warning">
                            The status of this player is not "managed", manual changes will
                            be overwritten by the automatic group assignment.
                        </p>

                        <hr>

                        <h5>Account Status</h5>
                        <p class="text-warning">
                            All groups will be removed from the player account when the status is changed!
                        </p>
                        <div class="input-group mb-1">
                            <label class="input-group-text" for="userAdminSetStatus">status</label>
                            <select class="form-select" id="userAdminSetStatus"
                                    v-model="playerData.status"
                                    v-on:change="setAccountStatus()">
                                <option value="standard">standard</option>
                                <option value="managed">manually managed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <admin v-cloak v-if="playerId" ref="admin"
                       :contentType="'groups'" :typeId="playerId" :type="'Player'"
                       v-on:activePlayer="playerData = $event"></admin>

            </div>
        </div>
    </div>
</template>

<script>
import {toRef} from "vue";
import { PlayerApi }   from 'neucore-js-client';
import Data            from "../../classes/Data";
import Helper          from "../../classes/Helper";
import Admin           from '../../components/EntityRelationEdit.vue';
import CharacterSearch from '../../components/CharacterSearch.vue';

export default {
    components: {
        Admin,
        CharacterSearch,
    },

    inject: ['store'],

    props: {
        route: Array,
    },

    data: function() {
        return {
            h: new Helper(this),
            player: toRef(this.store.state, 'player'),
            loginNames: Data.loginNames,
            players: [],
            playerId: null, // current player
            playerData: null, // current player
            backendHost: null,
            searchResult: [],
        }
    },

    mounted: function() {
        window.scrollTo(0,0);

        this.getPLayers();
        this.setPlayerId();

        // login URL for managed accounts
        this.backendHost = Data.envVars.backendHost;
    },

    watch: {
        route: function() {
            this.setPlayerId();
        },
    },

    methods: {
        getPLayers: function() {
            const vm = this;
            new PlayerApi().withStatus('managed', function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.players = data;
            });
        },

        setPlayerId: function() {
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },

        onSearchResult: function(result) {
            this.searchResult = result;
        },

        setAccountStatus: function() {
            const vm = this;
            const playerId = vm.playerId;
            new PlayerApi().setStatus(playerId, vm.playerData.status, function(error) {
                if (error) {
                    return;
                }
                vm.getPLayers();
                vm.$refs.admin.getSelectContent();
                vm.$refs.admin.getTableContent();
                if (playerId === vm.player.id) {
                    vm.emitter.emit('playerChange');
                }
            });
        },
    },
}
</script>

<style scoped>
    .hdl-small {
        font-size: 1rem;
    }
</style>
