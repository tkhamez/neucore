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

    <div class="card-header">Notification for deactivated accounts</div>
    <div class="card-body">
        <p>
            This EVE mail is sent when an account has been deactivated
            because one of its characters contains an invalid or no ESI token.<br>
            The mail is only sent if that feature is activated above.
        </p>

        <button class="btn btn-success btn-sm" v-on:click="sendMailAccountDisabledTestMail()">Send test mail</button>
        <small>Mail will be send to the logged-in user.</small>

        <div class="custom-control custom-checkbox mb-2 mt-3">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="mail_account_disabled_active" name="mail_account_disabled_active"
                   :checked="settings.mail_account_disabled_active === '1'"
                   @change="$emit('changeSetting', 'mail_account_disabled_active', $event.target.checked ? '1' : '0')">
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
                   v-on:input="$emit('changeSettingDelayed', 'mail_account_disabled_subject', $event.target.value)">
        </div>
        <div class="form-group">
            <label for="mailAccountDisabledBody">Message</label>
            <textarea v-model="settings.mail_account_disabled_body" class="form-control"
                      v-on:input="$emit('changeSettingDelayed', 'mail_account_disabled_body', $event.target.value)"
                      id="mailAccountDisabledBody" rows="6"></textarea>
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
            mailAccountDisabledAlliances: null,
        }
    },

    mounted () {
        init(this);
    },

    watch: {
        settings () {
            readSettings(this);
        },

        mailAccountDisabledAlliances (newValues, oldValues) {
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
            this.$emit('changeSetting', 'mail_account_disabled_alliances', newAllianceIds.join(','));
        },
    },

    methods: {
        removeMailChar () {
            this.settings.mail_character = '';
            this.$emit('changeSetting', 'mail_character', '');
        },

        sendMailAccountDisabledTestMail () {
            const vm = this;
            new SettingsApi().sendAccountDisabledMail((error, data) => {
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

    vm.mailAccountDisabledAlliances = [];

    for (const [name, value] of Object.entries(vm.settings)) {
        if (name !== 'mail_account_disabled_alliances') {
            continue;
        }
        for (let allianceId of value.split(',')) {
            allianceId = parseInt(allianceId);
            for (let alliance of vm.alliances) {
                if (alliance.id === allianceId) {
                    vm.mailAccountDisabledAlliances.push(alliance);
                    break;
                }
            }
        }
    }
}
</script>
