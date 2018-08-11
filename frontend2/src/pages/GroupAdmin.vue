<template>
<div class="container-fluid">

    <create-delete :swagger="swagger" :type="'Group'" ref="createDeleteModals"
           v-on:created="groupCreated($event)" v-on:deleted="groupDeleted()"></create-delete>

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
                                class="far fa-trash-alt delete-group bg-danger"
                                v-on:click="showDeleteGroupModal(group)" title="delete"></i>
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
                       :href="'#GroupAdmin/' + groupId + '/alliances'">Alliances</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active"
                       :class="{ 'bg-primary': contentType === 'corporations' }"
                       :href="'#GroupAdmin/' + groupId + '/corporations'">Corporations</a>
                </li>
            </ul>

            <admin v-cloak v-if="groupId"
                :player="player" :contentType="contentType" :typeId="groupId"
                :swagger="swagger" :type="'Group'"></admin>

        </div>
    </div>
</div>
</template>

<script>
import CreateDelete  from '../components/GroupAppCreateDelete.vue';
import Admin from '../components/GroupAppAdmin.vue';

module.exports = {
    components: {
        CreateDelete,
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
            contentType: "",
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
            this.$refs.createDeleteModals.showCreateModal('Allowed characters (no spaces): A-Z a-z 0-9 - . _');
        },

        groupCreated: function(newGroupId) {
            window.location.hash = '#GroupAdmin/' + newGroupId;
            this.getGroups();
        },

        showDeleteGroupModal: function(group) {
            this.$refs.createDeleteModals.showDeleteModal(group);
        },

        groupDeleted: function() {
            window.location.hash = '#GroupAdmin';
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
    .delete-group {
        float: right;
        padding: 4px 4px 5px 4px;
        border: 1px solid white;
    }
</style>
