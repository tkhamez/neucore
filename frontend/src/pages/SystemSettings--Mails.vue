<template>
<div class="card border-secondary mb-3">
    <div class="card-header">Sender</div>
    <div class="card-body">
        <span v-if="settings.mail_character === ''">
            <a :href="loginHost + '/login-mail'"><img src="../assets/eve_sso.png" alt="LOG IN with EVE Online"></a>
        </span>
        <span v-else>
            <span class="text-info">{{ settings.mail_character }}</span>
            <button type="button" class="btn btn-danger btn-sm ml-1" v-on:click="removeMailChar()">remove</button>
        </span>
        <br>
        <span class="small">The character is used for all mails.</span>
    </div>

    <div class="card-header">Invalid ESI token</div>
    <div class="card-body">
        <p>
            This EVE mail is sent when an account contains a character with an invalid or no ESI token.<br>
            Accounts with the "managed" status are excluded from this.
        </p>
        <button class="btn btn-success btn-sm" v-on:click="sendInvalidTokenTestMail()">Send test mail</button>
        <small>The mail is sent to the logged-in user.</small>
        <div class="custom-control custom-checkbox mb-2 mt-3">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="mail_invalid_token_active" name="mail_invalid_token_active"
                   :checked="settings.mail_invalid_token_active === '1'"
                   @change="$emit('changeSetting', 'mail_invalid_token_active', $event.target.checked ? '1' : '0')">
            <label class="custom-control-label" for="mail_invalid_token_active">Activate mail</label>
        </div>
        <div class="form-group">
            <p class="form-text mt-3 mb-0">
                The mail is only sent if at least one character in a player account
                belongs to one of the following alliances or corporations:
            </p>
            <label class="col-form-label">Alliances</label>
            <multiselect v-model="mailInvalidTokenAlliances" :options="allAlliances"
                         label="name" track-by="id" :multiple="true"
                         :loading="false" :searchable="true"
                         placeholder="Select alliances">
            </multiselect>
            <label class="col-form-label">Corporations</label>
            <multiselect v-model="mailInvalidTokenCorporations" :options="allCorporations"
                         label="name" track-by="id" :multiple="true"
                         :loading="false" :searchable="true"
                         placeholder="Select corporations">
            </multiselect>
        </div>
        <div class="form-group">
            <label class="col-form-label" for="mailInvalidTokenSubject">Subject</label>
            <input id="mailInvalidTokenSubject" type="text" class="form-control"
                   v-model="settings.mail_invalid_token_subject"
                   v-on:input="$emit('changeSettingDelayed', 'mail_invalid_token_subject', $event.target.value)">
        </div>
        <div class="form-group">
            <label for="mailInvalidTokenBody">Message</label>
            <textarea v-model="settings.mail_invalid_token_body" class="form-control"
                      v-on:input="$emit('changeSettingDelayed', 'mail_invalid_token_body', $event.target.value)"
                      id="mailInvalidTokenBody" rows="6"></textarea>
        </div>
    </div>

    <div class="card-header">Missing Character</div>
    <div class="card-body">
        <p>This EVE mail is sent to characters that have not been added to an account.</p>
        <button class="btn btn-success btn-sm" v-on:click="sendMissingCharacterTestMail()">Send test mail</button>
        <small>The mail is sent to the logged-in user.</small>
        <div class="custom-control custom-checkbox mb-2 mt-3">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="mail_missing_character_active" name="mail_missing_character_active"
                   :checked="settings.mail_missing_character_active === '1'"
                   @change="$emit('changeSetting', 'mail_missing_character_active', $event.target.checked ? '1' : '0')">
            <label class="custom-control-label" for="mail_missing_character_active">Activate mail</label>
        </div>
        <label class="mt-2">
            <input type="text" pattern="[0-9]*" class="form-control input-resend"
                   v-model="settings.mail_missing_character_resend"
                   v-on:input="$emit('changeSettingDelayed', 'mail_missing_character_resend', $event.target.value)">
            The mail will only be sent if the character has logged in within these number of days.
            Also the minimum number of days that must pass before the mail is resent.
            Must be greater 0.
        </label>
        <div class="form-group">
            <label class="col-form-label">
                The mail is sent to characters from one of the following corporations<br>
            </label>
            <multiselect v-model="mailMissingCharacterCorporations" :options="trackingCorporations"
                         label="name" track-by="id" :multiple="true"
                         :loading="false" :searchable="true"
                         placeholder="Select corporations">
            </multiselect>
            <small class="form-text text-muted">
                Only corporations with member tracking enabled, see
                <a href="#SystemSettings/Directors">SystemSettings -> Directors</a>
            </small>
        </div>
        <div class="form-group">
            <label class="col-form-label" for="mailMissingCharacterSubject">Subject</label>
            <input id="mailMissingCharacterSubject" type="text" class="form-control"
                   v-model="settings.mail_missing_character_subject"
                   v-on:input="$emit('changeSettingDelayed', 'mail_missing_character_subject', $event.target.value)">
        </div>
        <div class="form-group">
            <label for="mailMissingCharacterBody">Message</label>
            <textarea v-model="settings.mail_missing_character_body" class="form-control"
                      v-on:input="$emit('changeSettingDelayed', 'mail_missing_character_body', $event.target.value)"
                      id="mailMissingCharacterBody" rows="6"></textarea>
        </div>
    </div>
</div>
</template>

<script>
import {CorporationApi, SettingsApi} from 'neucore-js-client';

export default {
    props: {
        settings: Object,
    },

    data () {
        return {
            loginHost: '',
            allAlliances: [],
            allAlliancesLoaded: false,
            allCorporations: [],
            allCorporationsLoaded: false,
            trackingCorporations: [],
            trackingCorporationsLoaded: false,
            mailInvalidTokenAlliances: null,
            mailInvalidTokenCorporations: null,
            mailMissingCharacterCorporations: null,
        }
    },

    mounted () {
        if (this.$root.backendHost) {
            this.loginHost = this.$root.backendHost;
        }

        this.$parent.loadLists();
        getCorporations(this);

        this.$parent.$on('alliancesLoaded', (data) => {
            this.allAlliances = data;
            this.allAlliancesLoaded = true;
            readSettings(this);
        });

        this.$parent.$on('corporationsLoaded', (data) => {
            this.allCorporations = data;
            this.allCorporationsLoaded = true;
            readSettings(this);
        });
    },

    watch: {
        settings () {
            readSettings(this);
        },

        mailInvalidTokenAlliances (newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.mailInvalidTokenAlliances);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'mail_invalid_token_alliances', newValue);
        },

        mailInvalidTokenCorporations (newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.mailInvalidTokenCorporations);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'mail_invalid_token_corporations', newValue);
        },

        mailMissingCharacterCorporations (newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.mailMissingCharacterCorporations);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'mail_missing_character_corporations', newValue);
        },
    },

    methods: {
        removeMailChar () {
            this.settings.mail_character = '';
            this.$emit('changeSetting', 'mail_character', '');
        },

        sendInvalidTokenTestMail () {
            const vm = this;
            new SettingsApi().sendInvalidTokenMail((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                if (data !== '') {
                    vm.$root.message(data, 'error');
                } else {
                    vm.$root.message('"Invalid ESI Token" Mail sent.', 'success');
                }
            });
        },

        sendMissingCharacterTestMail () {
            const vm = this;
            new SettingsApi().sendMissingCharacterMail((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                if (data !== '') {
                    vm.$root.message(data, 'error');
                } else {
                    vm.$root.message('"Missing Character" Mail sent.', 'success');
                }
            });
        },
    },
}

function getCorporations(vm) {
    new CorporationApi().corporationAllTrackedCorporations((error, data) => {
        if (! error) {
            vm.trackingCorporations = data;
            vm.trackingCorporationsLoaded = true;
            readSettings(vm);
        }
    });
}

function readSettings(vm) {
    if (
        ! vm.allAlliancesLoaded || ! vm.allCorporationsLoaded || ! vm.trackingCorporationsLoaded ||
        ! vm.settings.hasOwnProperty('account_deactivation_delay')
    ) {
        return; // wait for necessary data
    }

    vm.mailInvalidTokenAlliances = [];
    vm.mailInvalidTokenCorporations = [];
    vm.mailMissingCharacterCorporations = [];

    for (const [name, value] of Object.entries(vm.settings)) {
        if (name === 'mail_invalid_token_alliances') {
            vm.mailInvalidTokenAlliances = vm.$parent.buildIdArray(value, vm.allAlliances);
        } else if (name === 'mail_invalid_token_corporations') {
            vm.mailInvalidTokenCorporations = vm.$parent.buildIdArray(value, vm.allCorporations);
        } else if (name === 'mail_missing_character_corporations') {
            vm.mailMissingCharacterCorporations = vm.$parent.buildIdArray(value, vm.trackingCorporations);
        }
    }
}
</script>

<style scoped>
    .input-resend {
        display: inline;
        width: 75px;
    }
</style>
