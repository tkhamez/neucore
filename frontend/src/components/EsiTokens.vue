<template>
<div v-cloak class="modal fade" id="esiTokensModal">
    <div class="modal-dialog modal-lg">
        <div v-cloak v-if="character && eveLogins" class="modal-content">
            <div class="modal-header">
                {{ character.name }} - ESI Tokens
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <a :href="`${loginHost}/login/${loginNames.default}`">
                            <img src="../../public/img/eve_sso.png" alt="LOG IN with EVE Online">
                        </a>
                    </p>
                </div>

                <table class="table" aria-describedby="ESI tokens">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th v-if="page === 'UserAdmin'">Status changed*</th>
                            <th>Last check*</th>
                            <th>Has required in-game roles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="esiToken in character.esiTokens">
                            <td>
                                <a v-if="page === 'UserAdmin' && h.hasRole('settings')" href="#"
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
                                    {{ U.formatDate(esiToken.validTokenTime) }}
                                </span>
                            </td>
                            <td>
                                <span v-if="esiToken.lastChecked">
                                    {{ U.formatDate(esiToken.lastChecked) }}
                                </span>
                            </td>
                            <td>
                                <span v-if="esiToken.hasRoles">Yes</span>
                                <span v-if="esiToken.hasRoles === false">No</span>
                                <span v-if="esiToken.hasRoles === null">n/a</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="small text-muted">* Time is GMT</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import {Modal} from "bootstrap";
import Data from "../classes/Data";
import Util from "../classes/Util";
import Helper from "../classes/Helper";

export default {
    props: {
        page: String,
        eveLogins: Array,
    },

    data() {
        return {
            U: Util,
            h: new Helper(this),
            loginNames: Data.loginNames,
            character: null,
            showInvalid: false,
            loginHost: '',
            esiTokensModal: null,
        }
    },

    mounted() {
        this.loginHost = Data.envVars.backendHost;
    },

    methods: {
        /**
         * @param character
         * @param {bool} [showInvalid]
         */
        showModal(character, showInvalid) {
            this.showInvalid = !!showInvalid;
            this.character = character;
            this.esiTokensModal = new Modal('#esiTokensModal');
            this.esiTokensModal.show();
        },

        showEveLogin(id) {
            if (this.esiTokensModal) {
                this.esiTokensModal.hide();
            }
            window.location.hash = `#SystemSettings/EVELoginAdmin/${id}`;
        },

        loginName(loginId) {
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
