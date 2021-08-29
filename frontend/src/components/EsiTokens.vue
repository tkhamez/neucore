<template>
<div v-cloak class="modal fade" id="esiTokensModal">
    <div class="modal-dialog" :class="{ 'modal-lg': page === 'UserAdmin'}">
        <div v-cloak v-if="character && eveLogins" class="modal-content">
            <div class="modal-header">
                {{ character.name }} - ESI Tokens
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div v-if="showInvalid">
                    <h5 class="modal-title">Invalid ESI token</h5>
                    <p>
                        The default ESI token for this character is no longer valid.<br>
                        Please use the EVE login button and login with "{{ character.name }}"
                        again to create a new token.
                    </p>
                    <p class="align-center">
                        <!--suppress JSUnresolvedVariable -->
                        <a :href="loginHost + '/login/' + loginNames.alt">
                            <img src="../assets/eve_sso.png" alt="LOG IN with EVE Online">
                        </a>
                    </p>
                </div>

                <table class="table">
                    <tr>
                        <td>Name</td>
                        <td>Status</td>
                        <td v-if="page === 'UserAdmin'">Status changed*</td>
                        <td>Has required in-game roles</td>
                    </tr>
                    <tr v-for="esiToken in character.esiTokens">
                        <td>
                            <a v-if="page === 'UserAdmin' && hasRole('settings')" href="#"
                               v-on:click.prevent="showEveLogin(esiToken.eveLoginId)">
                                {{ loginName(esiToken.eveLoginId) }}
                            </a>
                            <span v-else>{{ loginName(esiToken.eveLoginId) }}</span>
                        </td>
                        <td>
                            <span v-if="esiToken.validToken">valid</span>
                            <span v-if="esiToken.validToken === false">invalid</span>
                            <span v-if="esiToken.validToken === null">n/a</span>
                        </td>
                        <td v-if="page === 'UserAdmin'">
                            <span v-if="esiToken.validTokenTime">
                                {{ formatDate(esiToken.validTokenTime) }}
                            </span>
                        </td>
                        <td>
                            <span v-if="esiToken.hasRoles">Yes</span>
                            <span v-if="esiToken.hasRoles === false">No</span>
                            <span v-if="esiToken.hasRoles === null">n/a</span>
                        </td>
                    </tr>
                </table>
                <p v-if="page === 'UserAdmin'" class="small text-muted">* Time is GMT</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import $ from 'jquery';

export default {
    props: {
        page: String,
        eveLogins: Array,
    },

    data () {
        return {
            character: null,
            showInvalid: false,
            loginHost: '',
        }
    },

    mounted () {
        this.loginHost = this.$root.envVars.backendHost;
    },

    methods: {
        /**
         * @param character
         * @param {bool} [showInvalid]
         */
        showModal (character, showInvalid) {
            this.showInvalid = !!showInvalid;
            this.character = character;
            $('#esiTokensModal').modal('show');
        },

        showEveLogin (id) {
            $('#esiTokensModal').modal('hide');
            window.location.hash = `#SystemSettings/EveLogins/${id}`;
        },

        loginName (loginId) {
            for (const login of this.eveLogins) {
                if (login.id === loginId) {
                    return login.name;
                }
            }
            return `[${loginId}]`;
        },
    },
}
</script>
