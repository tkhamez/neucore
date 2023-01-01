<template>
    <div class="container-fluid">
        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Player Management</h1>
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
                           :href="`#PlayerManagement/${char.player_id}`">
                            {{ char.character_name }}
                        </a>
                    </div>
                </div>
                <div class="card border-secondary mb-3" >
                    <h4 class="card-header">
                        Players
                        <span class="hdl-small">status = manually managed</span>
                    </h4>
                    <div class="list-group">
                        <span v-for="managedPlayer in players">
                            <a class="list-group-item list-group-item-action"
                               :class="{ active: playerId === managedPlayer.id }"
                               :href="`#PlayerManagement/${managedPlayer.id}`">
                                {{ managedPlayer.name }} #{{ managedPlayer.id }}
                            </a>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div v-cloak v-if="playerData" class="card border-secondary border-bottom-0">
                    <h3 class="card-header">
                        {{ playerData.name }} #{{ playerData.id }}
                    </h3>

                    <div class="card-body">
                        <p>
                            Status:
                            <span v-if="playerData.status === 'managed'">manually managed</span>
                            <span v-else>standard</span>,

                            <span v-if="h.hasRole('user-admin')">
                                <a :href="`#UserAdmin/${playerData.id}`">User Administration</a>,
                            </span>

                            <a class="" href="" v-on:click.prevent="h.showCharacters(playerData.id)">
                                Show characters
                            </a>
                        </p>

                        <hr>

                        <h4>Account Status</h4>
                        <p>
                            <strong>Standard</strong>: Automatic group assignments, requires valid ESI token for
                            <a href="#SystemSettings/Features">Groups Deactivation</a> feature.<br>
                            <strong>Manually managed</strong>: No automatic group assignments, does not require a valid ESI
                            token.<br>
                            See also Documentation
                            <a :href="settings.repository + '/blob/main/doc/Documentation.md#account-status'"
                               class="external" target="_blank" rel="noopener noreferrer">Account Status</a>
                            and
                            <a :href="settings.repository + '/blob/main/doc/Documentation.md#group-deactivation'"
                               class="external" target="_blank" rel="noopener noreferrer">Group Deactivation</a>.
                        </p>
                        <div class="input-group mb-1">
                            <label class="input-group-text" for="userAdminSetStatus">Status</label>
                            <select class="form-select" id="userAdminSetStatus"
                                    v-model="playerData.status"
                                    v-on:change="setAccountStatus()">
                                <option value="standard">standard</option>
                                <option value="managed">manually managed</option>
                            </select>
                        </div>
                        <p class="text-warning">
                            All groups will be removed from the player account when the status is changed!
                        </p>

                        <hr>

                        <h4 class="mb-0">Groups</h4>
                        <p class="mt-2 mb-0" v-if="playerData.status === 'standard'">
                            Note: The status of this player is not "manually managed", manual changes to
                            group membership will be overwritten by the automatic group assignment.
                        </p>
                    </div>
                </div>
                <admin v-cloak v-if="playerId" ref="admin"
                       :contentType="'groups'" :typeId="playerId" :type="'Player'"
                       :cardClass="'border-top-0'" :cardBodyClass="'pt-0'"
                       v-on:activePlayer="playerData = $event"></admin>
            </div> <!-- col -->
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

    data() {
        return {
            h: new Helper(this),
            settings: toRef(this.store.state, 'settings'),
            player: toRef(this.store.state, 'player'),
            loginNames: Data.loginNames,
            players: [],
            playerId: null, // current player
            playerData: null, // current player
            searchResult: [],
        }
    },

    mounted() {
        window.scrollTo(0,0);

        this.getPLayers();
        this.setPlayerId();
    },

    watch: {
        route() {
            this.setPlayerId();
        },
    },

    methods: {
        getPLayers() {
            new PlayerApi().withStatus('managed', (error, data) => {
                if (error) { // 403 usually
                    return;
                }
                this.players = data;
            });
        },

        setPlayerId() {
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.playerId === null) {
                this.playerData = null;
            }
        },

        onSearchResult(result) {
            this.searchResult = result;
        },

        setAccountStatus() {
            const playerId = this.playerId;
            new PlayerApi().setStatus(playerId, this.playerData.status, error => {
                if (error) {
                    return;
                }
                this.getPLayers();
                this.$refs.admin.getSelectContent();
                this.$refs.admin.getTableContent();
                if (playerId === this.player.id) {
                    this.emitter.emit('playerChange');
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
