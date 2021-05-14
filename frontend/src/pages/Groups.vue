<template>
    <div class="container-fluid">

        <div v-cloak class="modal fade" id="leaveGroupModal">
            <div class="modal-dialog">
                <div v-cloak v-if="groupToLeave" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Leave Group</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>
                            Are you sure you want to leave this group?
                            You may lose access to some external services.
                        </p>
                        <p class="text-warning">{{ groupToLeave.name }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal" v-on:click="leave()">
                            LEAVE group
                        </button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Requestable Groups</h1>
                <table class="table table-hover table-sm" aria-describedby="groups">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Description</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="player && groups && applications"
                            v-for="group in groups"
                            :set="status = getStatus(group.id)">

                            <td>{{ group.name }}</td>
                            <td style="white-space: pre-wrap;">{{ group.description }}</td>
                            <td>{{ status }}</td>
                            <td>
                                <button v-if="status === 'Member'"
                                        type="button" class="btn btn-warning btn-sm"
                                        v-on:click="askLeave(group.id, group.name, group.autoAccept)">
                                    Leave group
                                </button>
                                <button v-if="status === ''"
                                        type="button" class="btn btn-primary btn-sm"
                                        v-on:click="apply(group.id)">
                                    {{ group.autoAccept ? 'Join' : 'Apply' }}
                                </button>
                                <button v-if="status === 'pending' || status === 'denied' || status === 'accepted'"
                                        type="button" class="btn btn-secondary btn-sm"
                                        v-on:click="cancel(group.id)">
                                    {{ status === 'pending' ? 'Cancel' : 'Remove' }} application
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
import $ from 'jquery';
import {GroupApi, PlayerApi} from 'neucore-js-client';

export default {
    props: {
        player: Object,
    },

    data: function() {
        return {
            groups: null,
            applications: null,
            groupToLeave: null,
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        this.getPublicGroups();
        this.getApplications();
    },

    methods: {
        getPublicGroups: function() {
            const vm = this;
            new GroupApi().userGroupPublic(function(error, data) {
                if (error) { // 403 usually
                    vm.groups = null;
                    return;
                }
                vm.groups = data;
            });
        },

        getApplications: function() {
            const vm = this;
            new PlayerApi().showApplications(function(error, data) {
                if (error) { // 403 usually
                    vm.applications = null;
                    return;
                }
                vm.applications = data;
            });
        },

        getStatus: function(groupId) {
            for (const member of this.player.groups) {
                if (member.id === groupId) {
                    return 'Member';
                }
            }
            for (const application of this.applications) {
                if (application.group.id === groupId) {
                    return application.status;
                }
            }
            return ''; // not a member, no application
        },

        apply: function(groupId) {
            const vm = this;
            new PlayerApi().addApplication(groupId, function() {
                vm.emitter.emit('playerChange');
                vm.getApplications();
            });
        },

        cancel: function(groupId) {
            const vm = this;
            new PlayerApi().removeApplication(groupId, function() {
                vm.getApplications();
            });
        },

        askLeave: function(groupId, groupName, autoAccept) {
            this.groupToLeave = {
                id: groupId,
                name: groupName,
            };
            if (autoAccept) {
                this.leave();
            } else {
                $('#leaveGroupModal').modal('show');
            }
        },

        leave: function() {
            const vm = this;
            new PlayerApi().leaveGroup(this.groupToLeave.id, function() {
                vm.emitter.emit('playerChange');
                vm.getApplications();
            });
            $('#leaveGroupModal').modal('hide');
            this.groupToLeave = null;
        }
    }
}
</script>
