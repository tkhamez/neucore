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

                <div class="card-header">General</div>
                <div class="card-body">
                    <em>Deactivate Accounts:</em>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="groups_require_valid_token" name="groups_require_valid_token"
                               :checked="variables['groups_require_valid_token'] === '1'"
                               @change="changeSetting('groups_require_valid_token',
                                                      $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="groups_require_valid_token">
                            Check this if the API for third-party applications should not return groups
                            for a player account if one or more of its characters have an invalid token.
                        </label>
                    </div>
                    <label class="mt-2">
                        <input type="text" pattern="[0-9]*" class="form-control input-delay"
                           v-model="accountDeactivationDelay">
                        Delay the deactivation after a token became invalid (hours).
                    </label>
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
                            Check to allow users to delete their characters.
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

                <div class="card-header">EVE Mails</div>
                <div class="card-body">
                    <h4>Sender</h4>
                    <p>
                        <span v-if="variables['mail_character'] === ''">
                            <a href="/login-mail"><img src="/images/eve_sso.png" alt="LOG IN with EVE Online"></a>
                        </span>
                        <span v-else>
                            <span class="text-info">{{ variables['mail_character'] }}</span>
                            <button type="button" class="btn btn-danger btn-sm ml-1"
                                    v-on:click="removeMailChar()">
                                remove
                            </button>
                        </span>
                    </p>

                    <hr>

                    <h4 class="mt-4">"Account disabled" Notification</h4>
                    <p>
                        This EVE mail is sent when an account has been deactivated
                        because one of its characters contains an invalid ESI token.
                    </p>

                    <button class="btn btn-success btn-sm" v-on:click="sendMailAccountDisabledTestMail()">
                        Send test mail
                    </button>
                    <small>Mail will be send to the logged-in user.</small>

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
                        <label class="col-form-label">Alliances</label>
                        <multiselect v-model="mailAccountDisabledAlliances" :options="alliances" :loading="false"
                                     label="name" track-by="id" :multiple="true"
                                     placeholder="Select alliances"></multiselect>
                        <small class="form-text text-muted">
                            The mail is only sent if at least one character in a player account
                            belongs to one of these alliances.<br>
                            You can add missing alliances in the <a href="#GroupAdmin">Group Administration</a>.
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="col-form-label" for="mailAccountDisabledSubject">Subject</label>
                        <input v-model="mailAccountDisabledSubject" type="text" class="form-control"
                               id="mailAccountDisabledSubject">
                    </div>
                    <div class="form-group">
                        <label for="mailAccountDisabledBody">Message</label>
                        <textarea v-model="mailAccountDisabledBody" class="form-control"
                                  id="mailAccountDisabledBody" rows="6"></textarea>
                    </div>
                </div>

                <div class="card-header">Directors</div>
                <div class="card-body">
                    <p>
                        Login URL for characters with director role:
                        <a :href="loginUrlDirector">{{ loginUrlDirector }}</a>
                    </p>
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Character</th>
                                <th>Corporation</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="director in directors">
                                <td>{{ director.value['character_name'] }}</td>
                                <td>
                                    [{{ director.value['corporation_ticker'] }}]
                                    {{ director.value['corporation_name'] }}
                                </td>
                                <td>
                                    <button type="button" class="btn btn-info"
                                            v-on:click="validateDirector(director.name)">
                                        <i class="fas fa-check"></i>
                                        validate
                                    </button>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger"
                                            v-on:click="removeDirector(director.name)">
                                        <i class="fas fa-minus-circle"></i>
                                        remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
            settingsLoaded: false,
            variables: {},
            api: null,
            alliances: [],
            loginUrlDirector: null,
            accountDeactivationDelay: null,
            mailAccountDisabledActive: false,
            mailAccountDisabledAlliances: null,
            mailAccountDisabledSubject: null,
            mailAccountDisabledBody: null,
            directors: [],
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.init();
        }
        this.$root.$emit('settingsChange'); // make sure the data is up to date

        // login URL for director chars
        let port = '';
        if (location.port !== "" && location.port !== 80 && location.port !== 443) {
            port = ':' + location.port;
        }
        this.loginUrlDirector = location.protocol + "//" + location.hostname + port + "/login-director"
    },

    watch: {
        initialized: function() { // on refresh
            this.init();
        },

        settings: function() {
            this.readSettings();
        },

        accountDeactivationDelay: function(newValue, oldValue) {
            if (oldValue === null) {
                return;
            }
            this.changeSettingDelayed(this, 'account_deactivation_delay', this.accountDeactivationDelay);
        },

        mailAccountDisabledAlliances: function(newValue, oldValue) {
            const allianceIds = [];
            for (let alliance of this.mailAccountDisabledAlliances) {
                allianceIds.push(alliance.id);
            }
            if (oldValue === null) {
                return;
            }
            this.changeSetting('mail_account_disabled_alliances', allianceIds.join(','));
        },

        mailAccountDisabledSubject: function(newValue, oldValue) {
            if (oldValue === null) {
                return;
            }
            this.changeSettingDelayed(this, 'mail_account_disabled_subject', this.mailAccountDisabledSubject);
        },

        mailAccountDisabledBody: function(newValue, oldValue) {
            if (oldValue === null) {
                return;
            }
            this.changeSettingDelayed(this, 'mail_account_disabled_body', this.mailAccountDisabledBody);
        },
    },

    methods: {
        init: function() {
            this.api = new this.swagger.SettingsApi();

            // get alliances
            const vm = this;
            vm.loading(true);
            new this.swagger.AllianceApi().all(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.alliances = data;
                vm.readSettings();
            });
        },

        readSettings: function() {
            if (this.settings.length === 0) {
                this.settingsLoaded = false; // reset state after "settingsChange" event
            }
            if (this.alliances.length === 0 || this.settings.length === 0 || this.settingsLoaded) {
                return; // wait for alliance list and settings
            }

            this.directors = [];
            this.mailAccountDisabledAlliances = [];

            for (let variable of this.settings) {
                if (variable.name === 'account_deactivation_delay') {
                    this.accountDeactivationDelay = variable.value.toString();
                } else if (variable.name === 'mail_account_disabled_active') {
                    this.mailAccountDisabledActive = variable.value === '1';
                } else if (variable.name === 'mail_account_disabled_alliances') {
                    for (let allianceId of variable.value.split(',')) {
                        allianceId = parseInt(allianceId);
                        for (let alliance of this.alliances) {
                            if (alliance.id === allianceId) {
                                this.mailAccountDisabledAlliances.push(alliance);
                                break;
                            }
                        }
                    }
                } else if (variable.name === 'mail_account_disabled_subject') {
                    this.mailAccountDisabledSubject = variable.value;
                } else if (variable.name === 'mail_account_disabled_body') {
                    this.mailAccountDisabledBody = variable.value;
                } else if (variable.name.indexOf('director_char_') !== -1) {
                    try {
                        this.directors.push({
                            'name': variable.name,
                            'value': JSON.parse(variable.value)
                        });
                    } catch(err) {}
                } else {
                    this.variables[variable.name] = variable.value;
                }
            }

            this.settingsLoaded = true;
        },

        removeMailChar: function() {
            this.variables['mail_character'] = '';
            this.changeSetting('mail_character', '');
        },

        removeDirector: function(name) {
            this.changeSetting(name, '');
        },

        validateDirector: function(name) {
            const vm = this;
            vm.loading(true);
            this.api.validateDirector(name, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.$root.message(
                    data ? 'The Token is valid and character has the director role.' : 'Validation failed.',
                    data ? 'info' : 'warning'
                );
                vm.$root.$emit('settingsChange');
            });
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

                // propagate only the change of variables that are used elsewhere or were deleted
                if ([
                        'groups_require_valid_token',
                        'allow_character_deletion',
                        'show_preview_banner',
                        'mail_character',
                    ].indexOf(name) !== -1 ||
                    name.indexOf('director_char_') !== -1
                ) {
                    vm.$root.$emit('settingsChange');
                }
            });
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
.input-delay {
    display: inline;
    width: 70px;
}
</style>
