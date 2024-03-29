<!--
Modal window with all characters of one player.
 -->

<template>
<div class="modal fade" id="playerModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 v-cloak class="modal-title">
                    <span v-if="selectedPlayer">{{ selectedPlayer.name }} #{{ selectedPlayer.id }}</span>
                    <span v-if="unauthorized" class="text-warning">Unauthorized.</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div v-cloak v-if="selectedPlayer" class="modal-body">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-7">
                            <h6>
                                Characters
                                <span role="img" class="copy-characters fa-regular fa-copy"
                                      title="Copy character list to clipboard."
                                      v-on:click="copyCharacterList"></span>
                            </h6>
                            <ul class="list-group">
                                <li v-for="character in selectedPlayer.characters"
                                    class="list-group-item p-1 pb-2 pt-2">
                                    <div class="row">
                                        <div class="col-1">
                                            <img :src="h.characterPortrait(character.id, 32)" alt="">
                                        </div>
                                        <div class="col-6">
                                            <span v-if="character.main" role="img"
                                                  class="fas fa-star text-warning" title="Main"></span>
                                            {{ character.name }}
                                            <character-name-changes :character="character"></character-name-changes>
                                        </div>
                                        <div class="col-5 text-end">
                                            <span
                                                  class="badge ms-1"
                                                  :class="{
                                                      'text-with-tooltip': character.validTokenTime ||
                                                                            character.tokenLastChecked,
                                                      'bg-success': character.validToken,
                                                      'bg-danger': character.validToken === false,
                                                      'bg-info': character.validToken === null,
                                                  }"
                                                  :data-bs-toggle="getTokenTitle(character) ? 'tooltip' : ''"
                                                  data-bs-html="true"
                                                  data-bs-custom-class="character-token"
                                                  :title="getTokenTitle(character)">
                                                <span v-if="character.validToken">Valid token</span>
                                                <span v-if="character.validToken === false">Invalid token</span>
                                                <span v-if="character.validToken === null">No token</span>
                                            </span>
                                            <a class="btn btn-secondary nc-btn-xs ms-1"
                                               :href="`https://evewho.com/character/${character.id}`"
                                               target="_blank" rel="noopener noreferrer">Eve Who</a>
                                        </div>
                                    </div>
                                    <div class="small row">
                                        <div class="small col-8">
                                            <div class="row">
                                                <div class="col-3 text-muted">Corporation:</div>
                                                <div class="col-9">
                                                    <span v-if="character.corporation">
                                                    [{{ character.corporation.ticker }}]
                                                    {{ character.corporation.name }}
                                                    <span v-if="character.corporation.id < 2000000"
                                                          class="badge bg-info">NPC</span>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-3 text-muted">Alliance:</div>
                                                <div class="col-9">
                                                    <span v-if="character.corporation &&
                                                                character.corporation.alliance">
                                                        [{{ character.corporation.alliance.ticker }}]
                                                        {{ character.corporation.alliance.name }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="small col-4 text-end">
                                            <span class="text-muted">Added: </span>
                                            <span v-if="character.created">
                                                {{ U.formatDate(character.created) }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-5">
                            <h6>Groups</h6>
                            <p v-if="playerDeactivated.withoutDelay" class="small text-info">
                                <span v-if="playerDeactivated.withDelay">
                                    Groups for this account <strong>are disabled</strong>
                                </span>
                                <span v-else-if="playerDeactivated.withoutDelay">
                                    Groups for this account <strong>will be disabled</strong> soon
                                </span>
                                because one or more characters do not have a valid ESI token.
                            </p>

                            <ul>
                                <li v-for="group in selectedPlayer.groups" class="small">{{ group.name }}</li>
                            </ul>

                            <br>
                            <h6>Service Accounts</h6>
                            <ul class="list-group">
                                <li v-for="serviceAccount in selectedPlayer.serviceAccounts"
                                    class="small list-group-item">
                                    Service: {{ serviceAccount.serviceName }}<br>
                                    Character: {{ characterName(serviceAccount.characterId) }}<br>
                                    Username:  {{ serviceAccount.username }}<br>
                                    Name:  {{ serviceAccount.name }}<br>
                                    Status: {{ serviceAccount.status }}<br>
                                </li>
                            </ul>

                            <br>
                            <h6>Moved Characters</h6>
                            <ul class="list-group">
                                <li v-for="movedChar in characterMovements" class="list-group-item small">
                                    <a class="external" :href="`https://evewho.com/character/${movedChar.characterId}`"
                                       title="Eve Who" target="_blank" rel="noopener noreferrer">
                                        {{ movedChar.characterName }}</a><br>

                                    <span v-if="movedChar.removedDate">
                                        {{ U.formatDate(movedChar.removedDate) }}<br>
                                    </span>

                                    <span class="text-muted">Action:</span>
                                    {{ movedChar.reason }}<br>

                                    <span v-if="movedChar.reason.indexOf('incoming') !== -1" class="text-muted">
                                        Old Player:&nbsp;
                                    </span>
                                    <span v-if="movedChar.reason.indexOf('removed') !== -1" class="text-muted">
                                        New Player:&nbsp;
                                    </span>
                                    <span v-if="movedChar.playerName">
                                        <a href="#" @click.prevent="fetchCharacters(movedChar.playerId)">
                                            {{ movedChar.playerName }} #{{ movedChar.playerId }}
                                        </a><br>
                                    </span>

                                    <span v-if="movedChar.deletedBy">
                                        <span class="text-muted">Deleted by:</span>
                                        {{ movedChar.deletedBy.name }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div> <!-- row -->
                </div> <!-- container -->
            </div> <!-- modal-body -->
            <div v-cloak v-if="selectedPlayer" class="modal-footer justify-content-between">
                <span v-if="h.hasAnyRole(['user-admin', 'user-manager', 'group-admin', 'app-admin', 'user-chars',
                                          'tracking', 'watchlist'])">
                    <button v-cloak type="button" class="btn btn-info mb-1" v-on:click="updatePlayer">
                        <span role="img" class="fas fa-sync" title="Update characters"></span>
                        Update characters and groups
                    </button>
                    &nbsp;
                    <button v-cloak type="button" class="btn btn-info mb-1" v-on:click="updateServices">
                        <span role="img" class="fas fa-sync" title="Update services"></span>
                        Update service accounts
                    </button>
                </span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import {Tooltip, Modal} from 'bootstrap';
import {PlayerApi} from 'neucore-js-client';
import Player from "../classes/Player";
import Character from "../classes/Character";
import Helper from "../classes/Helper";
import Util from "../classes/Util";
import CharacterNameChanges from '../components/CharacterNameChanges.vue';

export default {
    components: {
        CharacterNameChanges,
    },

    data() {
        return {
            U: Util,
            h: new Helper(this),
            selectedPlayer: null,
            characterMovements: [],
            unauthorized: null,
            playerDeactivated: null,
        }
    },

    updated() {
        document.querySelectorAll('#playerModal [data-bs-toggle="tooltip"]').forEach(tooltip => {
            if (tooltip.dataset.tooltipInit !== '1') {
                tooltip.dataset.tooltipInit = '1';
                return new Tooltip(tooltip);
            }
        });
    },

    methods: {
        characterName(characterId) {
            for (const character of this.selectedPlayer.characters) {
                if (characterId === character.id) {
                    return character.name;
                }
            }
            return '';
        },

        showCharacters(playerId) {
            const modalElement = document.getElementById('playerModal');
            new Modal(modalElement).show();
            modalElement.addEventListener('hide.bs.modal', () => {
                this.unauthorized = null;
                this.selectedPlayer = null;
                this.characterMovements = [];
            })
            this.fetchCharacters(playerId);
        },

        fetchCharacters(playerId) {
            this.unauthorized = null;
            this.selectedPlayer = null;
            this.characterMovements = [];

            const api = new PlayerApi();

            api.userPlayerCharacters(playerId, (error, data, response) => {
                if (error) {
                    if (response.statusCode === 403) {
                        this.unauthorized = true;
                    }
                    return;
                }
                this.selectedPlayer = data;
                this.characterMovements = Character.buildCharacterMovements(data);
            });

            api.groupsDisabledById(playerId, (error, data) => {
                if (!error) {
                    this.playerDeactivated = data;
                }
            });
        },

        copyCharacterList() {
            this.h.copyCharacterList(this.selectedPlayer.characters);
        },

        updatePlayer() {
            if (!this.selectedPlayer) {
                return;
            }
            new Player(this).updatePlayer(this.selectedPlayer, () => {
                this.fetchCharacters(this.selectedPlayer.id);
            });
        },

        updateServices() {
            if (!this.selectedPlayer) {
                return;
            }
            new Player(this).updateServices(this.selectedPlayer, () => {
                this.fetchCharacters(this.selectedPlayer.id);
            });
        },

        getTokenTitle(character) {
            if (!character.validTokenTime && !character.tokenLastChecked) {
                return '';
            }
            return `Status changed: ${Util.formatDate(character.validTokenTime)}<br>
                Last checked: ${Util.formatDate(character.tokenLastChecked)}`;
        }
    }
}
</script>

<style scoped>
    @media (max-width: 991px) {
        .list-group {
            margin-bottom: 1rem;
        }
    }

    .copy-characters {
        float: right;
        cursor: pointer;
        position: relative;
        top: 3px;
        right: 6px;
        font-size: .9em;
    }
</style>
