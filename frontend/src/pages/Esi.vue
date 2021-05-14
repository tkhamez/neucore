<template>
    <div class="container-fluid">

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>ESI</h1>

                <div class="form-group">
                    <label for="selectedCharacter">EVE Character</label>
                    <multiselect v-model="selectedCharacter" :options="charSearchResult"
                                 id="selectedCharacter"
                                 label="character_name" track-by="character_id"
                                 placeholder="Type to search (min. 3 characters)"
                                 :searchable="true"
                                 :loading="charSearchIsLoading"
                                 :internal-search="false"
                                 :max-height="600"
                                 :show-no-results="false"
                                 @search-change="charSearch">
                    </multiselect>
                </div>

                <div class="form-group">
                    <label for="esiRoute">ESI route</label>
                    <small class="text-muted">
                        see also
                        <a href="https://esi.evetech.net/ui" target="_blank" rel="noopener noreferrer">
                            https://esi.evetech.net/ui
                        </a>,
                        only GET and POST request are implemented.
                    </small>
                    <multiselect v-model="selectedPath" :options="paths" :loading="false"
                                 label="name" track-by="path" id="esiRoute"
                                 placeholder="Select route"></multiselect>
                    <br>
                    <select class="form-control" v-model="httpMethod">
                        <option>GET</option>
                        <option>POST</option>
                    </select>
                    <input class="form-control" v-model="esiRoute">
                    <small class="form-text text-muted">
                        Placeholder: {character_id}, {corporation_id} and {alliance_id} are automatically
                        replaced with the corresponding IDs of the selected character, other placeholders
                        must be replaced manually.<br>
                        If the result contains an "X-Pages" header, you can request the other pages by
                        adding "?page=2" etc. to the route.
                    </small>
                </div>

                <div class="form-group">
                    <label for="requestBody">
                        Body (for POST requests)
                    </label>
                    <textarea v-model="requestBody" id="requestBody" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox" v-model="debug">
                            Debug (show all headers, no cache)
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"
                        :class="{ disabled: selectedCharacter === '' || esiRoute === '' }"
                        :disabled="selectedCharacter === '' || esiRoute === ''"
                        v-on:click="request()">Submit</button>

                <div v-if="status" class="alert alert-secondary mt-3">
                    Status: <code>{{ status }}</code><br>
                    <br>
                    Headers:<br>
                    <code v-for="header in headers">{{ header[0] + ': ' + header[1] }}<br></code>
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
import $ from 'jquery';
import Multiselect from '@suadelabs/vue3-multiselect';
import {ESIApi, CharacterApi} from 'neucore-js-client';

export default {
    components: {
        Multiselect,
    },

    data: function() {
        return {
            status: null,
            headers: [],
            body: '',
            charSearchIsLoading: false,
            charSearchResult: [],
            selectedCharacter: '',
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

    mounted: function() {
        window.scrollTo(0,0);

        const vm = this;
        vm.ajaxLoading(true);
        $.get(this.$root.envVars.baseUrl+'esi-paths-http-get.json').then(data => {
            vm.pathsGet = data;
            result();
        });
        $.get(this.$root.envVars.baseUrl+'esi-paths-http-post.json').then(data => {
            vm.pathsPost = data;
            result();
        });
        function result() {
            if (vm.pathsGet.length > 0 && vm.pathsPost.length > 0) {
                vm.ajaxLoading(false);
                for (const path of vm.pathsGet) {
                    vm.paths.push({ name: `GET ${path}`, path: path});
                }
                for (const path of vm.pathsPost) {
                    vm.paths.push({ name: `POST ${path}`, path: path});
                }
            }
        }
    },

    watch: {
        selectedPath: function() {
            this.esiRoute = this.selectedPath.path;
            if (this.selectedPath.name.indexOf('GET') === 0) {
                this.httpMethod = 'GET';
            } else if (this.selectedPath.name.indexOf('POST') === 0) {
                this.httpMethod = 'POST';
            }
        }
    },

    methods: {
        charSearch (query) {
            if (query.length < 3) {
                return;
            }
            this.charSearchDelayed(this, query);
        },

        charSearchDelayed: _.debounce((vm, searchTerm) => {
            vm.charSearchResult = [];
            vm.charSearchIsLoading = true;
            new CharacterApi().findCharacter(searchTerm, { currentOnly: 'true' }, function(error, data) {
                vm.charSearchIsLoading = false;
                if (error) {
                    return;
                }
                vm.charSearchResult = data;
            });
        }, 250),

        request: function() {
            if (! this.selectedCharacter || ! this.esiRoute) {
                return;
            }

            const vm = this;

            const api = new ESIApi();
            const params = {
                'character': this.selectedCharacter.character_id,
                'route': this.esiRoute,
                'debug': this.debug ? 'true' : 'false',
            };
            const callback = function(error, data, response) {
                let result;
                vm.status = response.statusCode;
                try {
                    result = JSON.parse(response.text);
                    vm.body = result.hasOwnProperty('body') ? result.body : result;
                    vm.headers = result.headers || [];
                } catch(e) {
                    vm.body = response.text;
                }
            };

            if (this.httpMethod === 'POST') {
                api.requestPost(vm.requestBody, params, callback);
            } else {
                api.request(params, callback);
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
