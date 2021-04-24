<!--
Modal window to add alliances or corporations to the database.
 -->

<template>
    <div v-cloak class="modal" id="addAlliCorpModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add {{ addType }} to local database</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Search {{ addType }}</label>
                        <multiselect v-model="searchSelected" :options="searchResults"
                                     label="name" track-by="id"
                                     placeholder="Type to search"
                                     :searchable="true"
                                     :loading="searchIsLoading"
                                     :internal-search="false"
                                     :max-height="600"
                                     :show-no-results="false"
                                     @search-change="searchAlliCorp">
                        </multiselect>
                        <div class="form-check mt-2">
                            <label class="form-check-label">
                                <input class="form-check-input" type="checkbox" value="" v-model="searchStrict">
                                Strict search
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"
                            :disabled="searchError"
                            v-on:click="addAlliCorp()">Add</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import _ from 'lodash';
import $ from 'jquery';
import Multiselect from '@suadelabs/vue3-multiselect';
import {AllianceApi, CorporationApi} from 'neucore-js-client';

export default {
    components: {
        Multiselect,
    },

    props: {
        settings: Object,
    },

    data: function() {
        return {
            addType: '', // alliance or corp
            searchIsLoading: false,
            searchResults: [],
            searchSelected: null,
            searchStrict: false,
            searchError: false,
        }
    },

    methods: {
        showModal: function(addType) {
            this.addType = addType;
            this.searchResults = [];
            this.searchSelected = null;
            $('#addAlliCorpModal').modal('show');
        },

        searchAlliCorp (query) {
            if (query.length < 3) {
                return;
            }
            searchAlliCorpDelayed(this, query);
        },

        addAlliCorp () {
            if (! this.searchSelected) {
                return;
            }

            const vm = this;
            let api;
            if (this.addType === 'Corporation') {
                api = new CorporationApi();
            } else if (this.addType === 'Alliance') {
                api = new AllianceApi();
            } else {
                return;
            }

            api['add'].apply(api, [this.searchSelected.id, function(error, data, response) {
                if (response.statusCode === 409) {
                    vm.message(`${vm.addType} already exist.`, 'warning');
                } else if (response.statusCode === 404) {
                    vm.message(`${vm.addType} not found.`, 'error');
                } else if (error) {
                    vm.message(`Error adding ${vm.addType}.`, 'error');
                } else {
                    $('#addAlliCorpModal').modal('hide');
                    vm.message(`${vm.addType} "[${data.ticker}] ${data.name}" added.`, 'success');
                    vm.$emit('success');
                }
            }]);
        }
    }
}

const searchAlliCorpDelayed = _.debounce((vm, query) => {
    let category;
    if (vm.addType === 'Corporation') {
        category = 'corporation';
    } else if (vm.addType === 'Alliance') {
        category = 'alliance';
    } else {
        return;
    }

    const url = `${vm.settings.esiHost}/latest/search/?categories=${category}` +
        `&datasource=${vm.settings.esiDataSource}` +
        `&search=${encodeURIComponent(query)}&strict=${vm.searchStrict}`;

    vm.searchIsLoading = true;
    vm.searchResults = [];
    vm.searchError = false;
    $.get(url).done(response1 => {
        if (typeof response1[category] !== typeof []) {
            vm.searchIsLoading = false;
            return;
        }
        $.post(
            `${vm.settings.esiHost}/latest/universe/names/?datasource=${vm.settings.esiDataSource}`,
            JSON.stringify(response1[category])
        ).always(response2 => {
            vm.searchIsLoading = false;
            if (typeof response2 !== typeof []) {
                return;
            }
            vm.searchResults = []; // reset again because of parallel request
            for (const result of response2) {
                vm.searchResults.push(result);
            }
        });
    }).fail(() => {
        vm.searchIsLoading = false;
        vm.searchError = true;
        vm.searchResults.push({ name: 'Error, please try again later.'});
    });
}, 250);

</script>
