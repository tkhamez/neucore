<template>
    <div class="container-fluid">

        <characters ref="charactersModal"></characters>

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
                            {{ option.name }} [{{ option.ticker }}]
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
            <div class="col-lg-12 text-right">
                <div class="dropdown">
                    <span class="small">Search in:</span>
                    <button title="Columns" class="btn btn-secondary btn-sm dropdown-toggle"
                            type="button" data-toggle="dropdown" aria-label="Columns" aria-expanded="false">
                        <span role="img" class="fa fa-th-list"></span>
                        <span class="caret"></span>
                    </button>
                    <div class="dropdown-menu" v-on:click.stop="">
                        <span class="dropdown-item small">Search in:</span>
                        <div class="dropdown-item form-check small" v-for="(column, idx) in columns"
                             v-on:click="toggleSearchableColumn(idx)">
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <input class="form-check-input" type="checkbox" v-model="column.searchable">
                            <label class="form-check-label">{{ column.name }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-12">
                <table class="table table-hover table-sm member-table" aria-describedby="Corporation members">
                    <thead class="thead-dark">
                        <tr>
                            <th scope="col">Character</th>
                            <th scope="col">Account</th>
                            <th scope="col" title="ESI token status">ESI</th>
                            <th scope="col">Logon</th>
                            <th scope="col">Logoff</th>
                            <th scope="col">Location (System, Structure)</th>
                            <th scope="col">Ship</th>
                            <th scope="col">Joined</th>
                        </tr>
                    </thead>
                </table>
                <p class="small text-muted">
                    Last update:
                    <span v-if="corporation.trackingLastUpdate">{{ formatDate(corporation.trackingLastUpdate) }}</span>
                    <br>
                    All dates and times are in GMT.
                </p>
            </div>
        </div>

    </div>
</template>

<script>
import _ from 'lodash';
import $ from 'jquery';
import {CorporationApi} from 'neucore-js-client';

import Characters from '../components/Characters.vue';

export default {
    components: {
        Characters,
    },

    props: {
        route: Array,
    },

    data () {
        return {
            corporation: "", // empty string to select the first entry in the drop-down
            corporations: [],
            formOptions: {
                daysActive: null,
                daysInactive: null,
                account: null,
                validToken: null,
                tokenChanged: null,
            },
            table: null,
            columns: [
                { name: 'Character', searchable: true },
                { name: 'Account', searchable: true },
                { name: 'ESI', searchable: true },
                { name: 'Logon', searchable: true },
                { name: 'Logoff', searchable: true },
                { name: 'Location', searchable: true },
                { name: 'Ship', searchable: true },
                { name: 'Joined', searchable: true },
            ],
        }
    },

    mounted () {
        window.scrollTo(0,0);

        configureDataTable(this);

        this.getCorporations();
        this.getMembers();
    },

    watch: {
        route () {
            if (this.route.length < 3) {
                this.getMembers();
            } else if (this.route.length === 3 && this.route[2] !== '0') {
                this.showCharacters(this.route[2]);
                window.location.hash = `#Tracking/${this.corporation.id}/0`;
            }
        },

        corporation () {
            if (this.corporation !== '') {
                window.location.hash = `#Tracking/${this.corporation.id}`;
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
        getCorporations () {
            const vm = this;
            new CorporationApi().trackedCorporations((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                vm.corporations = data;
                vm.setCorporation(); // select correct value in drop down after page reload
            });
        },

        setCorporation () {
            if (! this.route[1]) {
                return;
            }
            const corporationId = parseInt(this.route[1], 10);
            for (const corporation of this.corporations) {
                if (corporation.id === corporationId) {
                    this.corporation = corporation;
                    break;
                }
            }
        },

        getMembersDelayed: _.debounce((vm) => {
            vm.getMembers();
        }, 250),

        getMembers () {
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
            new CorporationApi().members(corporationId, opts, (error, data, response) => {
                if (error) {
                    if (response.statusCode === 403) {
                        vm.message(error, 'warning', 2000);
                    }
                    return;
                }
                vm.table.clear(); // it can happen that two of these requests run in parallel
                vm.table.rows.add(data);
                vm.table.draw();
            });
        },

        showCharacters (playerId) {
            this.$refs.charactersModal.showCharacters(playerId);
        },

        toggleSearchableColumn (index) {
            this.columns[index].searchable = ! this.columns[index].searchable;
            this.table.draw();
        },
    }
};

function configureDataTable(vm) {
    $.fn.dataTable.ext.search.push(
        (settings, searchData) => {
            const term = $('.dataTables_filter input').val().toLowerCase();
            for (let index = 0; index < vm.columns.length; index++) {
                if (! vm.columns[index].searchable) {
                    continue;
                }
                if (searchData[index].toLowerCase().indexOf(term) !== -1) {
                    return true;
                }
            }
            return false;
        }
    );

    vm.table = $('.member-table').DataTable({
        lengthMenu: [
            [10, 25, 50, 100, 200, 500, 1000, 5000, -1],
            [10, 25, 50, 100, 200, 500, 1000, 5000, "All"]
        ],
        pageLength: 10,
        deferRender: true,
        order: [[4, "desc"]],
        'drawCallback': function() {
            $('[data-toggle="tooltip"]').tooltip();
        },
        columns: [{
            data (row) {
                return `
                    <a href="https://evewho.com/character/${row.id}"
                        target="_blank" rel="noopener noreferrer" title="Eve Who">
                        ${(row.name ? row.name : row.id)}
                    </a>`;
            }
        }, {
            data (row) {
                if (row.player) {
                    return `
                        <a href="#Tracking/${vm.corporation.id}/${row.player.id}">
                            ${row.player.name} #${row.player.id}
                        </a>`;
                } else if (row.missingCharacterMailSent) {
                    return `
                        <div data-toggle="tooltip" data-html="true"
                              title="Mail sent: ${vm.$root.formatDate(row.missingCharacterMailSent)}">
                            n/a
                        </div>`;
                } else {
                    return '';
                }
            }
        }, {
            data (row) {
                if (! row.character) {
                    return '';
                }
                let text = '';
                if (row.character.validToken) {
                    text = 'valid';
                } else if (row.character.validToken === false) {
                    text = 'invalid';
                } else {
                    text = 'n/a';
                }
                if (row.character.validTokenTime) {
                    return `
                        <div data-toggle="tooltip" data-html="true"
                              title="Token status change date: ${vm.$root.formatDate(row.character.validTokenTime)}">
                            ${text}
                        </div>`;
                } else {
                    return text;
                }
            }
        }, {
            data (row) {
                return row.logonDate ? vm.$root.formatDate(row.logonDate) : '';
            }
        }, {
            data (row) {
                return row.logoffDate ? vm.$root.formatDate(row.logoffDate) : '';
            }
        }, {
            data (row) {
                if (row.location) {
                    return row.location.name ? row.location.name : row.location.id;
                }
                return '';
            }
        }, {
            data (row) {
                if (row.shipType) {
                    return row.shipType.name ? row.shipType.name : row.shipType.id;
                }
                return '';
            }
        }, {
            data (row) {
                return row.startDate ? vm.$root.formatDate(row.startDate) : '';
            }
        }]
    });
}
</script>

<style type="text/scss" scoped>
    .input-option {
        display: inline;
        width: 80px;
    }
    .member-table {
        font-size: 90%;
    }
    @supports (position: sticky) {
        // position needs !important because "dataTables.bootstrap4.css" sets position to absolute
        .member-table thead th {
            position: sticky !important;
            top: 80px;
        }
    }
</style>
