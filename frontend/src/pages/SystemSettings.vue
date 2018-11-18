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
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="groups_require_valid_token" name="groups_require_valid_token"
                               :checked="variables['groups_require_valid_token'] === '1'"
                               @change="changeSetting('groups_require_valid_token',
                                                      $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="groups_require_valid_token">
                            <em>Deactivate Accounts:</em>
                            Check this if the API for third-party applications should not return groups
                            for a player account if one or more of its characters have an invalid token.
                        </label>
                    </div>
                    <hr>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="allow_character_deletion" name="allow_character_deletion"
                               :checked="variables['allow_character_deletion'] === '1'"
                               @change="changeSetting('allow_character_deletion',
                                                      $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="allow_character_deletion">
                            <em>Delete characters:</em>
                            Check to allow users to delete their character.
                        </label>
                    </div>
                    <hr>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="show_preview_banner" name="show_preview_banner"
                               :checked="variables['show_preview_banner'] === '1'"
                               @change="changeSetting('show_preview_banner',
                                                      $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="show_preview_banner">
                            <em>Preview:</em>
                            Check to shows the "preview" banner on the Home screen.
                        </label>
                    </div>

                </div>
                <div class="card-header">
                    Mail
                </div>
                <div class="card-body">
                    <h4>Character</h4>
                    <p>
                        <span v-if="mailCharacter === ''">
                            <a :href="loginUrl"><img src="/images/eve_sso.png" alt="LOG IN with EVE Online"></a>
                        </span>
                        <span v-else>
                            <span class="text-info">{{ mailCharacter }}</span>
                            <button type="button" class="btn btn-danger btn-sm ml-1"
                                    v-on:click="mailCharacter = ''; changeSetting('mail_character', '')">
                                remove
                            </button>
                        </span>
                    </p>

                    <h4 class="mt-4">"Account disabled" Notification</h4>
                    <p>
                        This EVE mail is sent when an account has been deactivated
                        because one of its characters contains an invalid ESI token.
                    </p>

                    <button class="btn btn-success btn-sm" v-on:click="sendMailAccountDisabledTestMail()">
                        Send test mail
                    </button>
                    Mail will be send to the logged-in user.

                    <div class="custom-control custom-checkbox mb-2 mt-3">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="mail_account_disabled_active" name="mail_account_disabled_active"
                               v-model="mailAccountDisabledActive"
                               @change="changeSetting('mail_account_disabled_active',
                                                      mailAccountDisabledActive ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="mail_account_disabled_active">Activate mail</label>
                    </div>
                    <div class="form-group">
                        <label class="col-form-label" for="mailAccountDisabledSubject">Subject</label>
                        <input v-model="mailAccountDisabledSubject" type="text" class="form-control"
                               id="mailAccountDisabledSubject">
                    </div>
                    <div class="form-group">
                        <label for="mailAccountDisabledBody">Body</label>
                        <textarea v-model="mailAccountDisabledBody" class="form-control"
                                  id="mailAccountDisabledBody" rows="6"></textarea>
                    </div>
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
            mailCharacter: '',
            mailAccountDisabledActive: false,
            mailAccountDisabledSubject: '',
            mailAccountDisabledBody: '',
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.init();
        }
        this.$root.$emit('settingsChange'); // make sure the data is up to date
    },

    watch: {
        initialized: function() { // on refresh
            this.init();
            this.checkLoginResult();
        },

        settings: function() {
            this.readSettings();
        },

        mailAccountDisabledSubject: function() {
            this.changeSettingDelayed(this, 'mail_account_disabled_subject', this.mailAccountDisabledSubject);
        },

        mailAccountDisabledBody: function() {
            this.changeSettingDelayed(this, 'mail_account_disabled_body', this.mailAccountDisabledBody);
        },
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
            this.mailCharacter = this.variables['mail_character'];
            this.mailAccountDisabledActive = this.variables['mail_account_disabled_active'] === '1';
            this.mailAccountDisabledSubject = this.variables['mail_account_disabled_subject'];
            this.mailAccountDisabledBody = this.variables['mail_account_disabled_body'];
        },

        changeSettingDelayed: _.debounce((vm, name, value) => {
            vm.changeSetting(name, value);
        }, 250),

        changeSetting: function(name, value) {
            const vm = this;
            vm.loading(true);
            this.api.systemChange(name, value, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }

                // propagate only the change of variables that are used elsewhere
                if (['groups_require_valid_token',
                    'allow_character_deletion',
                    'show_preview_banner'].indexOf(name) !== -1
                ) {
                    vm.$root.$emit('settingsChange');
                }
            });
        },

        getLoginUrl: function() {
            const vm = this;
            vm.loginUrl = null;

            vm.loading(true);
            const params = { redirect: '/#SystemSettings/login', type: 'mail' };
            new this.swagger.AuthApi().loginUrl(params, function(error, data) {
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

        sendMailAccountDisabledTestMail: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.SettingsApi().sendAccountDisabledMail(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                if (data !== '') {
                    vm.$root.message(data, 'error');
                } else {
                    vm.$root.message('Mail sent.', 'success');
                }
            });
        },
    },
}
</script>

<style scoped>

</style>
