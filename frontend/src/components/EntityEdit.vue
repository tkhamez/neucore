<!--
Modal windows to create, delete and edit entities
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
                        <label for="entityEditCreateName">{{ type }} name</label>
                        <input class="form-control" v-model="newName" type="text" id="entityEditCreateName">
                        <small v-if="['Group', 'EveLogin'].indexOf(type) !== -1" class="form-text text-muted">
                            {{ messages.itemNameAllowedCharsHelp }}<br>
                            Maximum length: {{ type === 'EveLogin' ? '20' : '64' }}
                            <span v-if="type === 'EveLogin'">
                                <br>
                                Names starting with 'core.' are reserved for internal use.
                            </span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" v-on:click="functionCreate(newName)">Create</button>
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
                    <p class="text-warning">{{ item.name ? item.name : item.id }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" v-on:click="functionDelete(item.id)">
                        DELETE {{ type }}
                    </button>
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
                        <label for="entityEditEditName">{{ type }} name</label>
                        <input v-model="item.name" class="form-control" type="text" id="entityEditEditName">
                        <small v-if="type === 'Group'" class="form-text text-muted">
                            {{ messages.itemNameAllowedCharsHelp }}
                        </small>
                    </div>
                    <p v-cloak v-if="type === 'Group'" class="text-warning">
                        Please note, that renaming a group may break third party apps that rely on
                        the name instead of the ID.
                    </p>
                    <button type="button" class="btn btn-warning" v-on:click="functionRename(item.id, item.name)">
                        Rename {{ type }}
                    </button>

                    <hr>

                    <div v-cloak v-if="type === 'Group'" class="form-group">
                        <label for="entityEditVisibility">Group visibility</label>
                        <select class="custom-select" id="entityEditVisibility"
                                v-model="groupVisibility" v-on:change="setVisibility()">
                            <option value="private">private</option>
                            <option value="public">public</option>
                        </select>
                    </div>
                    <div v-cloak v-if="type === 'Group'" class="custom-control custom-checkbox mb-2">
                        <input class="custom-control-input" type="checkbox" id="entityEditAutoAccept"
                               v-model="groupAutoAccept" v-on:change="setAutoAccept()">
                        <label class="custom-control-label" for="entityEditAutoAccept">
                            Automatically accept applications
                        </label>
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
import {GroupApi} from 'neucore-js-client';

export default {
    props: {
        type: '',
        functionCreate: null,
        functionDelete: null,
        functionRename: null,
    },

    data: function() {
        return {
            newName: '',
            groupVisibility: '',
            groupAutoAccept: '',
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
                this.groupVisibility = item.visibility;
                this.groupAutoAccept = item.autoAccept;
            }
            $('#editModal').modal('show');
        },

        hideModal: function() {
            $('#createModal').modal('hide');
            $('#deleteModal').modal('hide');
            $('#editModal').modal('hide');
        },

        setVisibility() {
            const vm = this;
            new GroupApi().setVisibility(this.item.id, this.groupVisibility, function(error) {
                if (error) {
                    vm.message('Error saving visibility.', 'error');
                } else {
                    vm.message('Visibility saved.', 'success');
                    vm.emitter.emit('settingsChange');
                    vm.emitter.emit('playerChange');
                    vm.$emit('groupChange');
                }
            });
        },

        setAutoAccept() {
            const vm = this;
            new GroupApi().userGroupSetAutoAccept(this.item.id, this.groupAutoAccept ? 'on' : 'off', (error) => {
                if (error) {
                    vm.message('Error saving auto-accept.', 'error');
                } else {
                    vm.message('Auto-accept saved.', 'success');
                    vm.$emit('groupChange');
                }
            });
        }
    },
}
</script>
