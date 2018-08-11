<!--
Modals to create and delete groups and apps
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
                            <label>{{ type }} name</label>
                            <input class="form-control" v-model="newName" type="text" title="">
                            <small class="form-text text-muted">
                                {{ nameHelp }}
                            </small>
                        </div>
                        <div v-if="errorMessage" v-cloak class="alert alert-danger">
                            {{ errorMessage }}
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
                <div v-cloak v-if="toDelete" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete {{ type }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to <strong>permanently</strong> delete this {{ type }}?</p>
                        <p class="text-warning">{{ toDelete.name }}</p>
                        <div v-if="errorMessage" v-cloak class="alert alert-danger">
                            {{ errorMessage }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" v-on:click="deleteIt()">DELETE {{ type }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
module.exports = {
    props: {
        swagger: Object,
        type: ''
    },

    data: function() {
        return {
            newName: '',
            nameHelp: '',
            toDelete: null,
            errorMessage: '',
        }
    },

    methods: {
        showCreateModal: function(nameHelp) {
            this.newName = '';
            this.nameHelp = nameHelp || '';
            this.errorMessage = '';
            window.jQuery('#createModal').modal('show');
        },

        /**
         * @param toDelete the object to delete (must have id and name property)
         */
        showDeleteModal: function(toDelete) {
            this.toDelete = toDelete;
            this.errorMessage = '';
            window.jQuery('#deleteModal').modal('show');
        },

        create: function() {
            const vm = this;
            let api;
            if (this.type === 'Group') {
                api = new this.swagger.GroupApi();
            } else if (this.type === 'App') {
                api = new this.swagger.AppApi();
            } else {
                return;
            }

            vm.loading(true);
            api['create'].apply(api, [this.newName, function(error, data, response) {
                vm.loading(false);
                if (response.status === 409) {
                    vm.errorMessage = 'A '+ vm.type +' with this name already exists.';
                } else if (response.status === 400) {
                    vm.errorMessage = 'Invalid '+ vm.type +' name.';
                } else if (error) {
                    vm.errorMessage = 'Error: ' + response.status +' '+ response.statusText;
                } else {
                    window.jQuery('#createModal').modal('hide');
                    vm.message(vm.type + ' created.', 'success');
                    vm.$emit('created', data.id);
                }
            }]);
        },

        deleteIt: function() {
            const vm = this;
            let api;
            if (this.type === 'Group') {
                api = new this.swagger.GroupApi();
            } else if (this.type === 'App') {
                api = new this.swagger.AppApi();
            } else {
                return;
            }

            api['callDelete'].apply(api, [this.toDelete.id, function(error, data, response) {
                vm.loading(false);
                if (error) {
                    vm.errorMessage = 'Error: ' + response.status +' '+ response.statusText;
                } else {
                    window.jQuery('#deleteModal').modal('hide');
                    vm.message(vm.type + ' deleted.', 'success');
                    vm.$emit('deleted');
                }
            }]);
        },
    },
}
</script>

<style scoped>

</style>
