<template>
<div class="card border-secondary mb-3">
    <div class="card-header"><h6>Groups Deactivation</h6></div>
    <div class="card-body">
        <div class="form-check">
            <label class="form-check-label" for="groups_require_valid_token">
                Check this if the API for applications should not return groups
                for a player account if one or more of its characters have an invalid ESI token
                (no token or tokens without any scopes count as invalid), "managed" accounts
                are excluded from this.<br>
                This also affects groups passed to Neucore plugins.
            </label>
            <input class="form-check-input" type="checkbox" value="1"
                   id="groups_require_valid_token" name="groups_require_valid_token"
                   :checked="settings.groups_require_valid_token === '1'"
                   @change="$emit('changeSetting', 'groups_require_valid_token', $event.target.checked ? '1' : '0')">
        </div>
        <label class="mt-2 display-block">
            <input type="text" pattern="[0-9]*" class="form-control input-inline" name="account_deactivation_delay"
                   v-model="settings.account_deactivation_delay"
                   v-on:input="$emit('changeSettingDelayed', 'account_deactivation_delay', $event.target.value)">
            Delay the deactivation after a token became invalid (hours).
        </label>
        <p class="mt-3 mb-0">
            Groups are only deactivated if at least one character in a player account
            belongs to one of the following alliances or corporations:
        </p>
        <label class="col-form-label">Alliances</label>
        <multiselect v-model="accountDeactivationAlliances" :options="allAlliances"
                     label="name" track-by="id" :multiple="true"
                     :loading="false" :searchable="true"
                     placeholder="Select alliances">
        </multiselect>
        <label class="col-form-label">Corporations</label>
        <multiselect v-model="accountDeactivationCorporations" :options="allCorporations"
                     label="name" track-by="id" :multiple="true"
                     :loading="false" :searchable="true"
                     placeholder="Select corporations">
        </multiselect>
        <label class="mt-4 display-block">
            <input type="text" pattern="[0-9]*" class="form-control input-inline"
                   name="account_deactivation_active_days"
                   v-model="settings.account_deactivation_active_days"
                   v-on:input="$emit('changeSettingDelayed', 'account_deactivation_active_days', $event.target.value)">
            Number of days for the "check-tokens" command.
        </label>
        <span class="form-text">
            If a character has been added to an account in the last x days, its ESI token is checked by
            the "check-tokens" command with the "characters = active" option.
        </span>
    </div>

    <div class="card-header mt-3"><h6>API Rate Limit for Apps</h6></div>
    <div class="card-body">
        <p>
            If enabled, each response contains the headers "X-Neucore-Rate-Limit-Remain" and
            "X-Neucore-Rate-Limit-Reset". If the limit is exceeded, this results in a 429 "Too many requests"
            response. If it is only configured but not active, it will only log when an app exceeds the limit.
        </p>
        <label class="mt-2 display-block">
            <input type="text" pattern="[0-9]*" class="form-control input-inline" name="rate_limit_app_max_requests"
                   v-model="settings.rate_limit_app_max_requests"
                   v-on:input="$emit('changeSettingDelayed', 'rate_limit_app_max_requests', $event.target.value)">
            Maximum requests.
        </label>
        <label class="mt-2 display-block">
            <input type="text" pattern="[0-9]*" class="form-control input-inline" name="rate_limit_app_reset_time"
                   v-model="settings.rate_limit_app_reset_time"
                   v-on:input="$emit('changeSettingDelayed', 'rate_limit_app_reset_time', $event.target.value)">
            Reset time in seconds.
        </label>
        <div class="form-check">
            <label class="form-check-label" for="rate_limit_app_active">Active.</label>
            <input class="form-check-input" type="checkbox" value="1"
                   id="rate_limit_app_active" name="rate_limit_app_active"
                   :checked="settings.rate_limit_app_active === '1'"
                   @change="$emit('changeSetting', 'rate_limit_app_active', $event.target.checked ? '1' : '0')">
        </div>
    </div>

    <div class="card-header mt-3"><h6>Miscellaneous</h6></div>
    <div class="card-body">
        <p>"Managed" Logins</p>
        <div class="form-check">
            <label class="form-check-label" for="allow_login_managed">
                Enables the login URL for managed accounts that do not require ESI scopes.
            </label>
            <input class="form-check-input" type="checkbox" value="1"
                   id="allow_login_managed" name="allow_login_managed"
                   :checked="settings.allow_login_managed === '1'"
                   @change="$emit('changeSetting', 'allow_login_managed', $event.target.checked ? '1' : '0')">
        </div>

        <hr>
        <p>Character Deletion</p>
        <div class="form-check">
            <label class="form-check-label" for="allow_character_deletion">
                Check to allow users to delete their characters.
            </label>
            <input class="form-check-input" type="checkbox" value="1"
                   id="allow_character_deletion" name="allow_character_deletion"
                   :checked="settings.allow_character_deletion === '1'"
                   @change="$emit('changeSetting', 'allow_character_deletion', $event.target.checked ? '1' : '0')">
        </div>

        <hr>
        <p>Structure Name Updates</p>
        <p>
            This is to reduce 403 errors from ESI. No API request is made to update the name for a structure
            if this has previously failed multiple times for a specified number of days.
        </p>
        <label class="mt-2 display-block">
            <input type="text" pattern="[0-9]*" class="form-control" name="fetch_structure_name_error_days"
                   v-model="settings.fetch_structure_name_error_days"
                   v-on:input="$emit('changeSettingDelayed', 'fetch_structure_name_error_days', $event.target.value)">
            <span class="form-text">
                Example value "3=7,10=30":<br>
                Skip if the update has failed 3 times or more and the last update was less than 7 days ago,<br>
                also skip if the update has failed 10 times or more and the last update was less than 30 days ago.
            </span>
        </label>
    </div>

</div>
</template>

<script>
import Multiselect from '@suadelabs/vue3-multiselect';

export default {
    components: {
        Multiselect,
    },

    inject: ['store'],

    props: {
        allAlliances: Array,
        allCorporations: Array,
    },

    data() {
        return {
            settings: { ...this.store.state.settings },
            allAlliancesLoaded: false,
            allCorporationsLoaded: false,
            accountDeactivationAlliances: null,
            accountDeactivationCorporations: null,
        }
    },

    mounted() {
        this.$parent.loadLists();
    },

    watch: {
        settings() {
            readSettings(this);
        },

        allAlliances() {
            this.allAlliancesLoaded = true;
            readSettings(this);
        },

        allCorporations() {
            this.allCorporationsLoaded = true;
            readSettings(this);
        },

        accountDeactivationAlliances(newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.accountDeactivationAlliances);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'account_deactivation_alliances', newValue);
        },

        accountDeactivationCorporations(newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.accountDeactivationCorporations);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'account_deactivation_corporations', newValue);
        },
    },
}

function readSettings(vm) {
    if (
        !vm.allAlliancesLoaded ||
        !vm.allCorporationsLoaded ||
        !vm.settings.hasOwnProperty('account_deactivation_delay')
    ) {
        return; // wait for alliance and corporation list and settings
    }

    vm.accountDeactivationAlliances = [];
    vm.accountDeactivationCorporations = [];

    for (const [name, value] of Object.entries(vm.settings)) {
        if (name === 'account_deactivation_alliances') {
            vm.accountDeactivationAlliances = vm.$parent.buildIdArray(value, vm.allAlliances);
        }
        if (name === 'account_deactivation_corporations') {
            vm.accountDeactivationCorporations = vm.$parent.buildIdArray(value, vm.allCorporations);
        }
    }
}
</script>

<style scoped>
    .input-inline {
        display: inline;
        width: 100px;
    }
    .display-block {
        display: block;
    }
</style>
