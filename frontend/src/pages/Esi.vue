<template>
    <div class="container-fluid">

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>ESI</h1>

                <div class="form-group">
                    <label for="characterId">EVE Character ID</label>
                    <input class="form-control" id="characterId" v-model="characterId">
                    <small class="form-text text-muted">Must exist in the local database.</small>
                </div>
                <div class="form-group">
                    <label for="esiRoute">ESI route</label>
                    <input class="form-control" id="esiRoute" v-model="esiRoute"
                           placeholder="/characters/{character_id}/stats/">
                    <small class="form-text text-muted">
                        See
                        <a href="https://esi.evetech.net/ui" target="_blank">
                            https://esi.evetech.net/ui
                        </a>
                        <br>
                        Only GET request are implemented at the moment.
                        <br>
                        {character_id} is the only implemented placeholder so far.
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
            headers : '',
            body : '',
            characterId : '',
            esiRoute : '',
        }
    },

    methods: {
        request: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.ESIApi().request({
                character: this.characterId,
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