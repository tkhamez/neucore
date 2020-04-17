<template>
<div class="container-fluid">

    <edit :type="'Group'" ref="editModal"
          v-on:created="groupCreated($event)"
          v-on:deleted="groupDeleted()"
          v-on:itemChange="groupChanged()"></edit>

    <characters ref="charactersModal"></characters>

    <add-entity ref="addEntityModal" :settings="settings" v-on:success="addAlliCorpSuccess()"></add-entity>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Group Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3">
                <h3 class="card-header">
                    Groups
                    <span class="far fa-plus-square add-group"
                       @mouseover="mouseover"
                       @mouseleave="mouseleave"
                       v-on:click="showCreateGroupModal()"></span>
                </h3>
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

            <!--suppress HtmlUnknownTag -->
            <admin v-cloak v-if="groupId && contentType !== 'members'" ref="admin"
                   :player="player" :contentType="contentType" :typeId="groupId" :settings="settings"
                   :type="'Group'"></admin>

            <div v-cloak v-if="contentType === 'members'" class="card border-secondary mb-3">
                <table class="table table-hover mb-0" aria-describedby="Members">
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
import Characters from '../components/Characters.vue';

export default {
    components: {
        AddEntity,
        Edit,
        Admin,
        Characters,
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
            contentType: '',
            members: [],
        }
    },

    mounted: function() {
        window.scrollTo(0,0);
        this.getGroups();
        this.setGroupIdAndContentType();
        if (this.contentType === 'members') {
            fetchMembers(this);
        }
    },

    watch: {
        route: function() {
            this.setGroupIdAndContentType();
            if (this.contentType === 'members') {
                fetchMembers(this);
            }
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
            window.location.hash = '#GroupAdmin/' + newGroupId;
            this.getGroups();
        },

        showDeleteGroupModal: function(group) {
            this.$refs.editModal.showDeleteModal(group);
        },

        groupDeleted: function() {
            window.location.hash = '#GroupAdmin';
            this.groupId = null;
            this.contentType = '';
            this.getGroups();
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
            this.getGroups();
        },

        getGroups: function() {
            const vm = this;
            new GroupApi().all(function(error, data) {
                if (error) { // 403 usually
                    return;
                }
                vm.groups = data;
            });
        },

        setGroupIdAndContentType: function() {
            this.groupId = this.route[1] ? parseInt(this.route[1], 10) : null;
            if (this.groupId) {
                this.contentType = this.route[2] ? this.route[2] : 'managers';
            }
        },

        showCharacters: function(playerId) {
            this.$refs.charactersModal.showCharacters(playerId);
        },
    },
}

function fetchMembers(vm) {
    new GroupApi().members(vm.groupId, function(error, data) {
        if (error) {
            return;
        }
        console.log(data);
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
</style>
