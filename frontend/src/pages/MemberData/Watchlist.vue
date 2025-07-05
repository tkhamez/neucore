<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Watchlist</h1>
            <label class="watchlist-selection ms-3 mb-0">
                <select class="form-select" v-model="selectedId">
                    <option></option>
                    <option v-for="watchlist in watchlists" v-bind:value="watchlist.id">{{ watchlist.name }}</option>
                </select>
            </label>
        </div>
    </div>

    <ul v-cloak v-if="currentWatchlist" class="nc-nav nav nav-pills nav-fill">
        <li v-if="h.hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'warnings' }"
               :href="`#Watchlist/${currentWatchlist.id}/warnings`">Warnings</a>
        </li>
        <li v-if="h.hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'kick' }"
               :href="`#Watchlist/${currentWatchlist.id}/kick`">Kicklist</a>
        </li>
        <li v-if="h.hasRole('watchlist')" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'allow' }"
               :href="`#Watchlist/${currentWatchlist.id}/allow`">Allowlist</a>
        </li>
        <li v-if="manageIds.indexOf(currentWatchlist.id) !== -1" class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'settings' }"
               :href="`#Watchlist/${currentWatchlist.id}/settings`">Settings</a>
        </li>
    </ul>

    <watchlistLists v-cloak v-if="currentWatchlist && tab !== 'settings'"
                    :id="currentWatchlist.id" :tab="tab" :manageIds="manageIds"></watchlistLists>

    <watchlistSettings v-cloak v-if="currentWatchlist && tab === 'settings'"
                       :list="currentWatchlist"></watchlistSettings>
</div>
</template>

<script>
import {WatchlistApi}  from 'neucore-js-client';
import Helper from "../../classes/Helper";
import WatchlistLists    from './Watchlist--Lists.vue';
import WatchlistSettings from './Watchlist--Settings.vue';

export default {
    components: {
        WatchlistLists,
        WatchlistSettings,
    },

    props: {
        route: Array,
    },

    data() {
        return {
            h: new Helper(this),
            watchlists: [], // watchlists with view permission
            manageIds: [], // watchlist IDs with edit permission
            currentWatchlist: null,
            selectedId: '',
            tab: '',
        }
    },

    mounted() {
        window.scrollTo(0,0);

        getWatchlists(this, () => {
            setTab(this);
        });
    },

    watch: {
        selectedId() {
            const tab = this.route[2] ? this.route[2] : '';
            window.location.hash = `#Watchlist/${this.selectedId}/${tab}`;
        },

        route() {
            setTab(this);
        },
    },
}

/**
 * @param vm
 * @param [callback]
 */
function getWatchlists(vm, callback) {
    const api = new WatchlistApi;
    api.watchlistListAvailable((error, data) => {
        if (!error) {
            vm.watchlists = data;
            if (typeof callback === typeof Function) {
                callback();
            }
        }
    });
    api.watchlistListAvailableManage((error, data) => {
        if (!error) {
            vm.manageIds = [];
            for (const list of data) {
                vm.manageIds.push(list.id);
            }
        }
    });
}

function setTab(vm) {
    const tabs = ['warnings', 'kick', 'allow', 'settings'];
    let found = false;
    if (vm.route[1]) {
        const idFromPath = parseInt(vm.route[1], 10);
        for (const list of vm.watchlists) {
            if (list.id === idFromPath) {
                found = true;
                vm.currentWatchlist = list;
                vm.selectedId = idFromPath;
            }
        }
    }

    if (!found) {
        vm.currentWatchlist = null;
        vm.selectedId = '';
        return;
    }

    if (
        vm.route[2] &&
        tabs.indexOf(vm.route[2]) !== -1 &&
        (vm.route[2] !== 'settings' || vm.manageIds.indexOf(vm.currentWatchlist.id) !== -1)
    ) {
        vm.tab = vm.route[2];
    } else {
        vm.tab = 'warnings';
        window.location.hash = `#Watchlist/${vm.currentWatchlist.id}`;
    }
}
</script>

<style scoped>
    h1 {
        display: inline-block;
    }
    .watchlist-selection {
        position: relative;
        top: -0.5rem;
    }
</style>
