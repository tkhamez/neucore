<!--
Modal window with all characters of one player.
 -->

<template>
<div v-cloak v-if="selectedPlayer" class="modal fade" id="playerModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ selectedPlayer.name }}
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <ul class="list-group">
                <li v-for="character in selectedPlayer.characters" class="list-group-item">
                    <div class="row">
                        <div class="col-6">
                            <img :src="'https://image.eveonline.com/Character/' + character.id + '_32.jpg'"
                                 alt="character">
                            {{ character.name }}
                            <span v-if="character.main" class="fas fa-star text-warning" title="Main"></span>
                        </div>
                        <div class="col-6 text-right">
                            <span v-if="character.validToken" class="badge badge-success ml-1">Valid token</span>
                            <span v-if="character.validToken === false" class="badge badge-danger ml-1">
                                Invalid token
                            </span>
                            <span v-if="character.validToken === null" class="badge badge-info ml-1">No token</span>
                            <a class="badge badge-secondary ml-1"
                               :href="'https://evewho.com/character/' + character.id"
                               target="_blank" rel="noopener noreferrer">Eve Who</a>
                        </div>
                    </div>
                    <div class="small row">
                        <span class="text-muted col-2">Corporation:</span>
                        <span class="col-10" v-if="character.corporation">
                            [{{ character.corporation.ticker }}]
                            {{ character.corporation.name }}
                        </span>
                    </div>
                    <div class="small row">
                        <span class="text-muted col-2">Alliance:</span>
                        <span class="col-10" v-if="character.corporation && character.corporation.alliance">
                            [{{ character.corporation.alliance.ticker }}]
                            {{ character.corporation.alliance.name }}
                        </span>
                    </div>
                </li>
            </ul>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</template>

<script>
module.exports = {
    props: {
        swagger: Object,
    },

    data: function() {
        return {
            selectedPlayer: null,
        }
    },

    methods: {
        showCharacters: function(playerId) {
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().characters(playerId, function(error, data) {
                vm.loading(false);
                if (error) {
                    return;
                }
                window.setTimeout(function() {
                    window.$('#playerModal').modal('show');
                }, 10);
            });
        },
    }
}
</script>

<style scoped>
</style>
