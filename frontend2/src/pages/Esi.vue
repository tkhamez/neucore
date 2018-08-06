<template>
    <div class="container-fluid">

        <div class="row">
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
                        {character_id} is the only implemented parameter so far.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary" v-on:click="request()">Submit</button>

                <div class="alert alert-dismissible alert-secondary mt-3">
                    <pre>{{ result }}</pre>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
module.exports = {
    props: {
        initialized: Boolean,
    },

    data: function() {
        return {
            result : '',
            characterId : '',
            esiRoute : '',
        }
    },

    methods: {
        request: function() {
            const vm = this;
            const $ = window.jQuery;
            const url = '/api/user/esi/request?route='+this.esiRoute+'&character='+this.characterId;
            $.get(url, function(result) {
                vm.result = result;
            });
        }
    }
}
</script>

<style scoped>

</style>
