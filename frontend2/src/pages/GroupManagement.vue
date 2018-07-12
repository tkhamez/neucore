<template>
<div class="container-fluid">

    <div v-cloak v-if="selectedPlayer" class="modal fade" id="playerModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        [{{ selectedPlayer.id }}]
                        {{ selectedPlayer.name }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <ul class="list-group">
                    <li v-for="character in selectedPlayer.characters" class="list-group-item">
                        <a class="badge badge-secondary badge-link ml-1"
                            :href="'https://zkillboard.com/character/' + character.id"
                            target="_blank">zKillboard</a>
                        <a class="badge badge-secondary badge-link ml-1"
                            :href="'https://evewho.com/pilot/' + character.name"
                            target="_blank">Eve Who</a>
                        <img :src="'https://image.eveonline.com/Character/' + character.id + '_32.jpg'">
                        {{ character.name }}
                        <div class="small">
                            <span class="text-muted">Corporation:</span>
                            <span v-if="character.corporation">
                                [{{ character.corporation.ticker }}]
                                {{ character.corporation.name }}
                            </span>
                            <br>
                            <span class="text-muted"> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Alliance:</span>
                            <span v-if="character.corporation && character.corporation.alliance">
                                [{{ character.corporation.alliance.ticker }}]
                                {{ character.corporation.alliance.name }}
                            </span>
                        </div>
                    </li>
                </ul>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <h1>Group Management</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">Groups</h3>
                <div v-cloak v-if="player" class="list-group">
                    <a
                        v-for="group in player.managerGroups"
                        class="list-group-item list-group-item-action"
                        :class="{ active: groupId === group.id }"
                        :href="'#GroupManagement/' + group.id">
                        {{ group.name }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-secondary mb-3">
                <h3 class="card-header">
                    Members
                    <span class="text-muted small">{{ groupName }}</span>
                </h3>

                <div v-cloak v-if="groupId" class="card-body add-member">
                    <div class="input-group input-group-sm mb-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="inputGroup-sizing-sm">Add member</span>
                        </div>
                        <input type="text" class="form-control" placeholder="Character name"
                            v-model="searchTerm" v-on:click="findCharacter">
                        <div class="input-group-append">
                            <button class="btn" type="button" v-on:click="searchTerm = ''">&times;</button>
                        </div>
                    </div>
                    <ul v-if="searchResult.length > 0" class="list-group search-result">
                        <li v-for="character in searchResult" v-on:click="findPlayer(character.id)"
                                class="list-group-item list-group-item-action search-result-item">
                            <img :src="'https://image.eveonline.com/Character/' + character.id + '_32.jpg'">
                            {{ character.name }}
                        </li>
                    </ul>
                    <div v-if="newMember">
                        <span class="text-muted">Player account:</span>
                        [{{ newMember.id }}] {{ newMember.name }}
                        <button class="btn btn-info btn-sm"
                                v-on:click="showCharacters(newMember.id)">
                            Show characters
                        </button>
                        <button class="btn btn-success btn-sm"
                                v-on:click="addPlayer(newMember.id)">
                            Add to group
                        </button>
                    </div>
                </div>

                <table v-cloak v-if="groupId" class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in groupMembers">
                            <td>{{ member.id }}</td>
                            <td>{{ member.name }}</td>
                            <td>
                                <button class="btn btn-info btn-sm"
                                    v-on:click="showCharacters(member.id)">
                                    Show characters
                                </button>
                            </td>
                            <td>
                                <button class="btn btn-danger btn-sm" v-on:click="removePlayer(member.id)">
                                    Remove from group
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div> <!-- card -->
        </div> <!-- col  -->
    </div> <!-- row -->
</div>
</template>

<script>
module.exports = {
    props: {
        route: Array,
        swagger: Object,
        player: [null, Object],
    },

    data: function() {
        return {
            groupId: null,
            groupName: null,
            groupMembers: [],
            searchTerm: '',
            searchResult: [],
            newMember: null,
            selectedPlayer: null,
        }
    },

    watch: {
        player: function() {
            this.getMembers();
        },

        route: function() {
            this.getMembers();
        },

        searchTerm: function() {
            this.findCharacter();
        }
    },

    methods: {

        getMembers: function() {
            // reset variables
            this.groupMembers = [];
            this.searchTerm = '';
            this.searchResult = [];
            this.newMember = null;

            // group id
            this.groupName = null;
            this.groupId = this.route[1] ?  parseInt(this.route[1], 10) : null;
            if (this.groupId === null) {
                return;
            }

            // set group name variable
            for (var group of this.player.managerGroups) {
                if (group.id === this.groupId) {
                    this.groupName = group.name;
                }
            }

            // get members
            var vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().members(this.groupId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.groupMembers = data;
            });
        },

        findCharacter() {
            this.newMember = null;
            if (this.searchTerm === '') {
                this.searchResult = [];
            } else {
                this.doFindCharacter(this);
            }
        },

        doFindCharacter: _.debounce((vm) => {
            vm.loading(true);
            new vm.swagger.CharacterApi().findBy(vm.searchTerm, function(error, data) {
                vm.loading(false);
                if (error) {
                    vm.searchResult = [];
                    return;
                }
                vm.searchResult = data;
            });
        }, 250),

        findPlayer: function(characterId) {
            var vm = this;
            vm.loading(true);
            new this.swagger.CharacterApi().findPlayerOf(characterId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.newMember = data;
                vm.searchResult = [];
            });
        },

        showCharacters: function(playerId) {
            var vm = this;
            vm.characters = [];
            vm.loading(true);
            new this.swagger.PlayerApi().characters(playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.selectedPlayer = data;
                window.setTimeout(function() {
                    window.jQuery('#playerModal').modal('show');
                }, 10);
            });
        },

        addPlayer: function() {
            if (this.groupId === null || this.newMember === null) {
                return;
            }
            var vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().addMember(this.groupId, this.newMember.id, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                if (vm.newMember.id === vm.player.id) {
                    vm.$root.$emit('playerChange'); // changes the player object which triggers getMembers()
                } else {
                    vm.getMembers();
                }
            });
        },

        removePlayer: function(playerId) {
            if (this.groupId === null) {
                return;
            }
            var vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().removeMember(this.groupId, playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                } else {
                    vm.getMembers();
                }
            });
        },
    },
}
</script>

<style scoped>
    .search-result {
        position: absolute;
        max-height: 173px;
        width: 95%;
        overflow: auto;
    }

    .search-result-item {
        cursor: pointer;
    }

    .badge-link {
        float: right;
    }
</style>
