<template>
<div class="container-fluid">

    <characters :swagger="swagger" ref="charactersModal"></characters>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Group Management</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">Groups</h3>
                <div v-cloak v-if="player" class="list-group">
                    <a
                        v-for="group in player.managerGroups"
                        class="list-group-item list-group-item-action"
                        :class="{ active: groupId === group.id }"
                        :href="'#GroupManagement/' + group.id + '/' + contentType">
                        {{ group.name }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">

            <ul class="nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': contentType === 'members' }"
                       :href="'#GroupManagement/' + groupId + '/members'">Members</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': contentType === 'applications' }"
                       :href="'#GroupManagement/' + groupId + '/applications'">Applications</a>
                </li>
            </ul>

            <div v-if="contentType === 'members'" class="card border-secondary mb-3">
                <div v-cloak v-if="groupId" class="card-body">
                    <p class="small">
                        Groups that are a prerequisite for being a member of this group:
                        <span v-for="requiredGroup in requiredGroups" class="text-info">
                            {{requiredGroup.name}},
                        </span>
                        <span v-if="requiredGroups.length === 0">none</span>
                        <br>
                        Any member who is not also a member of at least one of these groups will
                        automatically be removed from this group.
                    </p>

                    <character-search :swagger="swagger" v-on:result="searchResult = $event"></character-search>

                    <ul v-if="searchResult.length > 0" class="list-group search-result">
                        <li v-for="character in searchResult" v-on:click="findPlayer(character.id)"
                                class="list-group-item list-group-item-action search-result-item">
                            <img :src="'https://image.eveonline.com/Character/' + character.id + '_32.jpg'" alt="">
                            {{ character.name }}
                        </li>
                    </ul>
                    <div v-if="newMember">
                        <span class="text-muted">Player account:</span>
                        {{ newMember.name }} #{{ newMember.id }}
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

                <table v-cloak v-if="groupId" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Player ID</th>
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
                                <button class="btn btn-info btn-sm" v-on:click="showCharacters(member.id)">
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
            </div> <!-- card members -->

            <div v-if="contentType === 'applications'" class="card border-secondary mb-3"
                 v-for="status in ['pending', 'denied', 'accepted']">
                <div class="card-body">
                    <h5>{{ status }}</h5>
                    <span v-if="status === 'accepted'" class="small">
                        The player no longer has to be a member of the group.
                    </span>
                </div>
                <table v-cloak v-if="groupId" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Created (GMT)</th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="application in groupApplications" v-if=" application.status === status">
                            <td>{{ application.player.name + ' #' + application.player.id }}</td>
                            <td>{{ formatDate(application.created) }}</td>
                            <td>
                                <button class="btn btn-info btn-sm" v-on:click="showCharacters(application.player.id)">
                                    Show characters
                                </button>
                            </td>
                            <td>
                                <button v-if="application.status === 'pending' || application.status === 'denied'"
                                        class="btn btn-success btn-sm"
                                        v-on:click="accept(application.id, application.player.id)">
                                    Accept
                                </button>
                            </td>
                            <td>
                                <button v-if="application.status === 'pending'"
                                        class="btn btn-warning btn-sm"
                                        v-on:click="deny(application.id)">
                                    Deny
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div> <!-- card applications -->

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
            groupMembers: [],
            groupApplications: [],
            searchResult: [],
            newMember: null,
            requiredGroups: [],
            contentType: ''
        }
    },

    watch: {
        player: function() {
            this.getData();
        },

        route: function() {
            this.getData();
        }
    },

    methods: {
        getData: function() {
            this.groupId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.groupId === null) {
                return;
            }
            this.contentType = this.route[2] ? this.route[2] : 'members';
            if (this.contentType === 'members') {
                this.getMembers();
                this.getRequiredGroups();
            } else if (this.contentType === 'applications') {
                this.getApplications();
            }
        },

        getMembers: function() {
            const vm = this;

            // reset variables
            vm.groupMembers = [];
            vm.searchResult = [];
            this.newMember = null;

            // get members
            vm.loading(true);
            new this.swagger.GroupApi().members(this.groupId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.groupMembers = data;
            });
        },

        getApplications: function() {
            const vm = this;
            vm.groupMembers = [];
            vm.loading(true);
            new this.swagger.GroupApi().applications(this.groupId, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.groupApplications = data;
            });
        },

        getRequiredGroups: function() {
            const vm = this;
            vm.requiredGroups = [];
            vm.loading(true);
            new this.swagger.GroupApi().requiredGroups(this.groupId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                vm.requiredGroups = data;
            });
        },

        findPlayer: function(characterId) {
            const vm = this;
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
            const vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().addMember(this.groupId, this.newMember.id, function(error) {
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
            const vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().removeMember(this.groupId, playerId, function(error) {
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

        accept: function(applicationId, playerId) {
            const vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().acceptApplication(applicationId, function() {
                vm.loading(false);
                vm.getApplications();
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
            });
        },

        deny: function(applicationId) {
            const vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().denyApplication(applicationId, function() {
                vm.loading(false);
                vm.getApplications()
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
