<template>
    <div class="container-fluid">

        <div v-cloak class="modal fade" id="tokenModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Invalid ESI token</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        The ESI token for this character is no longer valid.<br>
                        Please use the EVE login button and login with this character
                        again to create a new token.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="jumbotron mt-3">
            <span v-cloak id="preview" v-if="preview">PREVIEW</span>
            <a href="https://www.bravecollective.com/" target="_blank">
                <img src="/images/brave_300.png" class="float-right" alt="Brave logo"
                    title="Brave Collective: What's your fun per hour?">
            </a>
            <h1 class="display-3">BRAVE Core</h1>
            <p class="lead">
                This site provides access to alliance services such as Mumble, Wiki and Forum.
            </p>
            <hr class="my-4">

            <div v-cloak v-if="! this.player">
                <p class="lead">
                    Click the button below to login through <i>EVE Online SSO</i>.
                </p>
                <a :href="loginUrl">
                    <img src="/images/EVE_SSO_Login_Buttons_Large_Black.png" alt="LOG IN with EVE Online">
                </a>
                <p class="small">
                    <br>
                    Learn more about the security of <i>EVE Online SSO</i> in this
                    <a href="https://www.eveonline.com/article/eve-online-sso-and-what-you-need-to-know/"
                        target="_blank">dev-blog</a> article.
                </p>
            </div>

            <div v-cloak v-if="this.player">
                <p>Please add all your characters by logging in with EVE SSO.</p>
                <p class="lead">
                    <a :href="loginAltUrl"><img src="/images/eve_sso.png" alt="LOG IN with EVE Online"></a>
                </p>
            </div>
        </div>

        <div v-cloak v-if="this.player">
            <div class="row">
                <div class="col-lg-8">
                    <h2>Characters</h2>
                    <div class="card-columns">
                        <div v-for="char in player.characters"
                            class="card border-secondary bg-light">
                            <div class="card-header">
                                <img :src="'https://image.eveonline.com/Character/'+char.id+'_32.jpg'"
                                    alt="Character Portrait">
                                {{ char.name }}
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <span class="text-muted">Corporation:</span>
                                    <span v-if="char.corporation">
                                        {{ char.corporation.name }}
                                    </span>
                                    <br>
                                    <span class="text-muted">Alliance:</span>
                                    <span v-if="char.corporation && char.corporation.alliance">
                                        {{ char.corporation.alliance.name }}
                                    </span>
                                </p>
                            </div>
                            <div class="card-footer">
                                <span v-if="char.main" class="fas fa-star text-warning mr-2" title="Main"
                                    aria-hidden="true"></span>

                                <button v-if="! char.validToken" type="button" class="btn btn-danger btn-sm"
                                        data-toggle="modal" data-target="#tokenModal">
                                    Invalid ESI token
                                </button>

                                <button v-if="! char.main && char.validToken"
                                        type="button" class="btn btn-primary btn-sm"
                                        v-on:click="makeMain(char.id)">
                                    Make Main
                                </button>
                                <button v-if="char.validToken"
                                        type="button" class="btn btn-primary btn-sm"
                                        v-on:click="update(char.id)">
                                    <i class="fas fa-sync small"></i>
                                    Update
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="player-hdl">
                        <h2>Player</h2>
                        <span>Name: {{ player.name }}, ID: {{ player.id }}</span>
                    </div>
                    <div class="card border-secondary mb-3">
                        <h3 class="card-header">Groups</h3>
                        <ul class="list-group list-group-flush">
                            <li v-for="group in player.groups" class="list-group-item">
                                {{ group.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-if="player.managerGroups && player.managerGroups.length > 0"
                            class="card border-secondary mb-3" >
                        <h3 class="card-header">Group Manager</h3>
                        <ul class="list-group list-group-flush">
                            <li v-for="group in player.managerGroups" class="list-group-item">
                                {{ group.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-if="player.managerApps && player.managerApps.length > 0"
                            class="card border-secondary mb-3" >
                        <h3 class="card-header">App Manager</h3>
                        <ul class="list-group list-group-flush">
                            <li v-for="app in player.managerApps" class="list-group-item">
                                {{ app.name }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div> <!-- row -->
        </div> <!-- if authenticated -->
    </div> <!-- container -->
</template>

<script>
module.exports = {
    props: {
        route: Array,
        swagger: Object,
        player: [null, Object],
    },

    data: function() {
        return {
            preview: false,
            loginUrl: null,
            loginAltUrl: null,
        }
    },

    mounted: function() {
        // "preview" banner
        if (location.hostname === 'brvneucore.herokuapp.com') {
            this.preview = true;
        }

        this.getLoginUrl();
    },

    watch: {
        player: function() {
            this.getLoginUrl();
        }
    },

    methods: {
        getLoginUrl: function() {
            var vm = this;
            var api = new this.swagger.AuthApi();

            var method;
            var redirect;
            if (this.player) {
                method = 'loginAltUrl';
                redirect = '/#login-alt';
            } else {
                method = 'loginUrl';
                redirect = '/#login';
            }

            vm.loading(true);
            api[method].apply(api, [{ redirect: redirect }, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                if (method === 'loginAltUrl') {
                    vm.loginAltUrl = data;
                } else {
                    vm.loginUrl = data;
                }
            }]);
        },

        makeMain: function(characterId) {
            var vm = this;
            vm.loading(true);
            new this.swagger.PlayerApi().setMain(characterId, function(error) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.$root.$emit('playerChange');
            });
        },

        update: function(characterId) {
            var vm = this;
            vm.loading(true);
            new this.swagger.CharacterApi().update(characterId, function(error) {
                vm.loading(false);
                if (error) { // usually 403 (from Core) or 503 (ESI down)
                    if (error.message) {
                        vm.message(error.message, 'error');
                    }
                    return;
                }
                vm.message('Update done.', 'success');
                vm.$root.$emit('playerChange');
            });
        }
    }
}
</script>

<style scoped>
    .jumbotron {
        position: relative;
        min-height: 430px;
    }

    .player-hdl {
        position: relative;
    }
    .player-hdl h2 {
        display: inline-block;
        margin-right: 10px;
    }

    #preview {
        position: absolute;
        right: 20px;
        font-size: 3em;
        font-family: impact, sans-serif;
        letter-spacing: 30px;
        color: #dd3333;
        text-shadow: 1px 1px black;
        padding-left: 30px;
        transform: rotate(10deg);
        background-color: rgba(200, 200, 200, .5);
    }
</style>
