<template>
<div class="container-fluid">

    <edit :type="'Watchlist'" ref="editModal"
          :functionCreate="create"
          :functionDelete="deleteIt"
          :functionRename="rename"></edit>

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Watchlist Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3">
                <h4 class="card-header">
                    Watchlists
                    <span class="far fa-plus-square add-watchlist" title="Add watchlist"
                          @mouseover="mouseover"
                          @mouseleave="mouseleave"
                          v-on:click="showCreateWatchlistModal()"></span>
                </h4>
                <div class="list-group">
                    <span v-for="watchlist in watchlists" class="list-item-wrap"
                          :class="{ active: currentWatchlist && currentWatchlist.id === watchlist.id }">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: currentWatchlist && currentWatchlist.id === watchlist.id }"
                           :href="`#WatchlistAdmin/${watchlist.id}/${contentType}`">{{ watchlist.name }}
                        </a>
                        <span class="entity-actions">
                            <span role="img" aria-label="edit" title="edit"
                                  class="fas fa-pencil-alt me-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showEditWatchlistModal(watchlist)"></span>
                            <span role="img" aria-label="delete" title="delete"
                                  class="far fa-trash-alt me-1"
                                  @mouseover="mouseover" @mouseleave="mouseleave"
                                  v-on:click="showDeleteWatchlistModal(watchlist)"></span>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <h4 class="card-header">{{ currentWatchlist ? currentWatchlist.name : '' }}</h4>
            </div>
            <ul v-cloak v-if="currentWatchlist" class="nav nav-pills nav-fill">
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'groups' }"
                       :href="`#WatchlistAdmin/${currentWatchlist.id}/groups`">View</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'groupsManage' }"
                       :href="`#WatchlistAdmin/${currentWatchlist.id}/groupsManage`">Manage</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       :class="{ 'active': contentType === 'setting' }"
                       :href="`#WatchlistAdmin/${currentWatchlist.id}/setting`">Settings</a>
                </li>
            </ul>

            <admin v-cloak v-if="currentWatchlist && ['groups', 'groupsManage'].indexOf(contentType) !== -1"
                   :contentType="contentType" :type="'Watchlist'" :typeId="currentWatchlist.id"></admin>

            <div v-cloak v-if="currentWatchlist && contentType === 'setting'" class="card border-secondary mb-3">
                <div class="card-body mb-0">
                    <div class="form-check">
                        <label class="form-check-label" for="lockWatchlistSettings">
                            <strong>Lock Watchlist settings.</strong>
                        </label>
                        <input class="form-check-input" type="checkbox" value="1"
                               id="lockWatchlistSettings" name="lockWatchlistSettings"
                               :checked="currentWatchlist.lockWatchlistSettings === true"
                               @change="saveLockWatchlistSettings($event.target.checked)">
                        <p class="mb-0">
                            If checked, only watchlist admins can add or remove corporations and alliances from
                            the watchlist. This has no effect on the kick and allow list settings.
                        </p>
                    </div>
                </div>
            </div>
            <div v-cloak v-if="currentWatchlist && contentType === 'setting' && currentWatchlist.lockWatchlistSettings"
                 class="card border-secondary">
                <div class="card-header">Alliances and Corporations to watch</div>
                <div class="card-body mb-0">
                    <admin :contentType="'alliances'" :type="'Watchlist'" :typeId="currentWatchlist.id"></admin>
                    <admin :contentType="'corporations'" :type="'Watchlist'" :typeId="currentWatchlist.id"></admin>
                </div>
            </div>
        </div>
    </div>
</div>
</template>

<script>
import {WatchlistApi} from 'neucore-js-client';
import Admin from '../components/EntityRelationEdit.vue';
import Edit from '../components/EntityEdit.vue';
import $ from "jquery";

export default {
    components: {
        Admin,
        Edit,
    },

    props: {
        settings: Object,
        route: Array,
        player: Object,
    },

    data () {
        return {
            watchlists: [],
            currentWatchlist: null,
            contentType: '',
        }
    },

    mounted () {
        window.scrollTo(0,0);
        getWatchlists(this);
    },

    watch: {
        route () {
            setWatchlistAndContentType(this);
        },
    },

    methods: {
        mouseover (ele) {
          $(ele.target).addClass('text-warning');
        },

        mouseleave (ele) {
          $(ele.target).removeClass('text-warning');
        },

        showCreateWatchlistModal () {
            this.$refs.editModal.showCreateModal();
        },

        showDeleteWatchlistModal (watchlist) {
            this.$refs.editModal.showDeleteModal(watchlist);
        },

        showEditWatchlistModal (watchlist) {
            this.$refs.editModal.showEditModal(watchlist);
        },

        create (newName) {
            const vm = this;
            new WatchlistApi().watchlistCreate(newName, (error, data, response) => {
                if (response.status === 400) {
                    vm.message('Missing name.', 'error');
                } else if (error) {
                    vm.message('Error creating watchlist.', 'error');
                } else {
                    vm.$refs.editModal.hideModal();
                    vm.message('Watchlist created.', 'success');
                    window.location.hash = `#WatchlistAdmin/${data.id}`;
                    getWatchlists(vm);
                }
            });
        },

        deleteIt (id) {
            const vm = this;
            new WatchlistApi().watchlistDelete(id, (error) => {
                if (error) {
                    vm.message('Error deleting watchlist.', 'error');
                } else {
                    vm.$refs.editModal.hideModal();
                    vm.message('Watchlist deleted.', 'success');
                    window.location.hash = '#WatchlistAdmin';
                    vm.currentWatchlist = null;
                    vm.contentType = '';
                    getWatchlists(vm);
                    vm.emitter.emit('playerChange'); // current player could have been a manager or "viewer"
                }
            });
        },

        rename (id, name) {
            const vm = this;
            new WatchlistApi().watchlistRename(id, name, (error, data, response) => {
                if (response.status === 400) {
                    vm.message('Missing name.', 'error');
                } else if (error) {
                    vm.message('Error renaming watchlist.', 'error');
                } else {
                    vm.message('Watchlist renamed.', 'success');
                    vm.$refs.editModal.hideModal();
                    getWatchlists(vm);
                }
            });
        },

        saveLockWatchlistSettings (checked) {
            const vm = this;
            const lock = checked ? '1' : '0';
            new WatchlistApi().watchlistLockWatchlistSettings(vm.currentWatchlist.id, lock, (error, data) => {
                if (error) {
                    vm.message('Error.', 'error');
                    return;
                }
                vm.currentWatchlist = data;
                for (let i = 0; i < vm.watchlists.length; i++) {
                    if (vm.watchlists[i].id === data.id) {
                        vm.watchlists[i] = data;
                        break;
                    }
                }
            });
        },
    }
}

function setWatchlistAndContentType(vm) {
    const watchlistId = vm.route[1] ? parseInt(vm.route[1], 10) : null;
    if (watchlistId) {
        vm.currentWatchlist = vm.watchlists.filter(watchlist => watchlist.id === watchlistId)[0];
        vm.contentType = vm.route[2] ? vm.route[2] : 'groups';
    }
}

function getWatchlists(vm) {
    (new WatchlistApi).watchlistListAll((error, data) => {
        if (! error) {
            vm.watchlists = data;
            setWatchlistAndContentType(vm);
        }
    });
}
</script>

<style scoped>
    .add-watchlist {
        float: right;
        cursor: pointer;
    }
</style>
