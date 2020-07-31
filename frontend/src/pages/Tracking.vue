<template>
    <div class="container-fluid">
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
            <div class="col-sm-12 col-md-6">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.mailCount">
                    Limit to characters whose <strong>mail count</strong> is greater than or equal to x.
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
                <table class="table table-hover nc-table-sm member-table" aria-describedby="Corporation members">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col">Character</th>
                            <th scope="col">Account</th>
                            <th scope="col" title="ESI token status">ESI</th>
                            <th scope="col">Logon</th>
                            <th scope="col">Logoff</th>
                            <th scope="col">Location</th>
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

export default {
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
                mailCount: null,
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
        getCorporations(this);
    },

    watch: {
        route () {
            setOptionsFromPath(this);
            getMembersDelayed(this);
        },

        corporation () {
            setPathFromOptions(this);
        },

        formOptions: {
            handler() {
                setPathFromOptions(this);
            },
            deep: true
        }
    },

    methods: {
        toggleSearchableColumn (index) {
            this.columns[index].searchable = ! this.columns[index].searchable;
            this.table.draw();
        },
    }
};

function getCorporations(vm) {
    new CorporationApi().corporationTrackedCorporations((error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.corporations = data;
        setOptionsFromPath(vm); // call after list of corporation was populated
        getMembers(vm);
    });
}

const getMembersDelayed = _.debounce((vm) => {
    getMembers(vm);
}, 400);

function getMembers(vm) {
    vm.table.clear();
    vm.table.draw();
    if (! vm.corporation) {
        return;
    }

    const opts = {
        inactive: vm.formOptions.daysInactive,
        active: vm.formOptions.daysActive,
        account: vm.formOptions.account,
        validToken: vm.formOptions.validToken,
        tokenStatusChanged: vm.formOptions.tokenChanged,
        mailCount: vm.formOptions.mailCount,
    };
    new CorporationApi().members(vm.corporation.id, opts, (error, data, response) => {
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
}

function setOptionsFromPath(vm) {
    if (vm.route[1]) {
        const corporationId = parseInt(vm.route[1], 10);
        for (const corporation of vm.corporations) {
            if (corporation.id === corporationId) {
                vm.corporation = corporation;
                break;
            }
        }
    }
    if (vm.route[2]) {
        vm.formOptions.daysInactive = vm.route[2];
    }
    if (vm.route[3]) {
        vm.formOptions.daysActive = vm.route[3];
    }
    if (vm.route[4]) {
        vm.formOptions.account = vm.route[4];
    }
    if (vm.route[5]) {
        vm.formOptions.validToken = vm.route[5];
    }
    if (vm.route[6]) {
        vm.formOptions.tokenChanged = vm.route[6];
    }
    if (vm.route[7]) {
        vm.formOptions.mailCount = vm.route[7];
    }
}

function setPathFromOptions(vm) {
    const params = [
        vm.corporation !== '' ? vm.corporation.id : '',
        vm.formOptions.daysInactive,
        vm.formOptions.daysActive,
        vm.formOptions.account,
        vm.formOptions.validToken,
        vm.formOptions.tokenChanged,
        vm.formOptions.mailCount,
    ];
    window.location.hash = `#Tracking/${params.join('/')}`;
}

function configureDataTable(vm) {
    $.fn.dataTable.ext.search.push((settings, searchData) => {
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
    });

    vm.table = $('.member-table').DataTable({
        lengthMenu: [
            [10, 25, 50, 100, 200, 500, 1000, 5000, -1],
            [10, 25, 50, 100, 200, 500, 1000, 5000, "All"]
        ],
        pageLength: 10,
        deferRender: true,
        order: [[3, "desc"]],
        'drawCallback': function() {
            $('[data-toggle="tooltip"]').tooltip();
            $('a[data-player-id]').on('click', (evt) => {
                $.Event(evt).preventDefault();
                vm.showCharacters(evt.target.dataset.playerId);
            });
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
                        <a href="#" data-player-id="${row.player.id}">
                            ${row.player.name} #${row.player.id}
                        </a>`;
                } else if (row.missingCharacterMailSentNumber > 0) {
                    return `
                        <div class="with-tooltip" data-toggle="tooltip" data-html="true" title="
                            Number mails sent: ${row.missingCharacterMailSentNumber}<br>
                            Last mail: ${vm.$root.formatDate(row.missingCharacterMailSentDate)}<br>
                            Result: ${row.missingCharacterMailSentResult ? row.missingCharacterMailSentResult : ''}
                        ">n/a</div>`;
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
                        <div class="with-tooltip" data-toggle="tooltip" data-html="true"
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

<style type="text/scss">
    .member-table .with-tooltip {
        text-decoration: underline;
        text-decoration-style: dotted;
    }
</style>
<style type="text/scss" scoped>
    .member-table {
        th:nth-child(2) {
          width: 15%; // +2.5
        }
        th:nth-child(3) {
          width: 5%; // -7.5
        }
        th:nth-child(6) {
          width: 20%; // +7.5
        }
        th:nth-child(7) {
          width: 10%; // -2.5
        }
    }
    .input-option {
        display: inline;
        width: 80px;
    }
    @supports (position: sticky) {
        .member-table thead th {
            position: sticky;
            top: 51px;
        }
    }
</style>
