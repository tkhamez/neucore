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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="entityEditCreateName">{{ type }} name</label>
                    <input class="form-control" v-model="newName" type="text" id="entityEditCreateName">
                    <span v-if="['Group', 'EveLogin'].indexOf(type) !== -1" class="form-text">
                        {{ messages.itemNameAllowedCharsHelp }}<br>
                        Maximum length: {{ type === 'EveLogin' ? '20' : '64' }}
                        <span v-if="type === 'EveLogin'">
                            <br>
                            Names starting with 'core.' are reserved for internal use.
                        </span>
                    </span>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" v-on:click="functionCreate(newName)">Create</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-dialog">
            <div v-cloak v-if="item" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete {{ type }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <strong>permanently</strong> delete this {{ type }}?</p>
                    <p class="text-warning">{{ item.name ? item.name : item.id }}</p>
                    <p v-cloak v-if="type === 'EveLogin'">
                        <span role="img" class="fas fa-exclamation-triangle text-warning"></span>
                        This will also delete <strong>all</strong> associated existing tokens from any character!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" v-on:click="functionDelete(item.id)">
                        DELETE {{ type }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-dialog">
            <div v-cloak v-if="item" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ type }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="entityEditEditName">{{ type }} name</label>
                    <input v-model="item.name" class="form-control" type="text" id="entityEditEditName">
                    <span v-if="type === 'Group'" class="form-text">{{ messages.itemNameAllowedCharsHelp }}</span>
                    <p v-cloak v-if="type === 'Group'" class="text-warning">
                        Please note, that renaming a group may break third party apps that rely on
                        the name instead of the ID.
                    </p>
                    <button type="button" class="mt-3 btn btn-warning" v-on:click="functionRename(item.id, item.name)">
                        Rename {{ type }}
                    </button>

                    <hr>

                    <div v-cloak v-if="type === 'Group'">
                        <label class="form-label" for="entityEditVisibility">Group visibility</label>
                        <select class="form-select" id="entityEditVisibility"
                                v-model="groupVisibility" v-on:change="setVisibility()">
                            <option value="private">private</option>
                            <option value="public">public</option>
                        </select>
                    </div>
                    <div v-cloak v-if="type === 'Group'" class="form-check mt-3">
                        <label class="form-check-label" for="entityEditAutoAccept">
                            Automatically accept applications
                        </label>
                        <input class="form-check-input" type="checkbox" id="entityEditAutoAccept"
                               v-model="groupAutoAccept" v-on:change="setAutoAccept()">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>
</div>
</template>

<script>
import {Modal} from 'bootstrap';
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
            createModal: null,
            deleteModal: null,
            editModal: null,
        }
    },

    methods: {
        showCreateModal: function() {
            this.newName = '';
            this.createModal = new Modal('#createModal');
            this.createModal.show();
        },

        /**
         * @param item the object to delete (must have id and name property)
         */
        showDeleteModal: function(item) {
            this.item = {
                id: item.id,
                name: item.name,
            };
            this.deleteModal = new Modal('#deleteModal');
            this.deleteModal.show();
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
            this.editModal = new Modal('#editModal');
            this.editModal.show();
        },

        hideModal: function() {
            if (this.createModal) {
                this.createModal.hide();
            }
            if (this.deleteModal) {
                this.deleteModal.hide();
            }
            if (this.editModal) {
                this.editModal.hide();
            }
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
