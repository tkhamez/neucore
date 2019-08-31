<template>
    <div class="container-fluid">

        <!--suppress HtmlUnknownTag -->
        <characters :swagger="swagger" ref="charactersModal"></characters>

        <div class="row mb-3">
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
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12 col-md-6">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.daysInactive">
                    Limit to members who have been <strong>inactive</strong> for x days or longer.
                </label>
            </div>
            <div class="col-sm-12 col-md-6">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.daysActive">
                    Limit to members who were <strong>active</strong> in the last x days.
                </label>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 col-md-6">
                <label class="small">
                    <select class="form-control form-control-sm input-option" v-model="formOptions.account">
                        <option></option>
                        <option value="true">with</option>
                        <option value="false">without</option>
                    </select>
                    Limit to members with/without an <strong>account</strong>
                </label>
            </div>
            <div class="col-sm-12 col-md-6">
                <label class="small">
                    <select class="form-control form-control-sm input-option" v-model="formOptions.validToken">
                        <option></option>
                        <option value="true">valid</option>
                        <option value="false">invalid</option>
                    </select>
                    Limit to characters with a valid/invalid <strong>token</strong>
                </label>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 col-md-6">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.tokenChanged">
                    Limit to characters whose ESI <strong>token status</strong> has not changed for x days
                </label>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-lg-12">
                <table class="table table-hover table-sm data-table">
                    <thead>
                        <tr>
                            <th>Character ID</th>
                            <th>Character Name</th>
                            <th>Player Account</th>
                            <th>ESI Token</th>
                            <th>Token status changed</th>
                            <th>Logon Date (GMT)</th>
                            <th>Logoff Date (GMT)</th>
                            <th>Location ID</th>
                            <th>Ship Type ID</th>
                            <th>Start Date (GMT)</th>
                        </tr>
                    </thead>
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
            currentCorporationId: null,
            formOptions: {
                daysActive: null,
                daysInactive: null,
                account: null,
                validToken: null,
                tokenChanged: null,
            },
            table: null,
        }
    },

    mounted: function() {
        const vm = this;
        vm.table = $('.data-table').DataTable({
            lengthMenu: [[50, 200, 500, 1000, -1], [50, 200, 500, 1000, "All"]],
            columns: [
                { data: 'id' },
                { data: 'name' },
                {
                    data: function (row) {
                        if (row.player) {
                            return '' +
                                '<a href="#Tracking/98169165/' + row.player.id + '">' +
                                    row.player.name + ' #' + row.player.id +
                                '</a>';
                        }
                        return '';
                    }
                }, {
                    data: function (row) {
                        if (row.character) {
                            if (row.character.validToken) return 'valid';
                            if (row.character.validToken === false) return 'invalid';
                            if (row.character.validToken === null) return 'n/a';
                        }
                        return '';
                    }
                }, {
                    data: function (row) {
                        if (row.character && row.character.validTokenTime) {
                            return vm.$root.formatDate(row.character.validTokenTime);
                        }
                        return '';
                    }
                }, {
                    data: function (row) {
                        if (row.logonDate) {
                            return vm.$root.formatDate(row.logonDate);
                        }
                        return '';
                    }
                }, {
                    data: function (row) {
                        if (row.logoffDate) {
                            return vm.$root.formatDate(row.logoffDate);
                        }
                        return '';
                    }
                },
                { data: 'locationId' },
                { data: 'shipTypeId' },
                {
                    data: function (row) {
                        if (row.startDate) {
                            return vm.$root.formatDate(row.startDate);
                        }
                        return '';
                    }
                },
            ]
        });

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
            if (this.currentCorporationId !== this.corporation.id) {
                this.currentCorporationId = this.corporation.id;
                this.getMembers();
            } else if (this.route.length === 3) {
                this.showCharacters(this.route[2]);
            }
        },

        corporation: function() {
            if (this.corporation !== '') {
                window.location.hash = '#Tracking/' + this.corporation.id;
            } else {
                window.location.hash = '#Tracking';
            }
        },

        formOptions: {
            handler() {
                this.getMembersDelayed(this);
            },
            deep: true
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

        getMembersDelayed: window._.debounce((vm) => {
            vm.getMembers();
        }, 250),

        getMembers: function() {
            this.table.clear();
            this.table.draw();
            if (! this.route[1]) {
                return;
            }

            const corporationId = parseInt(this.route[1], 10);
            const opts = {
                inactive: this.formOptions.daysInactive,
                active: this.formOptions.daysActive,
                account: this.formOptions.account,
                validToken: this.formOptions.validToken,
                tokenStatusChanged: this.formOptions.tokenChanged,
            };

            const vm = this;
            vm.loading(true);
            new this.swagger.CorporationApi().members(corporationId, opts, function(error, data) {
                vm.loading(false);
                if (error) { // 403 usually
                    return;
                }
                vm.table.rows.add(data);
                vm.table.draw();
            });
        },

        showCharacters: function(playerId) {
            this.$refs.charactersModal.showCharacters(playerId);
        },
    }
};
</script>

<style scoped>
    table {
        font-size: 90%;
    }
    .input-option {
        display: inline;
        width: 80px;
    }
</style>
