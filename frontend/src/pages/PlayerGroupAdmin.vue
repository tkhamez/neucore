<template>
    <div class="container-fluid">

        <characters :swagger="swagger" ref="charactersModal"></characters>

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Player Groups Admin</h1>
                <p class="mb-0">Login URL: <a :href="loginUrlManaged">{{ loginUrlManaged }}</a></p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="card border-secondary mb-3" >
                    <h3 class="card-header">
                        Players
                        <span class="hdl-small">status = managed</span>
                    </h3>
                    <div class="list-group">
                        <span v-for="player in players">
                            <a class="list-group-item list-group-item-action"
                               :class="{ active: playerId === player.id }"
                               :href="'#PlayerGroupAdmin/' + player.id">
                                {{ player.name }} #{{ player.id }}
                            </a>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-secondary" >
                    <h3 class="card-header">
                        Groups
                    </h3>
                    <div v-if="playerData" class="card-body">
                        <span class="text-muted">
                            <a :href="'#UserAdmin/' + playerData.id">{{ playerData.name }} #{{ player.id }}</a>,
                            status: {{ playerData.status }}
                        </span>
                        <a class="badge badge-info ml-1" href="" v-on:click.prevent="showCharacters(playerData.id)">
                            Show characters
                        </a>
                        <span v-if="playerData.status === 'standard'" class="text-warning">
                            <br>
                            The status of this player is not "managed", manual changes can
                            be overwritten by the automatic group assignment.
                        </span>
                    </div>
                </div>

                <admin v-cloak v-if="playerId" ref="admin"
                       :player="player" :contentType="'groups'" :typeId="playerId"
                       :swagger="swagger" :type="'Player'"
                       v-on:activePlayer="playerData = $event"></admin>

            </div>
        </div>
    </div>
</template>

<script>
import Admin      from '../components/GroupAppPlayerAdmin.vue';
import Characters from '../components/Characters.vue';

module.exports = {
    components: {
        Admin,
        Characters,
    },

    props: {
        route: Array,
        swagger: Object,
        initialized: Boolean,
        player: [null, Object],
    },

    data: function() {
        return {
            players: [],
            playerId: null, // current player
            playerData: null, // current player
            loginUrlManaged: null,
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.getPLayers();
            this.setPlayerId();
        }

        // login URL for managed accounts
        let port = '';
        if (location.port !== "" && location.port !== 80 && location.port !== 443) {
            port = ':' + location.port;
        }
        this.loginUrlManaged = location.protocol + "//" + location.hostname + port + "/login-managed"
    },

    watch: {
        initialized: function() { // on refresh
            this.getPLayers();
            this.setPlayerId();
        },

        route: function() {
            this.setPlayerId();
        },
    },

    methods: {
        getPLayers: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().withStatus('managed', function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.players = data;
            });
        },

        setPlayerId: function() {
            this.playerId = this.route[1] ? parseInt(this.route[1], 10) : null;
        },

        showCharacters: function(memberId) {
            this.$refs.charactersModal.showCharacters(memberId);
        },
    },
}
</script>

<style scoped>
.hdl-small {
    font-size: 1rem;
}
</style>
