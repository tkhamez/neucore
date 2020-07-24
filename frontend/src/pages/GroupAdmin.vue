<template>
<div class="container-fluid">

    <edit :type="'Group'" ref="editModal"
          v-on:created="groupCreated($event)"
          v-on:deleted="groupDeleted()"
          v-on:itemChange="groupChanged()"></edit>

    <add-entity ref="addEntityModal" :settings="settings" v-on:success="addAlliCorpSuccess()"></add-entity>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Group Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3">
                <h4 class="card-header">
                    Groups
                    <span class="far fa-plus-square add-group"
                       @mouseover="mouseover"
                       @mouseleave="mouseleave"
                       v-on:click="showCreateGroupModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="group in groups" class="list-item-wrap" :class="{ active: groupId === group.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: groupId === group.id }"
                           :href="'#GroupAdmin/' + group.id + '/' + contentType">
                            {{ group.name }}
                            <span class="text-muted small">{{ group.visibility }}</span>
                        </a>
                        <span class="group-actions">
                            <span role="img" aria-label="edit" title="edit"
                                  class="fas fa-pencil-alt mr-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showEditGroupModal(group)"></span>
                            <span role="img" aria-label="delete" title="delete"
                                  class="far fa-trash-alt mr-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showDeleteGroupModal(group)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{groupName}}</h4>
            </div>
            <ul class="nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'managers' }"
                       :href="'#GroupAdmin/' + groupId + '/managers'">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                            :class="{ 'active': contentType === 'alliances' }"
                            :href="'#GroupAdmin/' + groupId + '/alliances'">
                        Alliances
                        <span class="far fa-plus-square add-alli-corp"
                           @mouseover="mouseover"
                           @mouseleave="mouseleave"
                            v-on:click="showAddAlliCorpModal('Alliance')"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                            :class="{ 'active': contentType === 'corporations' }"
                            :href="'#GroupAdmin/' + groupId + '/corporations'">
                        Corporations
                        <span class="far fa-plus-square add-alli-corp"
                           @mouseover="mouseover"
                           @mouseleave="mouseleave"
                            v-on:click="showAddAlliCorpModal('Corporation')"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'groups' }"
                       :href="'#GroupAdmin/' + groupId + '/groups'">Groups</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'members' }"
                       :href="'#GroupAdmin/' + groupId + '/members'">Members</a>
                </li>
            </ul>

            <admin v-cloak v-if="groupId && contentType !== 'members'" ref="admin"
                   :player="player" :contentType="contentType" :typeId="groupId" :settings="settings"
                   :type="'Group'"></admin>

            <div v-cloak v-if="contentType === 'members'" class="card border-secondary mb-3">
                <table class="table table-hover mb-0 nc-table-sm" aria-describedby="Members">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Characters</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in members">
                            <td>{{ member.id }}</td>
                            <td>{{ member.name }}</td>
                            <td>
                                <button class="btn btn-info btn-sm" v-on:click="showCharacters(member.id)">
                                    Show characters
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
</template>

<script>
import $ from 'jquery';
import {GroupApi} from 'neucore-js-client';
import AddEntity  from '../components/EntityAdd.vue';
import Edit       from '../components/GroupAppEdit.vue';
import Admin      from '../components/EntityRelationEdit.vue';

export default {
    components: {
        AddEntity,
        Edit,
        Admin,
    },

    props: {
        settings: Object,
        route: Array,
        player: Object,
    },

    data: function() {
        return {
            groups: [],
            groupId: null, // current group
            groupName: '',
            contentType: '',
            members: [],
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        getGroups(this);
        setGroupIdAndContentType(this);
    },

    watch: {
        route: function() {
            setGroupIdAndContentType(this);
        },
    },

    methods: {
        mouseover (ele) {
            $(ele.target).addClass('text-warning');
        },

        mouseleave (ele) {
            $(ele.target).removeClass('text-warning');
        },

        showCreateGroupModal: function() {
            this.$refs.editModal.showCreateModal();
        },

        groupCreated: function(newGroupId) {
            window.location.hash = `#GroupAdmin/${newGroupId}`;
            getGroups(this);
        },

        showDeleteGroupModal: function(group) {
            this.$refs.editModal.showDeleteModal(group);
        },

        groupDeleted: function() {
            window.location.hash = '#GroupAdmin';
            this.groupId = null;
            this.contentType = '';
            getGroups(this);
            this.$root.$emit('playerChange'); // current player could have been a manager or member
        },

        showEditGroupModal: function(group) {
            this.$refs.editModal.showEditModal(group);
        },

        showAddAlliCorpModal: function(addType) {
            this.$refs.addEntityModal.showModal(addType);
        },

        addAlliCorpSuccess: function() {
            if (this.$refs.admin) {
                this.$refs.admin.getSelectContent();
            }
        },

        groupChanged: function() {
            getGroups(this);
        },

    },
}

function setGroupIdAndContentType(vm) {
    vm.groupId = vm.route[1] ? parseInt(vm.route[1], 10) : null;
    if (vm.groupId) {
        setGroupName(vm);
        vm.contentType = vm.route[2] ? vm.route[2] : 'managers';
    }
    if (vm.contentType === 'members') {
        fetchMembers(vm);
    }
}

function getGroups(vm) {
    new GroupApi().all(function(error, data) {
        if (error) { // 403 usually
            return;
        }
        vm.groups = data;
        setGroupName(vm);
    });
}

function setGroupName(vm) {
    const group = vm.groups.filter(group => group.id === vm.groupId);
    if (group.length === 1) { // not yet there on page refresh
        vm.groupName = group[0].name;
    }
}

function fetchMembers(vm) {
    vm.members = [];
    new GroupApi().members(vm.groupId, function(error, data) {
        if (error) {
            return;
        }
        vm.members = data;
    });
}
</script>

<style scoped>
    .add-group {
        float: right;
        cursor: pointer;
    }
    .add-alli-corp {
        position: relative;
        top: 1px;
        margin-left: 12px;
        font-size: 1.1rem;
    }
    .nav-link {
        padding: 0.5rem 1rem;
    }
</style>
