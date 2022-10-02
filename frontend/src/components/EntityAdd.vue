<!--
Modal window to add alliances or corporations to the database.
 -->

<template>
    <div v-cloak class="modal" id="addAlliCorpModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add {{ addType }} to local database</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Search {{ addType }}</label>
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
                    <small class="text-muted">Use exact name if there is no result.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {toRef} from "vue";
import _ from 'lodash';
import {Modal} from "bootstrap";
import Multiselect from '@suadelabs/vue3-multiselect';
import {AllianceApi, CorporationApi} from 'neucore-js-client';
import Helper from "../classes/Helper";

export default {
    components: {
        Multiselect,
    },

    inject: ['store'],

    data() {
        return {
            h: new Helper(this),
            settings: toRef(this.store.state, 'settings'),
            addType: '', // alliance or corp
            searchIsLoading: false,
            searchResults: [],
            searchSelected: null,
            addAlliCorpModal: null,
        }
    },

    watch: {
        searchSelected() {
            addAlliCorp(this);
        }
    },

    methods: {
        showModal(addType) {
            this.addType = addType;
            this.searchResults = [];
            this.searchSelected = null;
            this.addAlliCorpModal = new Modal('#addAlliCorpModal');
            this.addAlliCorpModal.show();
        },

        searchAlliCorp(query) {
            if (query.length < 3) {
                return;
            }
            searchAlliCorpDelayed(this, query);
        },
    }
}

const searchAlliCorpDelayed = _.debounce((vm, query) => {
    let category;
    if (vm.addType === 'Corporation') {
        category = 'corporations';
    } else if (vm.addType === 'Alliance') {
        category = 'alliances';
    } else {
        return;
    }

    vm.searchIsLoading = true;
    vm.searchResults = [];
    window.fetch(`${vm.settings.esiHost}/latest/universe/ids?datasource=${vm.settings.esiDataSource}`, {
        method: 'POST',
        body: JSON.stringify([query])
    }).then(response => {
        vm.searchIsLoading = false;
        if (!response.ok) {
            throw new Error();
        }
        return response.json();
    }).then(data => {
        if (typeof data[category] !== typeof []) {
            return;
        }
        vm.searchResults = []; // reset again because of parallel request
        for (const result of data[category]) {
            vm.searchResults.push(result);
        }
    }).catch(() => {
        vm.searchIsLoading = false;
        vm.searchResults.push({ name: 'Error, please try again later.'});
    });
}, 250);

function addAlliCorp(vm) {
    if (!vm.searchSelected) {
        return;
    }

    let api;
    let method = 'add';
    if (vm.addType === 'Corporation') {
        api = new CorporationApi();
        method = 'userCorporationAdd';
    } else if (vm.addType === 'Alliance') {
        api = new AllianceApi();
    } else {
        return;
    }

    api[method].apply(api, [vm.searchSelected.id, (error, data, response) => {
        if (response.statusCode === 409) {
            vm.h.message(`${vm.addType} already exist.`, 'warning');
        } else if (response.statusCode === 404) {
            vm.h.message(`${vm.addType} not found.`, 'error');
        } else if (error) {
            vm.h.message(`Error adding ${vm.addType}.`, 'error');
        } else {
            vm.addAlliCorpModal.hide();
            vm.h.message(`${vm.addType} "[${data.ticker}] ${data.name}" added.`, 'success');
            vm.$emit('success');
        }
    }]);
}
</script>
