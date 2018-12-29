<template>
    <div class="container-fluid">

        <characters :swagger="swagger" ref="charactersModal"></characters>

        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Member Tracking</h1>

                <div class="input-group">
                    <label class="input-group-prepend" for="corporation-select">
                        <span class="input-group-text">Select corporation</span>
                    </label>
                    <select class="custom-select" v-model="corporation" id="corporation-select">
                        <option value=""></option>
                        <option v-for="option in corporations" v-bind:value="option">
                            [{{ option.ticker }}] {{ option.name }}
                        </option>
                    </select>
                </div>
                <label class="mt-2">
                    <input type="text" pattern="[0-9]*" class="form-control input-days"
                           v-model="daysInactive">
                    Limit to members who have been inactive for days x or longer.
                </label>
                <br>
                <label class="mt-2">
                    <input type="text" pattern="[0-9]*" class="form-control input-days"
                           v-model="daysActive">
                    Limit to members who were active in the last x days.
                </label>

                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Character ID</th>
                            <th>Character Name</th>
                            <th>Player Account</th>
                            <th>ESI Token</th>
                            <th>Logon Date (UTC)</th>
                            <th>Logoff Date (UTC)</th>
                            <th>Location ID</th>
                            <th>Ship Type ID</th>
                            <th>Start Date (UTC)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in members">
                            <td>{{ member.id }}</td>
                            <td>{{ member.name }}</td>
                            <td>
                                <a href="#" v-if="member.player" v-on:click.prevent="showCharacters(member.player.id)">
                                    {{ member.player.name }}
                                </a>
                            </td>
                            <td>
                                <span v-if="member.character">
                                    <span v-if="member.character.validToken">valid</span>
                                    <span v-if="member.character.validToken === false">invalid</span>
                                    <span v-if="member.character.validToken === null">n/a</span>
                                </span>
                            </td>
                            <td>
                                <span v-if="member.logonDate">
                                    {{ $root.formatDate(member.logonDate) }}
                                </span>
                            </td>
                            <td>
                                <span v-if="member.logoffDate">
                                    {{ $root.formatDate(member.logoffDate) }}
                                </span>
                            </td>
                            <td>{{ member.locationId }}</td>
                            <td>{{ member.shipTypeId }}</td>
                            <td>
                                <span v-if="member.startDate">
                                    {{ $root.formatDate(member.startDate) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>
        </div>

    </div>
</template>

<script>
import Characters from '../components/Characters.vue';

module.exports = {
    components: {
        Characters,
    },

    props: {
        route: Array,
        initialized: Boolean,
        swagger: Object,
    },

    data: function() {
        return {
            corporation: "", // empty string to select the first entry in the drop-down
            corporations: [],
            members: [],
            daysActive: null,
            daysInactive: null,
        }
    },

    mounted: function() {
        if (this.initialized) { // on page change
            this.getCorporations();
            this.getMembers();
        }
    },

    watch: {
        initialized: function() { // on refresh
            this.getCorporations();
            this.getMembers();
        },

        route: function() {
            this.getMembers();
        },

        corporation: function() {
            if (this.corporation !== '') {
                window.location.hash = '#Tracking/' + this.corporation.id;
            } else {
                window.location.hash = '#Tracking';
            }
        },

        daysActive: function() {
            this.getMembersDelayed(this);
        },

        daysInactive: function() {
            this.getMembersDelayed(this);
        }
    },

    methods: {

        getCorporations: function() {
            const vm = this;
            vm.loading(true);
            new this.swagger.CorporationApi().trackedCorporations(function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.corporations = data;
                vm.setCorporation(); // select correct value in drop down after page reload
            });
        },

        setCorporation: function() {
            if (! this.route[1]) {
                return;
            }
            const corporationId = parseInt(this.route[1], 10);
            for (let corporation of this.corporations) {
                if (corporation.id === corporationId) {
                    this.corporation = corporation;
                    break;
                }
            }
        },

        getMembersDelayed: _.debounce((vm) => {
            vm.getMembers();
        }, 250),

        getMembers: function() {
            this.members = [];
            if (! this.route[1]) {
                return;
            }

            const corporationId = parseInt(this.route[1], 10);
            const opts = {
                inactive: this.daysInactive,
                active: this.daysActive
            };

            const vm = this;
            vm.loading(true);
            new this.swagger.CorporationApi().members(corporationId, opts, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.members = data;
            });
        },

        showCharacters: function(playerId) {
            this.$refs.charactersModal.showCharacters(playerId);
        },
    }
}
</script>

<style scoped>
    table {
        font-size: 90%;
    }
    .input-days {
        display: inline;
        width: 70px;
    }
</style>
