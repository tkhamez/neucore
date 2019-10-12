<template>
<div class="container-fluid">

    <!--suppress HtmlUnknownTag -->
    <edit :type="'Group'" ref="editModal"
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
                        <label>Search {{ addType }}</label>
                        <multiselect v-model="searchSelected" :options="searchResults"
                                 label="name" track-by="id"
                                 placeholder="Type to search"
                                 :searchable="true"
                                 :loading="searchIsLoading"
                                 :internal-search="false"
                                 :max-height="600"
                                 :show-no-results="false"
                                 @search-change="searchAlliCorp">
                        </multiselect>
                        <div class="form-check mt-2">
                            <label class="form-check-label">
                                <input class="form-check-input" type="checkbox" value="" v-model="searchStrict">
                                Strict search
                            </label>
                        </div>
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
                    <a v-for="group in groups" class="list-group-item list-group-item-action"
                        :class="{ active: groupId === group.id }"
                        :href="'#GroupAdmin/' + group.id + '/' + contentType"
                    >
                        {{ group.name }}
                        <span class="text-muted small">{{ group.visibility }}</span>
                        <span class="group-actions" v-cloak v-if="groupId === group.id">
                            <span class="far fa-trash-alt mr-1 delete-group"
                               @mouseover="mouseover"
                               @mouseleave="mouseleave"
                               v-on:click="showDeleteGroupModal(group)" title="delete"></span>
                            <span class="fas fa-pencil-alt mr-1 edit-group"
                               @mouseover="mouseover"
                               @mouseleave="mouseleave"
                               v-on:click="showEditGroupModal(group)" title="edit"></span>
                        </span>
                    </a>
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
            </ul>

            <!--suppress HtmlUnknownTag -->
            <admin v-cloak v-if="groupId" ref="admin"
                   :player="player" :contentType="contentType" :typeId="groupId" :settings="settings"
                   :type="'Group'"></admin>

        </div>
    </div>
</div>
</template>

<script>
import _ from 'lodash';
import $ from 'jquery';
import { AllianceApi } from 'neucore-js-client';
import { CorporationApi } from 'neucore-js-client';
import { GroupApi } from 'neucore-js-client';

import Edit  from '../components/GroupAppEdit.vue';
import Admin from '../components/EntityRelationEdit.vue';

export default {
    components: {
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
            contentType: '',
            addType: '', // alliance or corp
            searchIsLoading: false,
            searchResults: [],
            searchSelected: null,
            searchStrict: false,
        }
    },

    mounted: function() {
        this.getGroups();
        this.setGroupIdAndContentType();
    },

    watch: {
        route: function() {
            this.setGroupIdAndContentType();
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
            this.addType = addType;
            this.searchResults = [];
            this.searchSelected = null;
            $('#addAlliCorpModal').modal('show');
        },

        searchAlliCorp (query) {
            if (query.length < 3) {
                return;
            }
            this.searchAlliCorpDelayed(this, query);
        },

        searchAlliCorpDelayed: _.debounce((vm, query) => {
            let category;
            if (vm.addType === 'Corporation') {
                category = 'corporation';
            } else if (vm.addType === 'Alliance') {
                category = 'alliance';
            } else {
                return;
            }

            const url =  vm.settings.esiHost + '/latest/search/?categories=' + category +
                '&datasource=' + vm.settings.esiDataSource +
                '&search=' + encodeURIComponent(query) + '&strict=' + vm.searchStrict;

            vm.searchIsLoading = true;
            vm.searchResults = [];
            $.get(url).always(response1 => {
                if (typeof response1[category] !== typeof []) {
                    vm.searchIsLoading = false;
                    return;
                }
                $.post(
                    vm.settings.esiHost + '/latest/universe/names/?datasource=' + vm.settings.esiDataSource,
                    JSON.stringify(response1[category])
                ).always(response2 => {
                    vm.searchIsLoading = false;
                    if (typeof response2 !== typeof []) {
                        return;
                    }
                    vm.searchResults = []; // reset again because of parallel request
                    for (let result of response2) {
                        vm.searchResults.push(result);
                    }
                });
            });
        }, 250),

        addAlliCorp: function() {
            if (! this.searchSelected) {
                return;
            }

            const vm = this;
            let api;
            if (this.addType === 'Corporation') {
                api = new CorporationApi();
            } else if (this.addType === 'Alliance') {
                api = new AllianceApi();
            } else {
                return;
            }

            api['add'].apply(api, [this.searchSelected.id, function(error, data, response) {
                if (response.statusCode === 409) {
                    vm.message(vm.addType + ' already exists.', 'warning');
                } else if (response.statusCode === 404) {
                    vm.message(vm.addType + ' not found.', 'error');
                } else if (error) {
                    vm.message('Error adding ' + vm.addType, 'error');
                } else {
                    $('#addAlliCorpModal').modal('hide');
                    vm.message(vm.addType + ' "['+ data.ticker +'] '+ data.name +'" added.', 'success');
                    if (vm.$refs.admin) {
                        vm.$refs.admin.getSelectContent();
                    }
                }
            }]);
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
        margin-left: 12px;
        font-size: 1.1rem;
    }

    .group-actions {
        float: right;
    }
</style>
