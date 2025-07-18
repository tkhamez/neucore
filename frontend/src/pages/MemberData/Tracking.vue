<template>
    <div class="container-fluid page-tracking">
        <div class="row mb-3 mt-3">
            <div class="col-lg-12">
                <h1>Member Tracking</h1>

                <div class="input-group">
                    <label for="corporation-select">
                        <span class="input-group-text">Select corporation</span>
                    </label>
                    <select class="form-select" v-model="corporation" id="corporation-select">
                        <option value=""></option>
                        <option v-for="option in corporations" v-bind:value="option">
                            {{ option.name }} [{{ option.ticker }}]
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12 col-md-6 mb-1">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.daysInactive">
                    Limit to members who have been <strong>inactive</strong> for x days or longer.
                </label>
            </div>
            <div class="col-sm-12 col-md-6 mb-1">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.daysActive">
                    Limit to members who were <strong>active</strong> in the last x days.
                </label>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 col-md-6 mb-1">
                <label class="small">
                    <select class="form-select form-select-sm input-option" v-model="formOptions.account">
                        <option></option>
                        <option value="true">with</option>
                        <option value="false">without</option>
                    </select>
                    Limit to members <em>with/without</em> an <strong>account</strong>
                </label>
            </div>
            <div class="col-sm-12 col-md-6 mb-1">
                <label class="small">
                    <select class="form-select form-select-sm input-option" v-model="formOptions.tokenStatus">
                        <option></option>
                        <option value="valid">valid</option>
                        <option value="invalid">invalid</option>
                        <option value="none">no</option>
                    </select>
                    Limit to characters with a <em>valid/invalid/no</em> <strong>token</strong>
                </label>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 col-md-6 mb-1">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.tokenChanged">
                    Limit to characters whose ESI <strong>token status</strong> has not changed for x days
                </label>
            </div>
            <div class="col-sm-12 col-md-6 mb-1">
                <label class="small">
                    <input type="text" pattern="[0-9]*" class="form-control form-control-sm input-option"
                           v-model="formOptions.mailCount">
                    Limit to characters whose "missing character" <strong>mail count</strong> is greater than or
                    equal to x.
                </label>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-lg-6">
                <p class="small text-muted">
                    Last update:
                    <span v-if="corporation.trackingLastUpdate">
                        {{ U.formatDate(corporation.trackingLastUpdate) }}
                    </span>
                </p>
            </div>
            <div class="col-lg-6 text-end">
                <div class="dropdown">
                    <span class="small">Search in: &nbsp;</span>
                    <button title="Columns" class="btn btn-secondary btn-sm dropdown-toggle"
                            type="button" data-bs-toggle="dropdown" aria-label="Columns" aria-expanded="false">
                        <span role="img" class="fa fa-th-list"></span>
                        <span class="caret"></span>
                    </button>
                    <div class="dropdown-menu" v-on:click.stop="">
                        <div class="dropdown-item small" v-for="(column, idx) in columns"
                             @click="toggleSearchableColumn(idx, $event)">
                            <label>
                                <input class="form-check-input" type="checkbox" v-model="column.searchable">
                                &nbsp; {{ column.name }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <table class="table table-hover nc-table-sm member-table" aria-describedby="Corporation members">
                    <thead class="table-light">
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
                <p class="small text-muted">All dates and times are in GMT.</p>
            </div>
        </div>

    </div>
</template>

<script>
import _ from 'lodash';
import $ from 'jquery';
import 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import {Tooltip} from 'bootstrap';
import {CorporationApi} from 'neucore-js-client';
import Helper from "../../classes/Helper";
import Util from "../../classes/Util";

export default {
    props: {
        route: Array,
    },

    data() {
        return {
            U: Util,
            h: new Helper(this),
            corporation: "", // empty string to select the first entry in the drop-down
            corporations: [],
            formOptions: {
                daysActive: null,
                daysInactive: null,
                account: null,
                tokenStatus: null,
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

    mounted() {
        window.scrollTo(0,0);

        configureDataTable(this);
        getCorporations(this);
    },

    unmounted() {
        this.table.clear();
        this.table.destroy();
    },

    watch: {
        route() {
            setOptionsFromPath(this);
            getMembersDelayed(this);
        },

        corporation() {
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
        /**
         * @param {Number} index
         * @param {Event} event
         */
        toggleSearchableColumn(index, event) {
            if (event.target.tagName === 'LABEL') {
                // prevent double click
                return;
            }
            this.columns[index].searchable = !this.columns[index].searchable;
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

const getMembersDelayed = _.debounce(vm => {
    getMembers(vm);
}, 400);

function getMembers(vm) {
    vm.table.clear();
    vm.table.draw();
    if (!vm.corporation) {
        return;
    }

    const opts = {
        inactive: vm.formOptions.daysInactive,
        active: vm.formOptions.daysActive,
        account: vm.formOptions.account,
        tokenStatus: vm.formOptions.tokenStatus,
        tokenStatusChanged: vm.formOptions.tokenChanged,
        mailCount: vm.formOptions.mailCount,
    };
    new CorporationApi().members(vm.corporation.id, opts, (error, data, response) => {
        if (error) {
            if (response.statusCode === 403) {
                vm.h.message(error, 'warning', 2000);
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
        vm.formOptions.tokenStatus = vm.route[5];
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
        vm.formOptions.tokenStatus,
        vm.formOptions.tokenChanged,
        vm.formOptions.mailCount,
    ];
    window.location.hash = `#Tracking/${params.join('/')}`;
}

function configureDataTable(vm) {
    if ($.fn.dataTable.ext.search.length === 0) {
        $.fn.dataTable.ext.search.push((settings, searchData) => {
            const term = $('#dt-search-0').val().toLowerCase().trim();
            for (let index = 0; index < vm.columns.length; index++) {
                if (!vm.columns[index].searchable) {
                    continue;
                }
                if (searchData[index].toLowerCase().indexOf(term) !== -1) {
                    return true;
                }
            }
            return false;
        });
    }

    const esiColumnText = (row) => {
        if (!row.character) {
            return '';
        }
        if (row.character.validToken) {
            return 'valid';
        }
        if (row.character.validToken === false) {
            return 'invalid';
        }
        return 'n/a';
    };

    vm.table = $('.member-table').DataTable({
        lengthMenu: [
            [10, 25, 50, 100, 200, 500, 1000, 5000, -1],
            [10, 25, 50, 100, 200, 500, 1000, 5000, "All"]
        ],
        pageLength: 10,
        deferRender: true,
        order: [[3, "desc"]],
        'drawCallback': () => {
            document.querySelectorAll('.page-tracking [data-bs-toggle="tooltip"]').forEach(tooltip => {
                if (tooltip.dataset.tooltipInit !== '1') {
                    tooltip.dataset.tooltipInit = '1';
                    return new Tooltip(tooltip);
                }
            });
            const $link = $('a[data-player-id]');
            $link.off('click');
            $link.on('click', evt => {
                $.Event(evt).preventDefault();
                vm.h.showCharacters(evt.target.dataset.playerId);
            });
        },
        columns: [{
            // Character
            render: {
                _(data, type, row) {
                    return `
                        <a class="external" href="https://evewho.com/character/${row.id}" target="_blank"
                           rel="noopener noreferrer" title="Eve Who">${(row.name ? row.name : row.id)}</a>`;
                },
                sort(data, type, row) {
                    return row.name;
                },
            }
        }, {
            // Account
            render: {
                _(data, type, row) {
                    if (row.player) {
                        return `
                            <a href="#" data-player-id="${row.player.id}">
                                ${row.player.name} #${row.player.id}
                            </a>`;
                    } else if (row.missingCharacterMailSentNumber > 0) {
                        return `
                            <div class="text-with-tooltip" data-bs-toggle="tooltip" data-bs-html="true" title="
                                Number mails sent: ${row.missingCharacterMailSentNumber}<br>
                                Last mail: ${Util.formatDate(row.missingCharacterMailSentDate)}<br>
                                Result: ${row.missingCharacterMailSentResult ? row.missingCharacterMailSentResult : ''}
                            ">n/a</div>`;
                    } else {
                        return '';
                    }
                },
                sort(data, type, row) {
                    return row.player ? row.player.name :
                        (row.missingCharacterMailSentNumber > 0 ? 'n/a' : '');
                },
            }
        }, {
            // ESI
            render: {
                _(data, type, row) {
                    const text = esiColumnText(row);
                    if (row.character && row.character.validTokenTime) {
                        return `
                            <div class="text-with-tooltip" data-bs-toggle="tooltip" data-bs-html="true"
                                  title="Token status change date: ${Util.formatDate(row.character.validTokenTime)}<br>
                                        Token's last check date: ${Util.formatDate(row.character.tokenLastChecked)}">
                                ${text}
                            </div>`;
                    } else {
                        return text;
                    }
                },
                sort(data, type, row) {
                    return esiColumnText(row);
                }
            }
        }, {
            // Logon
            data(row) {
                return row.logonDate ? Util.formatDate(row.logonDate) : '';
            }
        }, {
            // Logoff
            data(row) {
                return row.logoffDate ? Util.formatDate(row.logoffDate) : '';
            }
        }, {
            // Location
            data(row) {
                if (row.location) {
                    return row.location.name ? row.location.name : row.location.id;
                }
                return '';
            }
        }, {
            // Ship
            data(row) {
                if (row.shipType) {
                    return row.shipType.name ? row.shipType.name : row.shipType.id;
                }
                return '';
            }
        }, {
            // Joined
            data(row) {
                return row.startDate ? Util.formatDate(row.startDate) : '';
            }
        }]
    });
}
</script>

<style lang="scss" scoped>
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
        .member-table thead tr {
            position: sticky;
            top: 51px;
        }
    }
</style>
