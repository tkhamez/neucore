<template>
    <div class="container-fluid">

        <div v-cloak class="modal fade" id="leaveGroupModal">
            <div class="modal-dialog">
                <div v-cloak v-if="groupToLeave" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Leave Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            Are you sure you want to leave this group?
                            You may lose access to some external services.
                        </p>
                        <p class="text-warning">{{ groupToLeave.name }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" v-on:click="leave()">
                            LEAVE group
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Requestable Groups</h1>
                <table class="table table-hover table-sm" aria-describedby="groups">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Description</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="player && groups && applications" v-for="group in getGroupsWithStatus()">
                            <td>{{ group.name }}</td>
                            <td style="white-space: pre-wrap;">{{ group.description }}</td>
                            <td>{{ group.statusText }}</td>
                            <td>
                                <button v-if="group.statusText === 'Member'"
                                        type="button" class="btn btn-warning btn-sm"
                                        v-on:click="askLeave(group.id, group.name, group.autoAccept)">
                                    Leave group
                                </button>
                                <button v-if="group.statusText === ''"
                                        type="button" class="btn btn-primary btn-sm"
                                        v-on:click="apply(group.id)">
                                    {{ group.autoAccept ? 'Join' : 'Apply' }}
                                </button>
                                <button v-if="group.statusText === 'pending' || group.statusText === 'denied'"
                                        type="button" class="btn btn-secondary btn-sm"
                                        v-on:click="cancel(group.id)">
                                    {{ group.statusText === 'pending' ? 'Cancel' : 'Remove' }} application
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import {toRef} from "vue";
import {Modal} from "bootstrap";
import {GroupApi, PlayerApi} from 'neucore-js-client';

export default {
    inject: ['store'],

    data() {
        return {
            player: toRef(this.store.state, 'player'),
            groups: null,
            applications: null,
            groupToLeave: null,
            leaveGroupModal: null,
        }
    },

    mounted() {
        window.scrollTo(0,0);
        this.emitter.emit('playerChange'); // Ensure group memberships are up-to-date.
        getPublicGroups(this);
        getApplications(this);
    },

    methods: {
        getGroupsWithStatus() {
            const groups = [];
            for (const group of this.groups) {
                group.statusText = getStatus(this, group.id);
                groups.push(group);
            }
            return groups;
        },

        apply(groupId) {
            new PlayerApi().addApplication(groupId, () => {
                this.emitter.emit('playerChange');
                getApplications(this);
            });
        },

        cancel(groupId) {
            new PlayerApi().removeApplication(groupId, () => {
                getApplications(this);
            });
        },

        askLeave(groupId, groupName, autoAccept) {
            this.groupToLeave = {
                id: groupId,
                name: groupName,
            };
            if (autoAccept) {
                this.leave();
            } else {
                this.leaveGroupModal = new Modal('#leaveGroupModal');
                this.leaveGroupModal.show();
            }
        },

        leave() {
            new PlayerApi().leaveGroup(this.groupToLeave.id, () => {
                this.emitter.emit('playerChange');
                getApplications(this);
            });
            if (this.leaveGroupModal) {
                this.leaveGroupModal.hide();
            }
            this.groupToLeave = null;
        }
    }
}

function getPublicGroups(vm) {
    new GroupApi().userGroupPublic((error, data) => {
        if (error) { // 403 usually
            vm.groups = null;
            return;
        }
        vm.groups = data;
    });
}

function getApplications(vm) {
    new PlayerApi().showApplications((error, data) => {
        if (error) { // 403 usually
            vm.applications = null;
            return;
        }
        vm.applications = data;
    });
}

function getStatus (vm, groupId) {
    for (const member of vm.player.groups) {
        if (member.id === groupId) {
            return 'Member';
        }
    }
    for (const application of vm.applications) {
        if (application.group.id === groupId) {
            return application.status;
        }
    }
    return ''; // not a member, no application
}
</script>
