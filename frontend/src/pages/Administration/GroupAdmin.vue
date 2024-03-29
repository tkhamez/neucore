<template>
<div class="container-fluid">

    <edit :type="'Group'" ref="editModal"
          :functionCreate="create"
          :functionDelete="deleteIt"
          :functionRename="rename"
          v-on:groupChange="reloadGroups"></edit>

    <add-entity ref="addEntityModal" v-on:success="addAlliCorpSuccess()"></add-entity>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Group Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="nc-menu card border-secondary mb-3">
                <h4 class="card-header">
                    Groups
                    <span class="far fa-plus-square add-group" title="Add group"
                          @mouseover="U.addHighlight" @mouseleave="U.removeHighlight"
                       v-on:click="showCreateGroupModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="group in groups" class="nc-list-item-wrap" :class="{ active: groupId === group.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: groupId === group.id }"
                           :href="`#GroupAdmin/${group.id}/${contentType}`">
                            {{ group.name }}
                            <span class="text-muted small">
                                {{ group.visibility }}
                                {{ group.autoAccept ? 'auto-accept' : '' }}
                                {{ group.isDefault ? 'default' : '' }}
                            </span>
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="Edit" title="Edit"
                                  class="fa-regular fa-pen-to-square me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'warning')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'warning')"
                                  v-on:click="showEditGroupModal(group)"></span>
                            <span role="img" aria-label="Delete" title="Delete"
                                  class="far fa-trash-alt me-1"
                                  @mouseover="(ele) => U.addHighlight(ele, 'danger')"
                                  @mouseleave="(ele) => U.removeHighlight(ele, 'danger')"
                                  v-on:click="showDeleteGroupModal(group)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div v-cloak v-if="groupId" class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <div class="card-header">
                    <h4>{{groupName}}</h4>
                    <label class="form-label" for="groupDescription">Description</label>
                    <textarea maxlength="1024" v-model="groupDescription" class="form-control"
                              v-on:input="changeDescriptionDelayed($event.target.value)"
                              id="groupDescription" rows="2"></textarea>
                </div>
            </div>
            <ul class="nc-nav nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'managers' }"
                       :href="`#GroupAdmin/${groupId}/managers`">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'alliances' }"
                       :href="`#GroupAdmin/${groupId}/alliances`">
                        Alliances
                        <span class="far fa-plus-square add-alli-corp"
                              @mouseover="U.addHighlight" @mouseleave="U.removeHighlight"
                              v-on:click="showAddAlliCorpModal('Alliance')"
                              title="Add alliance"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'corporations' }"
                       :href="`#GroupAdmin/${groupId}/corporations`">
                        Corporations
                        <span class="far fa-plus-square add-alli-corp"
                              @mouseover="U.addHighlight" @mouseleave="U.removeHighlight"
                              v-on:click="showAddAlliCorpModal('Corporation')"
                              title="Add corporation"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'groups' }"
                       :href="`#GroupAdmin/${groupId}/groups`">Groups</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'members' }"
                       :href="`#GroupAdmin/${groupId}/members`">
                        Members
                        <span v-if="membersLoaded && contentType === 'members'">({{ members.length }})</span>
                    </a>
                </li>
            </ul>

            <admin v-cloak v-if="groupId && contentType !== 'members'" ref="admin"
                   :contentType="(contentType !== 'groups' ? contentType : 'requiredGroups')"
                   :typeId="groupId" :type="'Group'" :searchCurrentOnly="true"></admin>

            <admin v-cloak v-if="groupId && contentType === 'groups'" ref="admin"
                   :contentType="'forbiddenGroups'" :typeId="groupId"
                   :type="'Group'" :searchCurrentOnly="true"></admin>

            <div v-cloak v-if="contentType === 'members'" class="card border-secondary mb-3 table-responsive">
                <table class="table table-hover mb-0 nc-table-sm" aria-describedby="Members">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Corporation</th>
                            <th scope="col">Alliance</th>
                            <th scope="col">Characters</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in members">
                            <td>{{ member.id }}</td>
                            <td>{{ member.name }}</td>
                            <td>{{ member.corporationName }}</td>
                            <td>{{ member.allianceName }}</td>
                            <td>
                                <button class="btn btn-info btn-sm" v-on:click="h.showCharacters(member.id)">
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
import _ from "lodash";
import {GroupApi} from 'neucore-js-client';
import Helper from "../../classes/Helper";
import Util from "../../classes/Util";
import AddEntity  from '../../components/EntityAdd.vue';
import Edit       from '../../components/EntityEdit.vue';
import Admin      from '../../components/EntityRelationEdit.vue';

export default {
    components: {
        AddEntity,
        Edit,
        Admin,
    },

    props: {
        route: Array,
    },

    data() {
        return {
            h: new Helper(this),
            U: Util,
            groups: [],
            groupId: null, // current group
            groupName: '',
            groupDescription: '',
            contentType: '',
            members: [],
            membersLoaded: false,
        }
    },

    mounted() {
        window.scrollTo(0,0);
        getGroups(this);
        setGroupIdAndContentType(this);
    },

    watch: {
        route() {
            setGroupIdAndContentType(this);
            getGroups(this); // TODO need API endpoint to load one group only
        },
    },

    methods: {
        showCreateGroupModal() {
            this.$refs.editModal.showCreateModal();
        },

        showDeleteGroupModal(group) {
            this.$refs.editModal.showDeleteModal(group);
        },

        showEditGroupModal(group) {
            this.$refs.editModal.showEditModal(group);
        },

        showAddAlliCorpModal(addType) {
            this.$refs.addEntityModal.showModal(addType);
        },

        addAlliCorpSuccess() {
            if (this.$refs.admin) {
                this.$refs.admin.getSelectContent();
            }
        },

        create(name) {
            new GroupApi().userGroupCreate(name, (error, data, response) => {
                if (response.status === 409) {
                    this.h.message('A group with this name already exists.', 'error');
                } else if (response.status === 400) {
                    this.h.message('Invalid name.', 'error');
                } else if (error) {
                    this.h.message('Error creating group.', 'error');
                } else {
                    this.$refs.editModal.hideModal();
                    this.h.message('Group created.', 'success');
                    window.location.hash = `#GroupAdmin/${data.id}`;
                    getGroups(this);
                }
            });
        },

        deleteIt(id) {
            new GroupApi().userGroupDelete(id, error => {
                if (error) {
                    this.h.message('Error deleting group', 'error');
                } else {
                    this.$refs.editModal.hideModal();
                    this.h.message('Group deleted.', 'success');
                    window.location.hash = '#GroupAdmin';
                    this.groupId = null;
                    this.contentType = '';
                    this.groupDescription = '';
                    getGroups(this);
                    this.emitter.emit('playerChange'); // current player could have been a manager or member
                }
            });
        },

        rename(id, name) {
            new GroupApi().userGroupRename(id, name, (error, data, response) => {
                if (response.status === 409) {
                    this.message('A group with this name already exists.', 'error');
                } else if (response.status === 400) {
                    this.h.message('Invalid group name.', 'error');
                } else if (error) {
                    this.h.message('Error renaming group.', 'error');
                } else {
                    this.h.message('Group renamed.', 'success');
                    this.$refs.editModal.hideModal();
                    this.emitter.emit('playerChange');
                    getGroups(this);
                }
            });
        },

        reloadGroups() {
            getGroups(this);
        },

        changeDescriptionDelayed(value) {
            changeDescriptionDebounced(this, value);
        },
    },
}

const changeDescriptionDebounced = _.debounce((vm, value) => {
    new GroupApi().userGroupUpdateDescription(vm.groupId, value, (error, data, response) => {
        if (error && response.statusCode === 403) {
            vm.h.message('Unauthorized.', 'error');
        }
    });
}, 250);

function setGroupIdAndContentType(vm) {
    vm.groupId = vm.route[1] ? parseInt(vm.route[1], 10) : null;
    if (vm.groupId) {
        setActiveGroupData(vm);
        vm.contentType = vm.route[2] ? vm.route[2] : 'managers';
    }
    if (vm.contentType === 'members') {
        fetchMembers(vm);
    }
}

function getGroups(vm) {
    new GroupApi().userGroupAll((error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.groups = data;
        setActiveGroupData(vm);
    });
}

function setActiveGroupData(vm) {
    const activeGroup = vm.groups.filter(group => group.id === vm.groupId);
    if (activeGroup.length === 1) { // not yet there on page refresh
        vm.groupName = activeGroup[0].name;
        vm.groupDescription = activeGroup[0].description;
    }
}

function fetchMembers(vm) {
    vm.members = [];
    vm.membersLoaded = false;
    new GroupApi().userGroupMembers(vm.groupId, (error, data) => {
        if (error) {
            return;
        }
        vm.members = data;
        vm.membersLoaded = true;
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
