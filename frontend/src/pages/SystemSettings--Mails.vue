<template>
<div class="card border-secondary mb-3">
    <div class="card-header">Sender</div>
    <div class="card-body">
        <span v-if="settings.mail_character === ''">
            <a href="/login-mail"><img src="/static/eve_sso.png" alt="LOG IN with EVE Online"></a>
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

        <button class="btn btn-success btn-sm" v-on:click="sendMailInvalidTokenTestMail()">Send test mail</button>
        <small>Mail will be send to the logged-in user.</small>

        <div class="custom-control custom-checkbox mb-2 mt-3">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="mail_invalid_token_active" name="mail_invalid_token_active"
                   :checked="settings.mail_invalid_token_active === '1'"
                   @change="$emit('changeSetting', 'mail_invalid_token_active', $event.target.checked ? '1' : '0')">
            <label class="custom-control-label" for="mail_invalid_token_active">Activate mail</label>
        </div>
        <div class="form-group">
            <label class="col-form-label">Alliances</label>
            <multiselect v-model="mailInvalidTokenAlliances" :options="alliances"
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
</div>
</template>

<script>
import { AllianceApi } from 'neucore-js-client';
import { SettingsApi } from 'neucore-js-client';

export default {
    props: {
        settings: Object,
    },

    data () {
        return {
            alliances: [],
            alliancesLoaded: false,
            mailInvalidTokenAlliances: null,
        }
    },

    mounted () {
        init(this);
    },

    watch: {
        settings () {
            readSettings(this);
        },

        mailInvalidTokenAlliances (newValues, oldValues) {
            if (oldValues === null) {
                return;
            }
            const oldAllianceIds = [];
            for (let oldValue of oldValues) {
                oldAllianceIds.push(oldValue.id);
            }
            const newAllianceIds = [];
            for (let alliance of this.mailInvalidTokenAlliances) {
                newAllianceIds.push(alliance.id);
            }
            if (newAllianceIds.join(',') === oldAllianceIds.join(',')) {
                return;
            }
            this.$emit('changeSetting', 'mail_invalid_token_alliances', newAllianceIds.join(','));
        },
    },

    methods: {
        removeMailChar () {
            this.settings.mail_character = '';
            this.$emit('changeSetting', 'mail_character', '');
        },

        sendMailInvalidTokenTestMail () {
            const vm = this;
            new SettingsApi().sendInvalidTokenMail((error, data) => {
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

function init(vm) {
    // get alliances
    new AllianceApi().all((error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.alliances = data;
        vm.alliancesLoaded = true;
        readSettings(vm);
    });
}

function readSettings(vm) {
    if (! vm.alliancesLoaded || ! vm.settings.hasOwnProperty('account_deactivation_delay')) {
        return; // wait for alliance list and settings
    }

    vm.mailInvalidTokenAlliances = [];

    for (const [name, value] of Object.entries(vm.settings)) {
        if (name !== 'mail_invalid_token_alliances') {
            continue;
        }
        for (let allianceId of value.split(',')) {
            allianceId = parseInt(allianceId);
            for (let alliance of vm.alliances) {
                if (alliance.id === allianceId) {
                    vm.mailInvalidTokenAlliances.push(alliance);
                    break;
                }
            }
        }
    }
}
</script>
