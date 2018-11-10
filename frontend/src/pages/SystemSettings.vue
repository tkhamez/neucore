<template>
<div class="container-fluid">

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>System Settings</h1>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card border-secondary mb-3">
                <ul class="list-group list-group-flush">
                    <li v-for="variable in settings" class="list-group-item">

                        <div v-if="checkboxes.indexOf(variable.name) !== -1" class="form-check">
                            <label class="form-check-label">
                                <input class="form-check-input" type="checkbox"
                                       :name="variable.name"
                                       value="1"
                                       :checked="variable.value === '1'"
                                       @change="changeSetting(variable.name, $event.target.checked ? '1' : '0')"
                                >
                                <span v-if="variable.name === 'allow_character_deletion'">
                                    <em>Delete characters:</em>
                                    Check to allow users to delete their character.
                                </span>
                                <span v-if="variable.name === 'groups_require_valid_token'">
                                    <em>Deactivate Accounts:</em>
                                    Check this if the API for third-party applications should not return groups
                                    for a player account if one or more of its characters have an invalid token.
                                </span>
                                <span v-if="variable.name === 'show_preview_banner'">
                                    <em>Preview:</em>
                                    Check to shows the "preview" banner on the Home screen.
                                </span>
                            </label>
                        </div>

                    </li>
                </ul>
            </div>
        </div>
    </div>

</div>
</template>

<script>
module.exports = {
    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object],
        settings: Array,
    },

    data: function() {
        return {
            checkboxes: [
                'allow_character_deletion',
                'groups_require_valid_token',
                'show_preview_banner',
            ],
            api: null
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.init();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.init();
        },
    },

    methods: {
        init: function() {
            this.api = new this.swagger.SettingsApi();
        },

        changeSetting: function(name, value) {
            const vm = this;
            vm.loading(true);
            this.api.systemChange(name, value, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.$root.$emit('settingsChange');
            });
        }
    },
}
</script>

<style scoped>

</style>
