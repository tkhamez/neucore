<template>
<div class="container-fluid">

    <edit :swagger="swagger" :type="'Group'" ref="editModals"
          v-on:created="groupCreated($event)"
          v-on:deleted="groupDeleted()"
          v-on:itemChange="groupChanged()"></edit>

    <div v-cloak class="modal" id="addAlliCorpModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add {{ addType }} to local database</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ addType }} EVE ID</label>
                        <input class="form-control" v-model="addTypeId" type="text" title="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" v-on:click="addAlliCorp()">Add</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Group Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card border-secondary mb-3" >
                <h3 class="card-header">
                    Groups
                    <i class="far fa-plus-square add-group" v-on:click="showCreateGroupModal()"></i>
                </h3>
                <div class="list-group">
                    <span v-for="group in groups">
                        <a class="list-group-item list-group-item-action"
                            :class="{ active: groupId === group.id }"
                            :href="'#GroupAdmin/' + group.id + '/' + contentType">
                            {{ group.name }}
                            <i v-cloak v-if="groupId === group.id"
                                class="far fa-trash-alt mr-1 delete-group"
                                v-on:click="showDeleteGroupModal(group)" title="delete"></i>
                            <i v-cloak v-if="groupId === group.id"
                               class="fas fa-pencil-alt mr-1 edit-group"
                               v-on:click="showEditGroupModal(group)" title="edit"></i>
                        </a>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active"
                       :class="{ 'bg-primary': contentType === 'managers' }"
                       :href="'#GroupAdmin/' + groupId + '/managers'">Managers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active"
                            :class="{ 'bg-primary': contentType === 'alliances' }"
                            :href="'#GroupAdmin/' + groupId + '/alliances'">
                        Alliances
                        <i class="far fa-plus-square add-alli-corp"
                            v-on:click="showAddAlliCorpModal('Alliance')"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active"
                            :class="{ 'bg-primary': contentType === 'corporations' }"
                            :href="'#GroupAdmin/' + groupId + '/corporations'">
                        Corporations
                        <i class="far fa-plus-square add-alli-corp"
                            v-on:click="showAddAlliCorpModal('Corporation')"></i>
                    </a>
                </li>
            </ul>

            <admin v-cloak v-if="groupId" ref="admin"
                :player="player" :contentType="contentType" :typeId="groupId"
                :swagger="swagger" :type="'Group'"></admin>

        </div>
    </div>
</div>
</template>

<script>
import Edit  from '../components/GroupAppEdit.vue';
import Admin from '../components/GroupAppAdmin.vue';

module.exports = {
    components: {
        Edit,
        Admin,
    },

    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object],
    },

    data: function() {
        return {
            groups: [],
            groupId: null, // current group
            contentType: '',
            addType: '', // alliance or corp
            addTypeId: null,
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.getGroups();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.getGroups();
            this.setGroupIdAndContentType();
        },

        route: function() {
            this.setGroupIdAndContentType();
        },
    },

    methods: {
        showCreateGroupModal: function() {
            this.$refs.editModals.showCreateModal();
        },

        groupCreated: function(newGroupId) {
            window.location.hash = '#GroupAdmin/' + newGroupId;
            this.getGroups();
        },

        showDeleteGroupModal: function(group) {
            this.$refs.editModals.showDeleteModal(group);
        },

        groupDeleted: function() {
            window.location.hash = '#GroupAdmin';
            this.groupId = null;
            this.contentType = '';
            this.getGroups();
            this.$root.$emit('playerChange'); // current player could have been a manager or member
        },

        showEditGroupModal: function(group) {
            this.$refs.editModals.showEditModal(group);
        },

        showAddAlliCorpModal: function(addType) {
            this.addType = addType;
            this.addTypeId = '';
            window.jQuery('#addAlliCorpModal').modal('show');
        },

        addAlliCorp: function() {
            const vm = this;
            let api;
            if (this.addType === 'Corporation') {
                api = new this.swagger.CorporationApi();
            } else if (this.addType === 'Alliance') {
                api = new this.swagger.AllianceApi();
            } else {
                return;
            }

            vm.loading(true);
            api['add'].apply(api, [this.addTypeId, function(error, data, response) {
                vm.loading(false);
                if (response.statusCode === 409) {
                    vm.message(vm.addType + ' already exists.', 'error');
                } else if (response.statusCode === 404) {
                    vm.message(vm.addType + ' not found.', 'error');
                } else if (error) {
                    vm.message('Error adding ' + vm.addType, 'error');
                } else {
                    console.log(data);
                    window.jQuery('#addAlliCorpModal').modal('hide');
                    vm.message(vm.addType + ' "['+ data.ticker +'] '+ data.name +'" added.', 'success');
                    vm.$refs.admin.getSelectContent();
                }
            }]);
        },

        groupChanged: function() {
            this.getGroups();
        },

        getGroups: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.GroupApi().all(function(error, data) {
                vm.loading(false);
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
    },
}
</script>

<style scoped>
    .add-group {
        float: right;
        cursor: pointer;
    }

    .delete-group,
    .edit-group {
        float: right;
        padding: 4px 4px 5px 4px;
        border: 1px solid white;
    }

    .add-alli-corp {
        position: relative;
        top: 1px;
        right: -15px;
        font-size: 1.1rem;
    }

    .add-group:hover,
    .delete-group:hover,
    .edit-group:hover,
    .add-alli-corp:hover {
        color: #F39C12;
        border-color: #F39C12;
    }
</style>
