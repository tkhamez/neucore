<template>
<div class="container-fluid">

    <characters :swagger="swagger" ref="charactersModal"></characters>

    <div class="row mb-3 mt-3">
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
                    Members -
                    {{ groupName }}
                </h3>

                <div v-cloak v-if="groupId" class="card-body">

                    <character-search :swagger="swagger" v-on:result="searchResult = $event"></character-search>

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
import Characters      from '../components/Characters.vue';
import CharacterSearch from '../components/CharacterSearch.vue';

module.exports = {
    components: {
        Characters,
        CharacterSearch,
    },

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
            searchResult: [],
            newMember: null,
        }
    },

    watch: {
        player: function() {
            this.getMembers();
        },

        route: function() {
            this.getMembers();
        }
    },

    methods: {
        getMembers: function() {
            if (! this.player) {
                return;
            }

            // reset variables
            this.groupMembers = [];
            this.searchResult = [];
            this.newMember = null;

            // group id
            this.groupId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.groupId === null) {
                return;
            }

            // set group name variable
            this.groupName = null;
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

        showCharacters: function(memberId) {
            this.$refs.charactersModal.showCharacters(memberId);
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
        max-height: 230px;
        width: 95%;
        overflow: auto;
    }

    .search-result-item {
        cursor: pointer;
    }
</style>
