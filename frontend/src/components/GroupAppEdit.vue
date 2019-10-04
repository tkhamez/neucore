<!--
Modal windows to create, delete and edit groups and apps
-->

<template>
    <div>
        <div v-cloak class="modal" id="createModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create {{ type }}</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="groupAppEditCreateName">{{ type }} name</label>
                            <input class="form-control" v-model="newName" type="text" id="groupAppEditCreateName">
                            <small v-if="type === 'Group'" class="form-text text-muted">
                                {{ groupNameHelp }}
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" v-on:click="create()">Create</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal" id="deleteModal">
            <div class="modal-dialog">
                <div v-cloak v-if="item" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete {{ type }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to <strong>permanently</strong> delete this {{ type }}?</p>
                        <p class="text-warning">{{ item.name }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" v-on:click="deleteIt()">DELETE {{ type }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal" id="editModal">
            <div class="modal-dialog">
                <div v-cloak v-if="item" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit {{ type }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="groupAppEditEditName">{{ type }} name</label>
                            <input v-model="item.name" class="form-control" type="text" id="groupAppEditEditName">
                            <small v-if="type === 'Group'" class="form-text text-muted">
                                {{ groupNameHelp }}
                            </small>
                        </div>
                        <p v-cloak v-if="type === 'Group'" class="text-warning">
                            Please note, that renaming a group may break third party apps that rely on
                            the name instead of the ID.
                        </p>
                        <button type="button" class="btn btn-warning" v-on:click="rename()">Rename {{ type }}</button>

                        <hr>

                        <div v-cloak v-if="type === 'Group'" class="form-group">
                            <label for="groupAppEditVisibility">Group visibility</label>
                            <select class="custom-select" id="groupAppEditVisibility"
                                    v-model="groupVisibility" v-on:change="setVisibility()">
                                <option value="private">private</option>
                                <option value="public">public</option>
                                <option value="conditioned">conditioned</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import $ from 'jquery';
import { AppApi } from 'neucore-js-client';
import { GroupApi } from 'neucore-js-client';

module.exports = {
    props: {
        type: ''
    },

    data: function() {
        return {
            newName: '',
            groupVisibility: '',
            groupNameHelp: 'Allowed characters (no spaces): A-Z a-z 0-9 - . _',
            item: null,
        }
    },

    methods: {
        showCreateModal: function() {
            this.newName = '';
            $('#createModal').modal('show');
        },

        /**
         * @param item the object to delete (must have id and name property)
         */
        showDeleteModal: function(item) {
            this.item = {
                id: item.id,
                name: item.name,
            };
            $('#deleteModal').modal('show');
        },

        showEditModal: function(item) {
            this.item = {
                id: item.id,
                name: item.name,
            };
            if (this.type === 'Group') {
                this.groupVisibility = item.visibility
            }
            $('#editModal').modal('show');
        },

        hideEditModal: function() {
            $('#editModal').modal('hide');
        },

        create: function() {
            const vm = this;
            let api;
            if (this.type === 'Group') {
                api = new GroupApi();
            } else if (this.type === 'App') {
                api = new AppApi();
            } else {
                return;
            }

            api['create'].apply(api, [this.newName, function(error, data, response) {
                if (response.status === 409) {
                    vm.message('A '+ vm.type +' with this name already exists.', 'error');
                } else if (response.status === 400) {
                    vm.message('Invalid '+ vm.type +' name.', 'error');
                } else if (error) {
                    vm.message('Error creating ' + vm.type, 'error');
                } else {
                    $('#createModal').modal('hide');
                    vm.message(vm.type + ' created.', 'success');
                    vm.$emit('created', data.id);
                }
            }]);
        },

        deleteIt: function() {
            const vm = this;
            let api;
            if (this.type === 'Group') {
                api = new GroupApi();
            } else if (this.type === 'App') {
                api = new AppApi();
            } else {
                return;
            }

            api['callDelete'].apply(api, [this.item.id, function(error) {
                if (error) {
                    vm.message('Error deleting ' + vm.type, 'error');
                } else {
                    $('#deleteModal').modal('hide');
                    vm.message(vm.type + ' deleted.', 'success');
                    vm.$emit('deleted');
                }
            }]);
        },

        rename: function() {
            const vm = this;
            let api;
            if (this.type === 'Group') {
                api = new GroupApi();
            } else if (this.type === 'App') {
                api = new AppApi();
            } else {
                return;
            }

            api['rename'].apply(api, [this.item.id, this.item.name, function(error, data, response) {
                if (response.status === 409) {
                    vm.message('A '+ vm.type +' with this name already exists.', 'error');
                } else if (response.status === 400) {
                    vm.message('Invalid '+ vm.type +' name.', 'error');
                } else if (error) {
                    vm.message('Error creating ' + vm.type, 'error');
                } else {
                    vm.message(vm.type + ' renamed.', 'success');
                    vm.$emit('itemChange');
                    vm.$root.$emit('playerChange');
                }
            }]);
        },

        setVisibility: function() {
            const vm = this;
            new GroupApi().setVisibility(this.item.id, this.groupVisibility, function(error) {
                if (error) {
                    vm.message('Error saving visibility.', 'error');
                } else {
                    vm.message('Visibility saved.', 'success');
                    vm.$emit('itemChange');
                    vm.$root.$emit('playerChange');
                }
            });
        },
    },
}
</script>

<style scoped>

</style>
