<template>
<div class="card border-secondary mb-3">
    <div class="card-body">
        <em>Deactivate Accounts:</em>
        <div class="custom-control custom-checkbox">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="groups_require_valid_token" name="groups_require_valid_token"
                   :checked="settings.groups_require_valid_token === '1'"
                   @change="$emit('changeSetting', 'groups_require_valid_token', $event.target.checked ? '1' : '0')"
            >
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
        <hr>
        <div class="custom-control custom-checkbox">
            <input class="custom-control-input" type="checkbox" value="1"
                   id="allow_login_managed" name="allow_login_managed"
                   :checked="settings.allow_login_managed === '1'"
                   @change="$emit('changeSetting', 'allow_login_managed', $event.target.checked ? '1' : '0')"
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
                   @change="$emit('changeSetting', 'allow_character_deletion', $event.target.checked ? '1' : '0')"
            >
            <label class="custom-control-label" for="allow_character_deletion">
                <em>Delete characters:</em>
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
}
</script>

<style type="text/scss" scoped>
.input-delay {
    display: inline;
    width: 100px;
}
</style>
