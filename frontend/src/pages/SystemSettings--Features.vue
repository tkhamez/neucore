<template>
<div class="card border-secondary mb-3">
    <div class="card-body">
        <strong>Deactivate Groups</strong>
        <div class="custom-control custom-checkbox">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="groups_require_valid_token" name="groups_require_valid_token"
                   :checked="settings.groups_require_valid_token === '1'"
                   @change="$emit('changeSetting', 'groups_require_valid_token', $event.target.checked ? '1' : '0')">
            <label class="custom-control-label" for="groups_require_valid_token">
                Check this if the API for applications should not return groups
                for a player account if one or more of its characters have an invalid token
                (no token or tokens without any scopes count as invalid), "managed" accounts
                are excluded from this.
            </label>
        </div>
        <label class="mt-2">
            <input type="text" pattern="[0-9]*" class="form-control input-delay"
                   v-model="settings.account_deactivation_delay"
                   v-on:input="$emit('changeSettingDelayed', 'account_deactivation_delay', $event.target.value)">
            Delay the deactivation after a token became invalid (hours).
        </label>
        <div class="form-group">
            <p class="form-text mt-3 mb-0">
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
        </div>

        <hr>

        <div class="custom-control custom-checkbox">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="allow_login_managed" name="allow_login_managed"
                   :checked="settings.allow_login_managed === '1'"
                   @change="$emit('changeSetting', 'allow_login_managed', $event.target.checked ? '1' : '0')">
            <label class="custom-control-label" for="allow_login_managed">
                <strong>Allow "managed" Login:</strong>
                Enables the login URL for managed accounts that do not require ESI scopes.
            </label>
        </div>

        <hr>

        <div class="custom-control custom-checkbox">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="allow_character_deletion" name="allow_character_deletion"
                   :checked="settings.allow_character_deletion === '1'"
                   @change="$emit('changeSetting', 'allow_character_deletion', $event.target.checked ? '1' : '0')">
            <label class="custom-control-label" for="allow_character_deletion">
                <strong>Delete Characters:</strong>
                Check to allow users to delete their characters.
            </label>
        </div>
    </div>
</div>
</template>

<script>
export default {
    props: {
        settings: Object,
    },

    data () {
        return {
            allAlliances: [],
            allAlliancesLoaded: false,
            allCorporations: [],
            allCorporationsLoaded: false,
            accountDeactivationAlliances: null,
            accountDeactivationCorporations: null,
        }
    },

    mounted () {
        this.$parent.loadLists();

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

        accountDeactivationAlliances (newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.accountDeactivationAlliances);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'account_deactivation_alliances', newValue);
        },

        accountDeactivationCorporations (newValues, oldValues) {
            const newValue = this.$parent.buildIdString(newValues, oldValues, this.accountDeactivationCorporations);
            if (newValue === null) {
                return;
            }
            this.$emit('changeSetting', 'account_deactivation_corporations', newValue);
        },
    },
}

function readSettings(vm) {
    if (! vm.allAlliancesLoaded || ! vm.allCorporationsLoaded ||
        ! vm.settings.hasOwnProperty('account_deactivation_delay')
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

<style type="text/scss" scoped>
    .input-delay {
        display: inline;
        width: 100px;
    }
</style>
