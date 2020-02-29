<template>
<div class="container-fluid">
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

                    <!--suppress HtmlUnknownTag -->
                    <character-search v-on:result="searchResult = $event"></character-search>

                    <div class="search-result border" v-if="searchResult.length > 0">
                        <table class="table table-hover table-sm mb-0" aria-describedby="search result">
                            <thead>
                                <tr>
                                    <th scope="col">Character</th>
                                    <th scope="col">Account</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="char in searchResult">
                                    <td>
                                        <img :src="characterPortrait(char.character_id, 32)" alt="portrait">
                                        {{ char.character_name }}
                                    </td>
                                    <td>{{ char.player_name }} #{{ char.player_id }}</td>
                                    <td>
                                        <button v-if="! isMember(char.player_id)" class="btn btn-success btn-sm"
                                                @click="addPlayer(char.player_id)">Add to group</button>
                                        <button v-if="isMember(char.player_id)" class="btn btn-danger btn-sm"
                                                @click="removePlayer(char.player_id)">Remove from group</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <table v-cloak v-if="groupId" class="table table-hover mb-0" aria-describedby="members">
                    <thead>
                        <tr>
                            <th scope="col">Player ID</th>
                            <th scope="col">Name</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in groupMembers">
                            <td>{{ member.id }}</td>
                            <td>{{ member.name }}</td>
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
                <table v-cloak v-if="groupId" class="table table-hover mb-0"
                       :aria-describedby="status + ' applications'">
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Created (GMT)</th>
                            <th scope="col"></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="application in groupApplications" v-if=" application.status === status">
                            <td>{{ application.player.name + ' #' + application.player.id }}</td>
                            <td>{{ formatDate(application.created) }}</td>
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
import { GroupApi } from 'neucore-js-client';
import Characters      from '../components/Characters.vue';
import CharacterSearch from '../components/CharacterSearch.vue';

export default {
    components: {
        Characters,
        CharacterSearch,
    },

    props: {
        route: Array,
        player: Object,
    },

    data: function() {
        return {
            groupId: null,
            groupMembers: [],
            groupApplications: [],
            searchResult: [],
            requiredGroups: [],
            contentType: ''
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
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

            // get members
            new GroupApi().members(this.groupId, function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.groupMembers = data;
            });
        },

        isMember: function(playerId) {
            for (let member of this.groupMembers) {
                if (member.id === playerId) {
                    return true;
                }
            }
            return false;
        },

        getApplications: function() {
            const vm = this;
            vm.groupMembers = [];
            new GroupApi().applications(this.groupId, function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.groupApplications = data;
            });
        },

        getRequiredGroups: function() {
            const vm = this;
            vm.requiredGroups = [];
            new GroupApi().requiredGroups(this.groupId, function(error, data) {
                if (error) {
                    return;
                }
                vm.requiredGroups = data;
            });
        },

        addPlayer: function(playerId) {
            if (this.groupId === null) {
                return;
            }
            const vm = this;
            new GroupApi().addMember(this.groupId, playerId, function(error) {
                if (error) {
                    return;
                }
                if (playerId === vm.player.id) {
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
            new GroupApi().removeMember(this.groupId, playerId, function(error) {
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
            new GroupApi().acceptApplication(applicationId, function() {
                vm.getApplications();
                if (playerId === vm.player.id) {
                    vm.$root.$emit('playerChange');
                }
            });
        },

        deny: function(applicationId) {
            const vm = this;
            new GroupApi().denyApplication(applicationId, function() {
                vm.getApplications()
            });
        },
    },
}
</script>
