<template>
    <div class="container-fluid">

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>ESI</h1>

                <label for="selectedCharacter">EVE Character</label>
                <multiselect v-model="selectedCharacter" :options="charSearchResult"
                             id="selectedCharacter"
                             label="character_name" track-by="character_id"
                             :placeholder="messages.typeToSearch1"
                             :searchable="true"
                             :loading="charSearchIsLoading"
                             :internal-search="false"
                             :max-height="600"
                             :show-no-results="false"
                             @search-change="charSearch">
                </multiselect>
                <br>
                <label for="eveLogin">ESI Token (EVE Login Name)</label>
                <select id="eveLogin" class="form-select" v-model="selectedLoginName">
                    <option v-for="eveLogin in eveLogins" v-bind:value="eveLogin.name">{{ eveLogin.name }}</option>
                </select>

                <br>

                <label for="esiRoute">ESI route</label>
                <small class="text-muted">
                    see also
                    <a class="external" href="https://esi.evetech.net/ui" target="_blank" rel="noopener noreferrer">
                        https://esi.evetech.net/ui</a>,
                    only GET and POST request are implemented.
                </small>
                <multiselect v-model="selectedPath" :options="paths" :loading="false"
                             label="name" track-by="path" id="esiRoute"
                             placeholder="Select route"></multiselect>
                <br>
                <select class="form-select" v-model="httpMethod">
                    <option>GET</option>
                    <option>POST</option>
                </select>
                <input class="form-control" v-model="esiRoute">
                <span class="form-text">
                    Placeholder: {character_id}, {corporation_id} and {alliance_id} are automatically
                    replaced with the corresponding IDs of the selected character, other placeholders
                    must be replaced manually.<br>
                    If the result contains an "X-Pages" header, you can request the other pages by
                    adding "?page=2" etc. to the route.
                </span>

                <br>
                <br>
                <label for="requestBody">
                    Body (for POST requests)
                </label>
                <textarea v-model="requestBody" id="requestBody" class="form-control"></textarea>

                <br>
                <div class="form-check">
                    <label class="form-check-label">
                        <input class="form-check-input" type="checkbox" v-model="debug">
                        Debug (show all headers, no cache)
                    </label>
                </div>
                <br>

                <button type="submit" class="btn btn-primary"
                        :class="{ disabled: selectedCharacter === '' || esiRoute === '' }"
                        :disabled="selectedCharacter === '' || esiRoute === ''"
                        v-on:click="request()">Submit</button>

                <div v-if="status" class="alert alert-secondary mt-3">
                    Status: <code>{{ status }}</code><br>
                    <br>
                    Headers:<br>
                    <code v-for="header in headers">{{ `${header[0]}: ${header[1]}` }}<br></code>
                    <br>
                    Body:<br>
                    <pre>{{ body }}</pre>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import _ from 'lodash';
import Multiselect from '@suadelabs/vue3-multiselect';
import {ESIApi, CharacterApi, SettingsApi} from 'neucore-js-client';
import Data from "../../classes/Data";
import Helper from "../../classes/Helper";

export default {
    components: {
        Multiselect,
    },

    data() {
        return {
            h: new Helper(this),
            messages: Data.messages,
            status: null,
            headers: [],
            body: '',
            charSearchIsLoading: false,
            charSearchResult: [],
            selectedCharacter: '',
            eveLogins: [],
            selectedLoginName: '',
            paths: [],
            pathsGet: [],
            pathsPost: [],
            selectedPath: {},
            httpMethod: '',
            esiRoute: '',
            requestBody: '',
            debug: false,
        }
    },

    mounted() {
        window.scrollTo(0,0);

        getEveLogin(this);
        getPaths(this);
    },

    watch: {
        selectedPath() {
            this.esiRoute = this.selectedPath.path;
            if (this.selectedPath.name.indexOf('GET') === 0) {
                this.httpMethod = 'GET';
            } else if (this.selectedPath.name.indexOf('POST') === 0) {
                this.httpMethod = 'POST';
            }
        }
    },

    methods: {
        charSearch(query) {
            if (query.length < 3) {
                return;
            }
            this.charSearchDelayed(this, query);
        },

        charSearchDelayed: _.debounce((vm, searchTerm) => {
            vm.charSearchResult = [];
            vm.charSearchIsLoading = true;
            new CharacterApi().findCharacter(searchTerm, { currentOnly: 'true' }, (error, data) => {
                vm.charSearchIsLoading = false;
                if (error) {
                    return;
                }
                vm.charSearchResult = data;
            });
        }, 250),

        request() {
            if (!this.selectedCharacter || !this.esiRoute) {
                return;
            }

            const api = new ESIApi();
            const params = {
                'character': this.selectedCharacter.character_id,
                'login': this.selectedLoginName,
                'route': this.esiRoute,
                'debug': this.debug ? 'true' : 'false',
            };
            const callback = (error, data, response) => {
                let result;
                this.status = response.statusCode;
                try {
                    result = JSON.parse(response.text);
                    this.body = result.hasOwnProperty('body') ? result.body : result;
                    this.headers = result.headers || [];
                } catch(e) {
                    this.body = response.text;
                }
            };

            if (this.httpMethod === 'POST') {
                api.requestPost(this.requestBody, params, callback);
            } else {
                api.request(params, callback);
            }
        }
    }
}

function getEveLogin(vm) {
    new SettingsApi().userSettingsEveLoginList((error, data) => {
        if (error) {
            return;
        }
        vm.eveLogins = data;
        vm.selectedLoginName = Data.loginNames.default;
    });
}

function getPaths(vm) {
    vm.h.fetch(`${Data.envVars.baseUrl}esi-paths-http-get.json`).then(async response => {
        vm.pathsGet = await response.json();
        result();
    });
    vm.h.fetch(`${Data.envVars.baseUrl}esi-paths-http-post.json`).then(async response => {
        vm.pathsPost = await response.json();
        result();
    });
    function result() {
        if (vm.pathsGet.length > 0 && vm.pathsPost.length > 0) {
            for (const path of vm.pathsGet) {
                vm.paths.push({ name: `GET ${path}`, path: path});
            }
            for (const path of vm.pathsPost) {
                vm.paths.push({ name: `POST ${path}`, path: path});
            }
        }
    }
}
</script>

<style scoped>
    button:disabled {
        cursor: not-allowed;
    }
</style>
