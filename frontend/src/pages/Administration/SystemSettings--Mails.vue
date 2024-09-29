<template>
<div class="card border-secondary mb-3">
    <div class="card-header"><h6>Sender</h6></div>
    <div class="card-body">
        <span v-if="settings.mail_character === ''">
            <a :href="`${loginHost}/login/${loginNames.mail}`">
                <img src="../../../public/img/eve_sso.png" alt="LOG IN with EVE Online">
            </a>
        </span>
        <span v-else>
            <span class="text-info">{{ settings.mail_character }}</span>
            <button type="button" class="btn btn-danger btn-sm ms-1" v-on:click="removeMailChar()">remove</button>
        </span>
        <br>
        <span class="small">The character is used for all mails.</span>
    </div>

    <div class="card-header mt-3"><h6>Invalid ESI token</h6></div>
    <div class="card-body">
        <p>
            This EVE mail is sent when an account contains a character with an invalid or no ESI token.<br>
            Accounts with the "manually managed" status are excluded from this.
        </p>
        <button class="btn btn-success btn-sm" v-on:click="sendInvalidTokenTestMail()">Send test mail</button>
        <small>The mail is sent to the logged-in user.</small>
        <div class="form-check mb-4 mt-4">
            <label class="form-check-label" for="mail_invalid_token_active">Activate mail</label>
            <input class="form-check-input" type="checkbox" value="1"
                   id="mail_invalid_token_active" name="mail_invalid_token_active"
                   :checked="settings.mail_invalid_token_active === '1'"
                   @change="$emit('changeSetting', 'mail_invalid_token_active', $event.target.checked ? '1' : '0')">
        </div>
        <p class="mt-3 mb-0">
            The mail is only sent if at least one character in a player account
            belongs to one of the following alliances or corporations:
        </p>
        <label class="col-form-label" for="systemSettingsMailsMailInvalidTokenAlliances">Alliances</label>
        <multiselect v-model="mailInvalidTokenAlliances" :options="allAlliances"
                     id="systemSettingsMailsMailInvalidTokenAlliances"
                     label="name" track-by="id" :multiple="true"
                     :loading="isLoading" :searchable="true"
                     @search-change="(query) => findAlliancesOrCorporations(query, 'Alliances')"
                     :placeholder="`Select alliances ${messages.typeToSearch2}`">
        </multiselect>
        <label class="col-form-label" for="systemSettingsMailsMailInvalidTokenCorporations">Corporations</label>
        <multiselect v-model="mailInvalidTokenCorporations" :options="allCorporations"
                     id="systemSettingsMailsMailInvalidTokenCorporations"
                     label="name" track-by="id" :multiple="true"
                     :loading="isLoading" :searchable="true"
                     @search-change="(query) => findAlliancesOrCorporations(query, 'Corporations')"
                     :placeholder="`Select corporations ${messages.typeToSearch2}`">
        </multiselect>
        <br>
        <label class="col-form-label" for="mailInvalidTokenSubject">Subject</label>
        <input id="mailInvalidTokenSubject" type="text" class="form-control"
               v-model="settings.mail_invalid_token_subject"
               v-on:input="$emit('changeSettingDelayed', 'mail_invalid_token_subject', $event.target.value)">
        <br>
        <label for="mailInvalidTokenBody">Message</label>
        <textarea v-model="settings.mail_invalid_token_body" class="form-control"
                  v-on:input="$emit('changeSettingDelayed', 'mail_invalid_token_body', $event.target.value)"
                  id="mailInvalidTokenBody" rows="6"></textarea>
    </div>

    <div class="card-header mt-3"><h6>Missing Character</h6></div>
    <div class="card-body">
        <p>This EVE mail is sent to characters that have not been added to an account.</p>
        <button class="btn btn-success btn-sm" v-on:click="sendMissingCharacterTestMail()">Send test mail</button>
        <small>The mail is sent to the logged-in user.</small>
        <div class="form-check mb-2 mt-3">
            <label class="form-check-label" for="mail_missing_character_active">Activate mail</label>
            <input class="form-check-input" type="checkbox" value="1"
                   id="mail_missing_character_active" name="mail_missing_character_active"
                   :checked="settings.mail_missing_character_active === '1'"
                   @change="$emit('changeSetting', 'mail_missing_character_active', $event.target.checked ? '1' : '0')">
        </div>
        <label class="mt-3">
            <input type="text" pattern="[0-9]*" class="form-control input-resend"
                   v-model="settings.mail_missing_character_resend"
                   v-on:input="$emit('changeSettingDelayed', 'mail_missing_character_resend', $event.target.value)">
            The mail will only be sent if the character has logged in within these number of days.
            Also the minimum number of days that must pass before the mail is resent.
            Must be greater 0.
        </label>
        <div class="mt-3">
            <label class="col-form-label" for="systemSettingsMailsMailMissingCharacterCorporations">
                The mail is only sent to characters from the following corporations:<br>
            </label>
            <multiselect v-model="mailMissingCharacterCorporations" :options="trackingCorporations"
                         id="systemSettingsMailsMailMissingCharacterCorporations"
                         label="name" track-by="id" :multiple="true"
                         :loading="false" :searchable="true"
                         placeholder="Select corporations">
            </multiselect>
            <div class="form-text lh-sm">
                Only corporations with member tracking enabled, see
                <a :href="'#EVELoginAdmin'">EVE Logins</a>, {{ loginNames.tracking }} login.
            </div>
        </div>
        <div class="mt-3">
            <label class="col-form-label" for="mailMissingCharacterSubject">Subject</label>
            <input id="mailMissingCharacterSubject" type="text" class="form-control"
                   v-model="settings.mail_missing_character_subject"
                   v-on:input="$emit('changeSettingDelayed', 'mail_missing_character_subject', $event.target.value)">
        </div>
        <div class="mt-4">
            <label for="mailMissingCharacterBody">Message</label>
            <textarea v-model="settings.mail_missing_character_body" class="form-control"
                      v-on:input="$emit('changeSettingDelayed', 'mail_missing_character_body', $event.target.value)"
                      id="mailMissingCharacterBody" rows="6"></textarea>
        </div>
    </div>
</div>
</template>

<script>
import Multiselect from '@suadelabs/vue3-multiselect';
import {AllianceApi, CorporationApi, SettingsApi} from 'neucore-js-client';
import Data from "../../classes/Data";
import Helper from "../../classes/Helper";
import Util from "../../classes/Util";

export default {
    components: {
        Multiselect,
    },

    inject: ['store'],

    data() {
        return {
            h: new Helper(this),
            settings: { ...this.store.state.settings },
            loginNames: Data.loginNames,
            messages: Data.messages,
            loginHost: '',
            isLoading: false,
            allAlliancesChanged: 0,
            allCorporationsChanged: 0,
            trackingCorporationsChanged: 0,
            allAlliances: [],
            allCorporations: [],
            trackingCorporations: [],
            mailInvalidTokenAlliances: [],
            mailInvalidTokenCorporations: [],
            mailMissingCharacterCorporations: [],
        }
    },

    mounted() {
        this.loginHost = Data.envVars.backendHost;

        getTrackedCorporations(this);
        readSettings(this);
    },

    watch: {
        mailInvalidTokenAlliances() {
            this.allAlliancesChanged++;
            if (this.allAlliancesChanged > 2) {
                const newValue = Util.buildIdString(this.mailInvalidTokenAlliances);
                this.$emit('changeSetting', 'mail_invalid_token_alliances', newValue);
            }
        },

        mailInvalidTokenCorporations() {
            this.allCorporationsChanged++;
            if (this.allCorporationsChanged > 2) {
                const newValue = Util.buildIdString(this.mailInvalidTokenCorporations);
                this.$emit('changeSetting', 'mail_invalid_token_corporations', newValue);
            }
        },

        mailMissingCharacterCorporations() {
            this.trackingCorporationsChanged++;
            if (this.trackingCorporationsChanged > 2) {
                const newValue = Util.buildIdString(this.mailMissingCharacterCorporations);
                this.$emit('changeSetting', 'mail_missing_character_corporations', newValue);
            }
        },
    },

    methods: {
        removeMailChar() {
            this.settings.mail_character = '';
            this.$emit('changeSetting', 'mail_character', '');
        },

        findAlliancesOrCorporations(query, type) {
            this.isLoading = true;
            Util.findCorporationsOrAlliancesDelayed(query, type, result => {
                this.isLoading = false;
                if (type === 'Corporations') {
                    this.allCorporations = result;
                } else {
                    this.allAlliances = result;
                }
            });
        },

        sendInvalidTokenTestMail() {
            new SettingsApi().sendInvalidTokenMail((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                if (data !== '') {
                    this.h.message(data, 'error');
                } else {
                    this.h.message('"Invalid ESI Token" Mail sent.', 'success');
                }
            });
        },

        sendMissingCharacterTestMail() {
            new SettingsApi().sendMissingCharacterMail((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                if (data !== '') {
                    this.h.message(data, 'error');
                } else {
                    this.h.message('"Missing Character" Mail sent.', 'success');
                }
            });
        },
    },
}

function getTrackedCorporations(vm) {
    new CorporationApi().corporationAllTrackedCorporations((error, data) => {
        if (!error) {
            vm.trackingCorporations = data;
        }
    });
}

function readSettings(vm) {
    vm.mailInvalidTokenAlliances = [];
    vm.mailInvalidTokenCorporations = [];
    vm.mailMissingCharacterCorporations = [];

    const corporationApi = new CorporationApi();

    for (const [name, value] of Object.entries(vm.settings)) {
        if (name === 'mail_invalid_token_alliances') {
            new AllianceApi().userAllianceAlliances(value.split(','), (error, data) => {
                if (!error) {
                    vm.mailInvalidTokenAlliances = data;
                }
            });
        } else if (name === 'mail_invalid_token_corporations') {
            corporationApi.userCorporationCorporations(value.split(','), (error, data) => {
                if (!error) {
                    vm.mailInvalidTokenCorporations = data;
                }
            });
        } else if (name === 'mail_missing_character_corporations') {
            corporationApi.userCorporationCorporations(value.split(','), (error, data) => {
                if (!error) {
                    vm.mailMissingCharacterCorporations = data;
                }
            });
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
