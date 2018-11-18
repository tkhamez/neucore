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
                <div class="card-header">
                    General
                </div>
                <div class="card-body">
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox"
                                   name="groups_require_valid_token" value="1"
                                   :checked="variables['groups_require_valid_token'] === '1'"
                                   @change="changeSetting('groups_require_valid_token',
                                                          $event.target.checked ? '1' : '0')"
                            >
                            <em>Deactivate Accounts:</em>
                            Check this if the API for third-party applications should not return groups
                            for a player account if one or more of its characters have an invalid token.
                        </label>
                    </div>
                    <hr>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox"
                                   name="allow_character_deletion" value="1"
                                   :checked="variables['allow_character_deletion'] === '1'"
                                   @change="changeSetting('allow_character_deletion',
                                                          $event.target.checked ? '1' : '0')"
                            >
                            <em>Delete characters:</em>
                            Check to allow users to delete their character.
                        </label>
                    </div>
                    <hr>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox"
                                   name="show_preview_banner" value="1"
                                   :checked="variables['show_preview_banner'] === '1'"
                                   @change="changeSetting('show_preview_banner',
                                                          $event.target.checked ? '1' : '0')"
                            >
                            <em>Preview:</em>
                            Check to shows the "preview" banner on the Home screen.
                        </label>
                    </div>
                </div>
                <div class="card-header">
                    Mail
                </div>
                <div class="card-body">
                    <p>
                        Character:
                        <span v-if="variables['mail_character'] === ''">
                            <a :href="loginUrl"><img src="/images/eve_sso.png" alt="LOG IN with EVE Online"></a>
                        </span>
                        <span v-else>
                            {{ variables['mail_character'] }}
                            <button type="button" class="btn btn-danger"
                                    v-on:click="changeSetting('mail_character', '')">
                                remove
                            </button>
                        </span>
                    </p>
                    <p>
                        Subject:
                    </p>
                    <p>
                        Body:
                    </p>
                </div>
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
            variables: {},
            api: null,
            loginUrl: null,
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.init();
        }
        this.readSettings();
    },

    watch: {
        initialized: function() { // on refresh
            this.init();
            this.checkLoginResult();
        },

        settings: function() {
            this.readSettings();
        }
    },

    methods: {
        init: function() {
            this.api = new this.swagger.SettingsApi();
            this.getLoginUrl();
        },

        readSettings: function() {
            this.variables = {};
            for (let variable of this.settings) {
                this.variables[variable.name] = variable.value;
            }
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
        },

        getLoginUrl: function() {
            const vm = this;
            vm.loginUrl = null;

            vm.loading(true);
            new this.swagger.AuthApi().loginUrl({ redirect: '/#SystemSettings/login', type: 'mail' }, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.loginUrl = data;
            });
        },

        checkLoginResult: function() {
            if (this.route[1] !== 'login') {
                return;
            }
            this.$root.authResult();
        },
    },
}
</script>

<style scoped>

</style>
