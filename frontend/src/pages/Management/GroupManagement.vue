<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Group Management</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="nc-menu card border-secondary mb-3" >
                <h4 class="card-header">Groups</h4>
                <div v-cloak v-if="player" class="list-group">
                    <a
                        v-for="group in player.managerGroups"
                        class="list-group-item list-group-item-action"
                        :class="{ active: groupId === group.id }"
                        :href="`#GroupManagement/${group.id}/${contentType}`">
                        {{ group.name }}
                        <span class="text-muted small">
                            {{ group.visibility }} {{ group.autoAccept ? 'auto-accept' : '' }}
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <div class="card-header">
                    <h4>{{ groupName }}</h4>
                    <span style="white-space: pre-wrap;">{{ groupDescription }}</span>
                </div>
            </div>

            <ul v-if="groupId" class="nc-nav nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': contentType === 'members' }"
                       :href="`#GroupManagement/${groupId}/members`">
                        Members
                        <span v-if="groupMembersLoaded && contentType === 'members'">({{ groupMembers.length }})</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': contentType === 'applications' }"
                       :href="`#GroupManagement/${groupId}/applications`">Applications</a>
                </li>
            </ul>

            <div v-if="contentType === 'members'" class="card border-secondary mb-3">
                <div v-cloak v-if="groupId" class="card-body">
                    <p class="small">
                        Managers:
                        <span class="text-info">{{ groupManagers.map(manager => manager.name).join(', ') }}</span>
                    </p>
                    <p class="small">
                        Required groups:
                        <span class="text-info">{{ requiredGroups.map(group => group.name).join(', ') }}</span>
                        <span v-if="requiredGroups.length === 0" class="text-info">(none)</span>
                        <br>
                        <span class="text-muted">
                            Any member who is not also a member of at least <em>one</em> of these groups is
                            automatically removed.
                        </span>
                    </p>
                    <p class="small">
                        Forbidden groups:
                        <span class="text-info">{{ forbiddenGroups.map(group => group.name).join(', ') }}</span>
                        <span v-if="forbiddenGroups.length === 0" class="text-info">(none)</span>
                        <br>
                        <span class="text-muted">
                            Any member who is a member of <em>any</em> of these groups is automatically removed.
                        </span>
                    </p>

                    <character-search v-on:result="searchResult = $event" :admin="false"></character-search>
                    <character-result :searchResult="searchResult" :admin="false" :selectedPlayers="groupMembers"
                        v-on:add="addPlayer($event)" v-on:remove="removePlayer($event)"></character-result>

                </div>
                <div class="table-responsive">
                    <table v-cloak v-if="groupId" class="table table-hover mb-0 nc-table-sm"
                           aria-describedby="members">
                        <thead>
                            <tr>
                                <th scope="col">Player ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Corporation</th>
                                <th scope="col">Alliance</th>
                                <th scope="col" v-if="h.hasRole('user-chars')">Characters</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in groupMembers">
                                <td>{{ member.id }}</td>
                                <td>
                                    <a class="external" :href="`https://evewho.com/character/${member.characterId}`"
                                       title="Eve Who" target="_blank" rel="noopener noreferrer">{{ member.name }}</a>
                                </td>
                                <td>{{ member.corporationName }}</td>
                                <td>{{ member.allianceName }}</td>
                                <td v-if="h.hasRole('user-chars')">
                                    <button class="btn btn-info btn-sm" v-on:click="h.showCharacters(member.id)">
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
                </div>
            </div> <!-- card members -->

            <div v-if="contentType === 'applications'" class="card border-secondary mb-3"
                 v-for="status in ['pending', 'denied', 'accepted']">
                <div class="card-body">
                    <h5>{{ status }} ({{ groupApplicationsByStatus(status).length }})</h5>
                </div>
                <div class="table-responsive">
                    <table v-cloak v-if="groupId" class="table table-hover mb-0"
                           :aria-describedby="`${status} applications`">
                        <thead>
                            <tr>
                                <th scope="col">Player</th>
                                <th scope="col">Created (GMT)</th>
                                <th scope="col"></th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="application in groupApplicationsByStatus(status)">
                                <td>{{ `${application.player.name} #${application.player.id}` }}</td>
                                <td>{{ U.formatDate(application.created) }}</td>
                                <td>
                                    <button v-if="application.status === 'pending'"
                                            class="btn btn-warning btn-sm"
                                            v-on:click="deny(application.id)">
                                        Deny
                                    </button>
                                </td>
                                <td>
                                    <button v-if="application.status === 'pending' || application.status === 'denied'"
                                            class="btn btn-success btn-sm"
                                            v-on:click="accept(application.id, application.player.id)">
                                        Accept
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div> <!-- card applications -->

        </div> <!-- col  -->
    </div> <!-- row -->
</div>
</template>

<script>
import {toRef} from "vue";
import { GroupApi } from 'neucore-js-client';
import Data from '../../classes/Data';
import Helper from "../../classes/Helper";
import Util from '../../classes/Util';
import CharacterSearch from '../../components/CharacterSearch.vue';
import CharacterResult from '../../components/CharacterResult.vue';

export default {
    components: {
        CharacterSearch,
        CharacterResult,
    },

    inject: ['store'],

    props: {
        route: Array,
    },

    data() {
        return {
            U: Util,
            h: new Helper(this),
            player: toRef(this.store.state, 'player'),
            groupId: null,
            groupName: '',
            groupDescription: '',
            groupMembers: [],
            groupMembersLoaded: false,
            groupApplications: [],
            searchResult: [],
            requiredGroups: [],
            forbiddenGroups: [],
            groupManagers: [],
            contentType: ''
        }
    },

    mounted() {
        window.scrollTo(0,0);
        this.emitter.emit('playerChange'); // Make sure the group data is up-to-date.
    },

    watch: {
        route() {
            this.getData();
        },

        player() {
            this.getData();
        }
    },

    methods: {
        getData() {
            // reset variables
            this.groupName = '';
            this.groupDescription = '';
            this.groupMembers = [];
            this.groupMembersLoaded = false;
            this.searchResult = [];
            this.requiredGroups = [];
            this.forbiddenGroups = [];
            this.groupManagers = [];
            this.contentType = '';

            this.groupId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.groupId === null) {
                return;
            }
            if (this.player.managerGroups.map(group => group.id).indexOf(this.groupId) === -1) {
                this.groupId = null;
                return;
            }

            const group = this.player.managerGroups.filter(group => group.id === this.groupId)[0];
            this.groupName = group.name;
            this.groupDescription = group.description;

            this.contentType = this.route[2] ? this.route[2] : 'members';
            if (this.contentType === 'members') {
                getMembers(this);
                getRequiredGroups(this);
                getForbiddenGroups(this);
                getGroupManager(this);
            } else if (this.contentType === 'applications') {
                getApplications(this);
            }
        },

        addPlayer(playerId) {
            if (this.groupId === null) {
                return;
            }
            new GroupApi().addMember(this.groupId, playerId, (error, data, response) => {
                if (response.statusCode === 400) {
                    this.h.message(Data.messages.errorRequiredForbiddenGroup, 'warning');
                }
                addRemoveResult(this, playerId, error);
            });
        },

        removePlayer(playerId) {
            if (this.groupId === null) {
                return;
            }
            new GroupApi().removeMember(this.groupId, playerId, error => {
                addRemoveResult(this, playerId, error);
            });
        },

        groupApplicationsByStatus(status) {
            return this.groupApplications.filter(app => app.status === status);
        },

        accept(applicationId, playerId) {
            new GroupApi().acceptApplication(applicationId, () => {
                getApplications(this);
                if (playerId === this.player.id) {
                    this.emitter.emit('playerChange');
                }
            });
        },

        deny(applicationId) {
            new GroupApi().denyApplication(applicationId, () => {
                getApplications(this);
            });
        },
    },
}

function getMembers(vm) {
    new GroupApi().userGroupMembers(vm.groupId, (error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.groupMembers = data;
        vm.groupMembersLoaded = true;
    });
}

function getRequiredGroups(vm) {
    new GroupApi().requiredGroups(vm.groupId, (error, data) => {
        if (error) {
            return;
        }
        vm.requiredGroups = data;
    });
}

function getForbiddenGroups(vm) {
    new GroupApi().userGroupForbiddenGroups(vm.groupId, (error, data) => {
        if (error) {
            return;
        }
        vm.forbiddenGroups = data;
    });
}

function getGroupManager(vm) {
    new GroupApi().userGroupManagers(vm.groupId, (error, data) => {
        if (error) {
            return;
        }
        vm.groupManagers = data;
    });
}

function getApplications(vm) {
    new GroupApi().applications(vm.groupId, (error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.groupApplications = data;
    });
}

function addRemoveResult(vm, playerId, error) {
    if (error) {
        return;
    }
    if (playerId === vm.player.id) {
        vm.emitter.emit('playerChange');
    } else {
        getMembers(vm);
    }
}
</script>
