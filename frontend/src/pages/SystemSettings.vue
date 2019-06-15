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

                <div class="card-header">Customization</div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="col-form-label" for="customizationDocumentTitle">Document Title</label>
                        <input id="customizationDocumentTitle" type="text" class="form-control"
                               v-model="settings.customization_document_title"
                               v-on:input="changeSettingDelayed('customization_document_title', $event.target.value)">
                        <small class="form-text text-muted">
                            Value for HTML head title tag, i. e. name of the browser tab or bookmark.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationDefaultTheme">Theme</label>
                        <select id="customizationDefaultTheme" class="form-control"
                                v-model="settings.customization_default_theme"
                                @change="changeSetting(
                                    'customization_default_theme', settings.customization_default_theme
                                )">
                            <option v-for="theme in themes" v-bind:value="theme">
                                {{ theme }}
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            The default theme.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationHomepage">Website</label>
                        <input id="customizationHomepage" type="text" class="form-control"
                               v-model="settings.customization_website"
                               v-on:input="changeSettingDelayed('customization_website', $event.target.value)">
                        <small class="form-text text-muted">
                            URL for the links of the logos in the navigation bar and on the home page.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationNavTitle">Navigation Title</label>
                        <input id="customizationNavTitle" type="text" class="form-control"
                               v-model="settings.customization_nav_title"
                               v-on:input="changeSettingDelayed('customization_nav_title', $event.target.value)">
                        <small class="form-text text-muted">
                            Organization name used in navigation bar.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="customizationNavLogo" class="col-form-label">Navigation Logo</label><br>
                        <img :src="settings.customization_nav_logo" alt="logo">
                        <input type="file" class="mt-1" ref="customization_nav_logo"
                               id="customizationNavLogo" v-on:change="handleFileUpload('customization_nav_logo')">
                        <small class="form-text text-muted">
                            Organization logo used in navigation bar.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationHomeHeadline">Home Page Headline</label>
                        <input id="customizationHomeHeadline" type="text" class="form-control"
                               v-model="settings.customization_home_headline"
                               v-on:input="changeSettingDelayed('customization_home_headline', $event.target.value)">
                        <small class="form-text text-muted">
                            Headline on the home page.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationHomeDescription">Home Page Description</label>
                        <input id="customizationHomeDescription" type="text" class="form-control"
                               v-model="settings.customization_home_description"
                               v-on:input="changeSettingDelayed('customization_home_description', $event.target.value)">
                        <small class="form-text text-muted">
                            Text below the headline on the home page.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="customizationHomeLogo" class="col-form-label">Home Page Logo</label><br>
                        <img :src="settings.customization_home_logo" alt="logo">
                        <input type="file" class="mt-1" ref="customization_home_logo"
                               id="customizationHomeLogo" v-on:change="handleFileUpload('customization_home_logo')">
                        <small class="form-text text-muted">
                            Organization logo used on the home page.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="customizationHomeMarkdown" class="col-form-label">Home Page Text Area</label><br>
                        <textarea v-model="settings.customization_home_markdown" class="form-control"
                                  id="customizationHomeMarkdown" rows="9"></textarea>
                        <button class="btn btn-success" v-on:click="changeSetting(
                            'customization_home_markdown', settings.customization_home_markdown
                        )">save</button>
                        <small class="form-text text-muted">
                            Optional text area on the home page.
                            Supports <a href="https://markdown-it.github.io/" target="_blank">Markdown</a>,
                            with "typographer" and these plugins:
                            <a href="https://github.com/arve0/markdown-it-attrs">attrs</a>
                                (use with Bootstrap classes "text-primary", "bg-warning"
                                <a href="https://bootswatch.com/darkly/">etc.</a>),
                            <a href="https://github.com/markdown-it/markdown-it-mark">mark</a>,
                            <a href="https://github.com/markdown-it/markdown-it-emoji/blob/master/lib/data/light.json">
                                emoji</a> light,
                            <a href="https://github.com/markdown-it/markdown-it-sub">sub</a>,
                            <a href="https://github.com/markdown-it/markdown-it-sup">sup</a>,
                            <a href="https://github.com/markdown-it/markdown-it-abbr">abbr</a>.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationFooterText">Footer Text</label>
                        <input id="customizationFooterText" type="text" class="form-control"
                               v-model="settings.customization_footer_text"
                               v-on:input="changeSettingDelayed('customization_footer_text', $event.target.value)">
                        <small class="form-text text-muted">
                            Text for the footer.
                        </small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label class="col-form-label" for="customizationGithub">GitHub</label>
                        <input id="customizationGithub" type="text" class="form-control"
                               v-model="settings.customization_github"
                               v-on:input="changeSettingDelayed('customization_github', $event.target.value)">
                        <small class="form-text text-muted">
                            URL of GitHub repository for various links to the documentation.
                        </small>
                    </div>
                </div>

                <div class="card-header">Features</div>
                <div class="card-body">
                    <em>Deactivate Accounts:</em>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="groups_require_valid_token" name="groups_require_valid_token"
                               :checked="settings.groups_require_valid_token === '1'"
                               @change="changeSetting('groups_require_valid_token', $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="groups_require_valid_token">
                            Check this if the API for applications should not return groups
                            for a player account if one or more of its characters have an invalid token
                            (no token counts as invalid), "managed" accounts are excluded from this.
                        </label>
                    </div>
                    <label class="mt-2">
                        <input type="text" pattern="[0-9]*" class="form-control input-delay"
                               v-model="settings.account_deactivation_delay"
                               v-on:input="changeSettingDelayed('account_deactivation_delay', $event.target.value)">
                        Delay the deactivation after a token became invalid (hours).
                    </label>
                    <hr>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="allow_login_managed" name="allow_login_managed"
                               :checked="settings.allow_login_managed === '1'"
                               @change="changeSetting('allow_login_managed', $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="allow_login_managed">
                            <em>Allow "managed" Login:</em>
                            Enables the login URL for managed accounts that do not require ESI scopes.
                        </label>
                    </div>
                    <hr>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="allow_character_deletion" name="allow_character_deletion"
                               :checked="settings.allow_character_deletion === '1'"
                               @change="changeSetting('allow_character_deletion', $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="allow_character_deletion">
                            <em>Delete characters:</em>
                            Check to allow users to delete their characters.
                        </label>
                    </div>
                </div>

                <div class="card-header">EVE Mails</div>
                <div class="card-body">
                    <h4>Sender</h4>
                    <p>
                        <span v-if="settings.mail_character === ''">
                            <a href="/login-mail"><img src="/static/eve_sso.png" alt="LOG IN with EVE Online"></a>
                        </span>
                        <span v-else>
                            <span class="text-info">{{ settings.mail_character }}</span>
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
                        because one of its characters contains an invalid or no ESI token.
                    </p>

                    <button class="btn btn-success btn-sm" v-on:click="sendMailAccountDisabledTestMail()">
                        Send test mail
                    </button>
                    <small>Mail will be send to the logged-in user.</small>

                    <div class="custom-control custom-checkbox mb-2 mt-3">
                        <input class="custom-control-input" type="checkbox" value="1"
                               id="mail_account_disabled_active" name="mail_account_disabled_active"
                               :checked="settings.mail_account_disabled_active === '1'"
                               @change="changeSetting('mail_account_disabled_active',
                                                      $event.target.checked ? '1' : '0')"
                        >
                        <label class="custom-control-label" for="mail_account_disabled_active">Activate mail</label>
                    </div>
                    <div class="form-group">
                        <label class="col-form-label">Alliances</label>
                        <multiselect v-model="mailAccountDisabledAlliances" :options="alliances"
                                     label="name" track-by="id" :multiple="true"
                                     :loading="false" :searchable="true"
                                     placeholder="Select alliances"></multiselect>
                        <small class="form-text text-muted">
                            The mail is only sent if at least one character in a player account
                            belongs to one of these alliances.<br>
                            You can add missing alliances in the <a href="#GroupAdmin">Group Administration</a>.
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="col-form-label" for="mailAccountDisabledSubject">Subject</label>
                        <input id="mailAccountDisabledSubject" type="text" class="form-control"
                               v-model="settings.mail_account_disabled_subject"
                               v-on:input="changeSettingDelayed('mail_account_disabled_subject', $event.target.value)">
                    </div>
                    <div class="form-group">
                        <label for="mailAccountDisabledBody">Message</label>
                        <textarea v-model="settings.mail_account_disabled_body" class="form-control"
                                  v-on:input="changeSettingDelayed('mail_account_disabled_body', $event.target.value)"
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
        settings: Object,
    },

    data: function() {
        return {
            api: null,
            alliances: [],
            alliancesLoaded: false,
            loginUrlDirector: null,
            mailAccountDisabledAlliances: null,
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

        mailAccountDisabledAlliances: function(newValues, oldValues) {
            if (oldValues === null) {
                return;
            }
            const oldAllianceIds = [];
            for (let oldValue of oldValues) {
                oldAllianceIds.push(oldValue.id);
            }
            const newAllianceIds = [];
            for (let alliance of this.mailAccountDisabledAlliances) {
                newAllianceIds.push(alliance.id);
            }
            if (newAllianceIds.join(',') === oldAllianceIds.join(',')) {
                return;
            }
            this.changeSetting('mail_account_disabled_alliances', newAllianceIds.join(','));
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
                vm.alliancesLoaded = true;
                vm.readSettings();
            });
        },

        readSettings: function() {
            if (! this.alliancesLoaded || ! this.settings.hasOwnProperty('account_deactivation_delay')) {
                return; // wait for alliance list and settings
            }

            this.directors = [];
            this.mailAccountDisabledAlliances = [];

            for (const [name, value] of Object.entries(this.settings)) {
                if (name === 'mail_account_disabled_alliances') {
                    for (let allianceId of value.split(',')) {
                        allianceId = parseInt(allianceId);
                        for (let alliance of this.alliances) {
                            if (alliance.id === allianceId) {
                                this.mailAccountDisabledAlliances.push(alliance);
                                break;
                            }
                        }
                    }
                } else if (name.indexOf('director_char_') !== -1) {
                    try {
                        this.directors.push({
                            'name': name,
                            'value': JSON.parse(value)
                        });
                    } catch(err) {}
                }
            }
        },

        removeMailChar: function() {
            this.settings.mail_character = '';
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

        handleFileUpload: function(name) {
            const vm = this;
            const file = this.$refs[name].files[0];
            const reader  = new FileReader();

            reader.addEventListener('load', function() {
                const image = reader.result;
                vm.changeSetting(name, image);
            }, false);

            if (file) {
                reader.readAsDataURL(file)
            }
        },

        changeSettingDelayed: function(name, value) {
            // use value from parameter (input event) instead of value from this.settings
            // because the model is not updated on touch devices during IME composition
            this.changeSettingDebounced(this, name, value);
        },

        changeSettingDebounced: window._.debounce((vm, name, value) => {
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

                // only propagate the change of variables that needs it
                if ([
                        'allow_character_deletion',
                        'customization_home_logo',
                        'customization_nav_logo',
                        'customization_home_markdown',
                        'customization_document_title',
                        'customization_default_theme',
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
