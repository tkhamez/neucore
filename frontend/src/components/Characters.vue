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
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div v-cloak v-if="selectedPlayer" class="modal-body">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-7">
                            <h6>Characters</h6>
                            <ul class="list-group">
                                <li v-for="character in selectedPlayer.characters" class="list-group-item">
                                    <div class="row">
                                        <div class="col-6">
                                            <img :src="characterPortrait(character.id, 32)" alt="portrait">
                                            {{ character.name }}
                                            <span v-if="character.main" role="img"
                                                  class="fas fa-star text-warning" title="Main"></span>
                                        </div>
                                        <div class="col-6 text-right">
                                            <span v-if="character.validToken"
                                                  class="badge badge-success ml-1">Valid token</span>
                                            <span v-if="character.validToken === false"
                                                  class="badge badge-danger ml-1">
                                                Invalid token
                                            </span>
                                            <span v-if="character.validToken === null"
                                                  class="badge badge-info ml-1">No token</span>
                                            <a class="badge badge-secondary ml-1"
                                               :href="'https://evewho.com/character/' + character.id"
                                               target="_blank" rel="noopener noreferrer">Eve Who</a>
                                        </div>
                                    </div>
                                    <div class="small row">
                                        <div class="col-2 text-muted">
                                            Corporation:<br>
                                            Alliance:
                                        </div>
                                        <div class="col-6">
                                            <span v-if="character.corporation">
                                                [{{ character.corporation.ticker }}]
                                                {{ character.corporation.name }}
                                                <span v-if="character.corporation.id < 2000000"
                                                      class="badge badge-info">NPC</span>
                                            </span>
                                            <br>
                                            <span v-if="character.corporation && character.corporation.alliance">
                                                [{{ character.corporation.alliance.ticker }}]
                                                {{ character.corporation.alliance.name }}
                                            </span>
                                        </div>
                                        <div class="col-4 text-right">
                                            <span class="text-muted">Added:</span>
                                            <span v-if="character.created">{{ formatDate(character.created) }}</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-5">
                            <h6>Groups</h6>
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
                                    Status: {{ serviceAccount.status }}<br>
                                </li>
                            </ul>

                            <br>
                            <h6>Moved Characters</h6>
                            <ul class="list-group">
                                <li v-for="movedChar in characterMovements" class="list-group-item small">
                                    {{ movedChar.characterName }}<br>

                                    <span v-if="movedChar.removedDate">
                                        {{ formatDate(movedChar.removedDate) }}<br>
                                    </span>

                                    <span class="text-muted">Reason:</span>
                                    {{ movedChar.reason }}<br>

                                    <span v-if="movedChar.reason === 'incoming'" class="text-muted">Old Player:</span>
                                    <span v-if="movedChar.reason === 'removed'" class="text-muted">New Player:</span>
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
            <div v-cloak v-if="selectedPlayer" class="modal-footer">
                <button v-cloak
                        v-if="hasAnyRole(['user-admin', 'user-manager', 'group-admin', 'app-admin', 'user-chars'])"
                        type="button" class="btn btn-info" v-on:click="updateCharacters">
                    <span role="img" class="fas fa-sync" title="Update from ESI"></span>
                    Update from ESI
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import $ from 'jquery';
import {PlayerApi} from 'neucore-js-client';
import Character from '../classes/Character.js';

export default {
    data: function() {
        return {
            selectedPlayer: null,
            characterMovements: [],
            unauthorized: null,
        }
    },

    methods: {
        characterName: function(characterId) {
            for (const character of this.selectedPlayer.characters) {
                if (characterId === character.id) {
                    return character.name;
                }
            }
            return '';
        },

        showCharacters: function(playerId) {
            $('#playerModal').modal('show');
            this.fetchCharacters(playerId);
        },

        fetchCharacters: function(playerId) {
            const vm = this;
            vm.selectedPlayer = null;
            vm.characterMovements = [];
            vm.unauthorized = null;
            new PlayerApi().characters(playerId, function(error, data, response) {
                if (error) {
                    if (response.statusCode === 403) {
                        vm.unauthorized = true;
                    }
                    return;
                }
                vm.selectedPlayer = data;
                vm.characterMovements = vm.buildCharacterMovements(data);
            });
        },

        updateCharacters: function() {
            const vm = this;
            if (! vm.selectedPlayer) {
                return;
            }
            (new Character(vm)).updatePlayer(vm.selectedPlayer, function () {
                vm.fetchCharacters(vm.selectedPlayer.id);
            });
        },
    }
}
</script>

<style scoped>
    @media (max-width: 991px) {
        .list-group {
            margin-bottom: 1rem;
        }
    }
</style>
