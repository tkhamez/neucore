<template>
    <div class="container-fluid">

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>ESI</h1>

                <div class="form-group">
                    <label>EVE Character</label>
                    <multiselect v-model="selectedCharacter" :options="charSearchResult"
                                 label="name" track-by="id"
                                 placeholder="Type to search"
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
                        see also <a href="https://esi.evetech.net/ui" target="_blank">https://esi.evetech.net/ui</a>,
                        only GET request are implemented.
                    </small>
                    <multiselect v-model="selectedPath" :options="paths" :loading="false"
                                 placeholder="Select route"></multiselect>
                    <input class="form-control" v-model="esiRoute" placeholder="route" id="esiRoute">
                    <small class="form-text text-muted">
                        {character_id} is automatically replaced by the ID of the selected character,
                        other placeholders must be replaced manually.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary" v-on:click="request()">Submit</button>

                <div class="alert alert-secondary mt-3">
                    <pre>{{ headers }}</pre>
                    <pre>{{ body }}</pre>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
module.exports = {
    props: {
        initialized: Boolean,
        swagger: Object,
    },

    data: function() {
        return {
            headers: '',
            body: '',
            charSearchIsLoading: false,
            charSearchResult: [],
            selectedCharacter: '',
            paths: [],
            selectedPath: '',
            esiRoute: '',
        }
    },

    mounted: function() {
        window.jQuery.get('/static/esi-paths-http-get.json').then((data) => {
            this.paths = data;
        });
    },

    watch: {
        selectedPath: function() {
            this.esiRoute = this.selectedPath;
        }
    },

    methods: {
        charSearch (query) {
            if (query.length < 3) {
                return;
            }
            this.charSearchDelayed(this, query);
        },

        charSearchDelayed: window._.debounce((vm, searchTerm) => {
            vm.charSearchResult = [];
            vm.charSearchIsLoading = true;
            new vm.swagger.CharacterApi().findBy(searchTerm, function(error, data) {
                vm.charSearchIsLoading = false;
                if (error) {
                    return;
                }
                vm.charSearchResult = data;
            });
        }, 250),

        request: function() {
            if (! this.selectedCharacter) {
                return;
            }
            const vm = this;
            vm.loading(true);
            new this.swagger.ESIApi().request({
                character: this.selectedCharacter.id,
                route: this.esiRoute
            }, function(error, data, response) {
                vm.loading(false);
                let result;
                try {
                    result = JSON.parse(response.text);
                    vm.body = result.body || result;
                    vm.headers = result.headers || '';
                } catch(e) {
                    vm.body = response.text;
                }
            });
        }
    }
}
</script>

<style scoped>

</style>
