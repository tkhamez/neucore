<!--
Select and table to add and remove objects from other objects.
-->

<template>
    <div class="card border-secondary mb-3" :class="cardClass">

        <div v-cloak v-if="showGroupsEntity" class="modal fade" id="showGroupsModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            [{{ showGroupsEntity.ticker }}]
                            {{ showGroupsEntity.name }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Other groups that use this alliance/corporation:</p>
                        <ul class="list-group">
                            <li v-for="group in showGroupsEntity.groups" class="list-group-item">
                                {{ group.name }}
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-cloak class="card-body" :class="cardBodyClass">
            <p v-cloak v-if="type === 'Group' && contentType === 'managers'">
                Managers can add and remove players to a group.
            </p>
            <p v-cloak v-if="type === 'Group' && contentType === 'requiredGroups'">
                <strong>Required Groups</strong><br>
                Add groups that are a prerequisite (<em>any one</em> of them) to be a member of that group.
            </p>
            <p v-cloak v-if="type === 'Group' && contentType === 'forbiddenGroups'">
                <strong>Forbidden Groups</strong><br>
                Add groups that an account cannot be a member of (<em>any one</em> of them) to be a member of that group.
            </p>
            <p v-cloak v-if="type === 'App' && contentType === 'managers'">
                Managers can change the application secret.
            </p>
            <p v-cloak v-if="
                (type === 'Group' || type === 'App') &&
                (contentType === 'alliances' || contentType === 'corporations')
            ">
                Players in these {{contentType}} are automatically added to the group and removed when they leave.
            </p>
            <p v-cloak v-if="type === 'Role' && contentType === 'requiredGroups'">
                <strong>Required Groups</strong><br>
                Add groups that are required (<em>any one</em> of them) to keep the role.
            </p>
            <div class="row mb-1" v-cloak v-if="type === 'App' && contentType === 'groups'">
                <p class="col-9">
                    The application can only see the membership of players to groups that are listed here.
                </p>
                <div class="col-3 text-end">
                    <button class="btn btn-sm btn-outline-warning" v-on:click="addAllGroupsToApp()">
                        Add all groups to app
                    </button>
                </div>
            </div>
            <p v-cloak v-if="type === 'App' && contentType === 'roles'">
                See
                <a class="external" :href="`${settings.repository}/blob/master/doc/API.md`"
                   target="_blank" rel="noopener noreferrer">doc/API.md</a>
                for permissions for each role.
            </p>
            <p v-cloak v-if="type === 'App' && contentType === 'eveLogins'">
                The app can only use ESI tokens from selected EVE logins.
            </p>
            <p v-cloak v-if="type === 'Corporation' && contentType === 'groups'">
                Members of these groups can view the tracking data of the selected corporation.<br>
                Director(s) with valid ESI token:
                <span v-for="director in directors">
                    <a href="#" v-on:click.prevent="h.showCharacters(director.playerId)">
                        {{ director.name }}</a>&nbsp;
                </span>
            </p>
            <p v-cloak v-if="type === 'Watchlist' && contentType === 'groups'">
                Groups whose members are allowed to view the lists.
            </p>
            <p v-cloak v-if="type === 'Watchlist' && contentType === 'groupsManage'">
                Groups whose members are allowed to add and remove accounts and corporations from
                the kick and allow lists and edit the list configuration (unless locked in settings).
            </p>

            <multiselect v-if="!useCharacterSearch"
                         v-model="newObject" :options="currentSelectContent"
                         v-bind:placeholder="placeholder"
                         label="name" track-by="id"
                         :loading="isLoading"
                         :searchable="true"
                         @search-change="findSelectContent"
                         :custom-label="customLabel">
            </multiselect>

            <character-search v-if="useCharacterSearch" :admin="searchAdmin" :currentOnly="searchCurrentOnly"
                              v-on:result="searchResult = $event"></character-search>
            <character-result v-if="useCharacterSearch" :admin="searchAdmin"
                              :searchResult="searchResult"
                              :selectedPlayers="tableContent"
                              v-on:add="addOrRemoveEntityToEntity($event, 'add')"
                              v-on:remove="addOrRemoveEntityToEntity($event, 'remove')"></character-result>

        </div>

        <div :class="{ 'table-responsive': !sticky}">
            <table v-cloak v-if="typeId || typeName" class="table table-hover mb-0 nc-table-sm"
                   aria-describedby="Elements already added">
                <thead class="table-light" :class="{ 'sticky': sticky > 0}">
                    <tr>
                        <th scope="col" :style="stickyTop" v-if="
                                contentType === 'managers' || contentType === 'groups' ||
                                contentType === 'requiredGroups' || contentType === 'forbiddenGroups' ||
                                contentType === 'groupsManage' ||
                                contentType === 'corporations' || contentType === 'alliances'">
                            <span v-if="
                                contentType === 'managers' || contentType === 'groups' ||
                                contentType === 'requiredGroups' || contentType === 'forbiddenGroups' ||
                                contentType === 'groupsManage'">
                                ID
                            </span>
                            <span v-if="contentType === 'corporations' || contentType === 'alliances'">EVE ID</span>
                        </th>
                        <th scope="col" :style="stickyTop" v-if="
                            contentType === 'corporations' || contentType === 'alliances'">Ticker</th>
                        <th scope="col" :style="stickyTop">Name</th>
                        <th scope="col" :style="stickyTop" v-if="contentType === 'managers'">Characters</th>
                        <th scope="col" :style="stickyTop" v-if="contentType === 'corporations'">Alliance</th>
                        <th scope="col" :style="stickyTop" class="text-nowrap" v-if="
                            contentType === 'corporations' && type === 'WatchlistAllowlist'">auto *</th>
                        <th scope="col" :style="stickyTop" v-if="
                            (type === 'Group' || type === 'App') &&
                            (contentType === 'corporations' || contentType === 'alliances')">Groups</th>
                        <th scope="col" :style="stickyTop">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tableContent">
                        <td v-if="
                                contentType === 'managers' || contentType === 'groups' ||
                                contentType === 'requiredGroups' || contentType === 'forbiddenGroups' ||
                                contentType === 'groupsManage' || contentType === 'corporations' ||
                                contentType === 'alliances'">
                            {{ row.id }}
                        </td>
                        <td v-if="contentType === 'corporations' || contentType === 'alliances'">{{ row.ticker }}</td>
                        <td>
                            <a v-if="contentType === 'corporations'" class="external" rel="noopener noreferrer"
                               target="_blank" :href="`https://evewho.com/corporation/${row.id}`">{{ row.name }}</a>
                            <a v-else-if="contentType === 'alliances'" class="external" rel="noopener noreferrer"
                               target="_blank" :href="`https://evewho.com/alliance/${row.id}`">{{ row.name }}</a>
                            <a v-else-if="contentType === 'managers' && h.hasRole('user-admin')"
                               :href="`#UserAdmin/${row.id}`" title="User Administration">{{ row.name }}</a>
                            <span v-else>{{ row.name }}</span>
                        </td>
                        <td v-if="contentType === 'managers'">
                            <button class="btn btn-info btn-sm" v-on:click="h.showCharacters(row.id)">
                                Show characters
                            </button>
                        </td>
                        <td v-if="contentType === 'corporations'">
                            <a v-if="row.alliance" class="external" target="_blank" rel="noopener noreferrer"
                               :href="`https://evewho.com/alliance/${row.alliance.id}`">
                                [{{ row.alliance.ticker }}] {{ row.alliance.name }}</a>
                        </td>
                        <td v-if="contentType === 'corporations' && type === 'WatchlistAllowlist'">
                            {{ row.autoAllowlist }}
                        </td>
                        <td v-if="
                                (type === 'Group' || type === 'App') &&
                                (contentType === 'corporations' || contentType === 'alliances')">
                            <button class="btn btn-info btn-sm" v-on:click="showGroups(row.id)">Show groups</button>
                        </td>
                        <td>
                            <button class="btn btn-danger btn-sm"
                                    v-on:click="addOrRemoveEntityToEntity(row.id, 'remove')">
                                Remove
                                <span v-if="contentType === 'corporations'">corporation</span>
                                <span v-if="contentType === 'alliances'">alliance</span>
                                <span v-if="contentType === 'managers'">manager</span>
                                <span v-if="contentType === 'groups' || contentType === 'requiredGroups' ||
                                            contentType === 'forbiddenGroups'">group</span>
                                <span v-if="contentType === 'roles'">role</span>
                                <span v-if="contentType === 'eveLogins'">EVE login</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="contentType === 'corporations' && type === 'WatchlistAllowlist'" class="small text-muted ms-1 mt-1">
            * Corporations are automatically added (and removed accordingly) if all their members belong to
            the same account.
        </p>
    </div> <!-- card -->
</template>

<script>
import {toRef} from "vue";
import {Modal} from "bootstrap";
import Multiselect from '@suadelabs/vue3-multiselect';
import {
    AllianceApi,
    AppApi,
    CorporationApi,
    GroupApi,
    PlayerApi,
    RoleApi,
    SettingsApi,
    WatchlistApi
} from 'neucore-js-client';
import Data from '../classes/Data';
import Helper from "../classes/Helper";
import Util from "../classes/Util";
import CharacterSearch from '../components/CharacterSearch.vue';
import CharacterResult from '../components/CharacterResult.vue';

export default {
    components: {
        CharacterSearch,
        CharacterResult,
        Multiselect,
    },

    inject: ['store'],

    props: {
        /**
         * Content of the select box, e.g. "managers"
         */
        contentType: '',

        /**
         * Type of entity to add/remove items from, e. g. "Group"
         */
        type: '',

        /**
         * ID of entity to add/remove items from, e. g. 12
         */
        typeId: Number,

        /**
         * Unique name of entity to add/remove items from, e. g. user-chars
         */
        typeName: String,

        /**
         * Optional offset top for a sticky table head.
         */
        sticky: Number,

        searchCurrentOnly: Boolean,

        cardClass: String,

        cardBodyClass: String,
    },

    data() {
        return {
            h: new Helper(this),
            settings: toRef(this.store.state, 'settings'),
            player: toRef(this.store.state, 'player'),
            newObject: "", // empty string to select the first entry in the drop-down
            placeholder: "", // placeholder for the multi-select
            selectContentLoaded: [], // all options from backend
            currentSelectContent: [], // copy of selectContentLoaded without items from the table
            tableContent: [],
            showGroupsEntity: null, // one alliance or corporation object with groups
            withGroups: [], // all alliances or corporations with groups
            isLoading: false,
            useCharacterSearch: false,
            searchAdmin: false,
            searchResult: [],
            directors: [],
        }
    },

    computed: {
        stickyTop() {
            return {
                top: `${this.sticky}px`,
            };
        }
    },

    mounted() {
        setUseCharacterSearch(this);
        this.customPlaceholder();
        this.getSelectContent();
        if (this.typeId || this.typeName) {
            this.getTableContent();
            this.getWithGroups();
            fetchDirector(this);
        }
    },

    watch: {
        typeId() {
            this.newObject = "";
            if (this.typeId) {
                this.getTableContent();
                fetchDirector(this);
            }
        },

        typeName() {
            this.newObject = "";
            if (this.typeName) {
                this.getTableContent();
                fetchDirector(this);
            }
        },

        contentType() {
            setUseCharacterSearch(this);
            this.newObject = "";
            this.customPlaceholder();
            this.getSelectContent();
            if (this.typeId || this.typeName) {
                this.getTableContent();
                this.getWithGroups();
            }
        },

        newObject() {
            if (this.newObject === "") {
                return;
            }
            this.addOrRemoveEntityToEntity(this.newObject.id, 'add');
            this.newObject = "";
        },

        selectContentLoaded() {
            this.removeSelectedOptions();
        },

        tableContent() {
            this.removeSelectedOptions();
        },
    },

    methods: {
        customPlaceholder() {
            if (this.contentType === 'managers') {
                this.placeholder = 'Add manager';
            } else if (this.contentType === 'alliances') {
                this.placeholder = `Add alliance ${Data.messages.typeToSearch2}`;
            } else if (this.contentType === 'corporations') {
                this.placeholder = `Add corporation ${Data.messages.typeToSearch2}`;
            } else if (
                this.contentType === 'groups' || this.contentType === 'groupsManage' ||
                this.contentType === 'requiredGroups' || this.contentType === 'forbiddenGroups'
            ) {
                this.placeholder = 'Add group';
            } else if (this.contentType === 'roles') {
                this.placeholder = 'Add role';
            } else if (this.contentType === 'eveLogins') {
                this.placeholder = 'EVE login';
            }
        },

        customLabel(option) {
            let label = option.name;
            if (this.contentType === 'managers') {
                label += ` #${option.id}`
            } else if (this.contentType === 'corporations' || this.contentType === 'alliances') {
                label += ` [${option.ticker}]`;
            }
            if (this.contentType === 'corporations' && option.alliance) {
                label += ` - ${option.alliance.name} [${option.alliance.ticker}]`;
            }
            return label
        },

        findSelectContent(query) {
            if (['corporations', 'alliances'].indexOf(this.contentType) !== -1) {
                const type = this.contentType === 'corporations' ? 'Corporations' : 'Alliances';
                this.isLoading = true;
                Util.findCorporationsOrAlliancesDelayed(query, type, result => {
                    this.isLoading = false;
                    this.selectContentLoaded = result;
                });
            }
        },

        getSelectContent() {
            this.selectContentLoaded = [];

            let api;
            let method;
            if (this.contentType === 'managers') {
                api = new PlayerApi();
                if (this.type === 'Group') {
                    method = 'groupManagers';
                } else if (this.type === 'App') {
                    method = 'appManagers';
                }
            } else if (['corporations', 'alliances'].indexOf(this.contentType) !== -1) {
                return; // These use Ajax search
            } else if (
                (
                    this.type === 'App' ||
                    this.type === 'Corporation' ||
                    this.type === 'Group' ||
                    this.type === 'Player' ||
                    this.type === 'Role' ||
                    this.type === 'Watchlist'
                )
                &&
                (
                    this.contentType === 'groups' ||
                    this.contentType === 'groupsManage' ||
                    this.contentType === 'requiredGroups' ||
                    this.contentType === 'forbiddenGroups'
                )
            ) {
                api = new GroupApi();
                method = 'userGroupAll';
            } else if (this.contentType === 'roles') {
                this.selectContentLoaded = [
                    { id: 'app-groups', name: 'app-groups' },
                    { id: 'app-chars', name: 'app-chars' },
                    { id: 'app-tracking', name: 'app-tracking' },
                    { id: 'app-esi-login', name: 'app-esi-login' },
                    { id: 'app-esi-proxy', name: 'app-esi-proxy' },
                    { id: 'app-esi-token', name: 'app-esi-token' },
                ];
                return;
            } else if (this.contentType === 'eveLogins') {
                api = new SettingsApi();
                method = 'userSettingsEveLoginList';
            }
            if (!api || !method) {
                return;
            }

            api[method].apply(api, [(error, data) => {
                if (error) { // 403 usually
                    return;
                }
                this.selectContentLoaded = data;
            }]);
        },

        getTableContent() {
            this.tableContent = [];

            let api;
            let method;
            if (this.type === 'Group') {
                api = new GroupApi();
            } else if (this.type === 'App') {
                api = new AppApi();
            } else if (this.type === 'Player') {
                api = new PlayerApi();
            } else if (this.type === 'Corporation') {
                api = new CorporationApi();
            } else if (
                this.type === 'Watchlist' ||
                this.type === 'WatchlistKicklist' ||
                this.type === 'WatchlistAllowlist'
            ) {
                api = new WatchlistApi();
            } else if (this.type === 'Role') {
                api = new RoleApi();
            }
            if (this.type === 'Group' && this.contentType === 'managers') {
                method = 'userGroupManagers';
            } else if (this.type === 'App' && this.contentType === 'managers') {
                method = 'managers';
            } else if (this.type === 'Group' && this.contentType === 'corporations') {
                method = 'corporations';
            } else if (this.type === 'Group' && this.contentType === 'alliances') {
                method = 'alliances';
            } else if (
                this.type === 'App' &&
                (this.contentType === 'groups' || this.contentType === 'roles' || this.contentType === 'eveLogins')
            ) {
                method = 'show';
            } else if ((this.type === 'App' || this.type === 'Player') && this.contentType === 'groups') {
                method = 'showById';
            } else if (this.type === 'Group' && this.contentType === 'requiredGroups') {
                method = 'requiredGroups';
            } else if (this.type === 'Group' && this.contentType === 'forbiddenGroups') {
                method = 'userGroupForbiddenGroups';
            } else if (this.type === 'Corporation' && this.contentType === 'groups') {
                method = 'getGroupsTracking';
            } else if (this.type === 'Watchlist' && this.contentType === 'groups') {
                method = 'watchlistGroupList';
            } else if (this.type === 'Watchlist' && this.contentType === 'groupsManage') {
                method = 'watchlistManagerGroupList';
            } else if (this.type === 'Watchlist' && this.contentType === 'alliances') {
                method = 'watchlistAllianceList';
            } else if (this.type === 'Watchlist' && this.contentType === 'corporations') {
                method = 'watchlistCorporationList';
            } else if (
                (this.type === 'WatchlistKicklist' || this.type === 'WatchlistAllowlist') &&
                this.contentType === 'alliances'
            ) {
                method = this.type === 'WatchlistKicklist' ?
                    'watchlistKicklistAllianceList' : 'watchlistAllowlistAllianceList';
            } else if (
                (this.type === 'WatchlistKicklist' || this.type === 'WatchlistAllowlist') &&
                this.contentType === 'corporations'
            ) {
                method = this.type === 'WatchlistKicklist' ?
                    'watchlistKicklistCorporationList' : 'watchlistAllowlistCorporationList';
            } else if (this.type === 'Role') {
                method = 'userRoleRequiredGroups';
            }
            if (!api || !method) {
                return;
            }

            let typeParam = this.typeId;
            if (this.type === 'Role') {
                typeParam = this.typeName;
            }
            api[method].apply(api, [typeParam, (error, data) => {
                if (error) { // 403 usually
                    if (this.type === 'Player') {
                        this.$emit('activePlayer', null); // pass data to parent
                    }
                    return;
                }
                if (this.type === 'App' && this.contentType === 'groups') {
                    this.tableContent = data.groups;
                } else if (this.type === 'App' && this.contentType === 'roles') {
                    // transform string to object and remove "app" role
                    const roles = [];
                    for (const role of data.roles) {
                        if (role !== 'app') {
                            roles.push({ id: role, name: role });
                        }
                    }
                    this.tableContent = roles;
                } else if (this.type === 'App' && this.contentType === 'eveLogins') {
                    this.tableContent = data.eveLogins;
                }  else if (this.type === 'Player') {
                    this.tableContent = data.groups;
                    this.$emit('activePlayer', data); // pass data to parent
                }  else {
                    this.tableContent = data;
                }
            }]);
        },

        getWithGroups() {
            if (this.type !== 'Group') {
                return;
            }

            this.withGroups = [];

            let api;
            let method = 'withGroups';
            if (this.contentType === 'corporations') {
                api = new CorporationApi();
                method = 'userCorporationWithGroups';
            } else if (this.contentType === 'alliances') {
                api = new AllianceApi();
            } else {
                return;
            }

            api[method].apply(api, [(error, data) => {
                if (error) { // 403 usually
                    return;
                }
                this.withGroups = data;
            }]);
        },

        removeSelectedOptions() {
            this.currentSelectContent = [...this.selectContentLoaded]; // copy by value
            let removed = 0;
            for (const [index, option] of this.selectContentLoaded.entries()) {
                for (const row of this.tableContent) {
                    if (row.id === option.id) {
                        this.currentSelectContent.splice(index - removed, 1);
                        removed ++;
                        break;
                    }
                }
            }
        },

        showGroups(corpOrAllianceId) {
            this.showGroupsEntity = null;
            for (const entity of this.withGroups) {
                if (entity.id === corpOrAllianceId) {
                    this.showGroupsEntity = entity;
                    break;
                }
            }
            window.setTimeout(() => {
                new Modal('#showGroupsModal').show();
            }, 10);
        },

        /**
         * @param {number} id ID of the entity to be added or removed
         * @param {string} action "add" or "remove"
         */
        addOrRemoveEntityToEntity(id, action) {
            let api;
            let method;
            let param1;
            let param2;
            if (
                this.type === 'App' &&
                (this.contentType === 'groups' || this.contentType === 'roles' || this.contentType === 'eveLogins')
            ) {
                api = new AppApi();
                if (this.contentType === 'groups') {
                    method = action === 'add' ? 'userAppAddGroup' : 'userAppRemoveGroup';
                } else if (this.contentType === 'roles') {
                    method = action === 'add' ? 'addRole' : 'removeRole';
                } else if (this.contentType === 'eveLogins') {
                    method = action === 'add' ? 'userAppAddEveLogin' : 'userAppRemoveEveLogin';
                }
                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Player') {
                api = new GroupApi();
                method = action === 'add' ? 'addMember' : 'removeMember';
                param1 = id;
                param2 = this.typeId;
            } else if (
                this.type === 'Group' &&
                (this.contentType === 'corporations' || this.contentType === 'alliances')
            ) {
                if (this.contentType === 'corporations') {
                    api = new CorporationApi();
                    method = action === 'add' ? 'userCorporationAddGroup' : 'userCorporationRemoveGroup';
                } else if (this.contentType === 'alliances') {
                    api = new AllianceApi();
                    method = action === 'add' ? 'addGroup' : 'removeGroup';
                }
                param1 = id;
                param2 = this.typeId;
            } else if (
                this.type === 'Group' &&
                (this.contentType === 'requiredGroups' || this.contentType === 'forbiddenGroups')
            ) {
                api = new GroupApi();
                if (this.contentType === 'requiredGroups') {
                    method = action === 'add' ? 'addRequiredGroup' : 'removeRequiredGroup';
                } else {
                    method = action === 'add' ? 'userGroupAddForbiddenGroup' : 'userGroupRemoveForbiddenGroup';
                }
                param1 = this.typeId;
                param2 = id;
            } else if (this.contentType === 'managers') {
                if (this.type === 'Group') {
                    api = new GroupApi();
                    method = action === 'add' ? 'userGroupAddManager' : 'userGroupRemoveManager';
                } else if (this.type === 'App') {
                    api = new AppApi();
                    method = action === 'add' ? 'addManager' : 'removeManager';
                }
                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Corporation') {
                api = new CorporationApi();
                method = action === 'add' ? 'addGroupTracking' : 'removeGroupTracking';
                param1 = this.typeId;
                param2 = id;
            } else if (
                this.type === 'Watchlist' ||
                this.type === 'WatchlistKicklist' ||
                this.type === 'WatchlistAllowlist'
            ) {
                api = new WatchlistApi();
                if (this.type === 'Watchlist') {
                    if (this.contentType === 'groups') {
                        method = action === 'add' ? 'watchlistGroupAdd' : 'watchlistGroupRemove';
                    } else if (this.contentType === 'groupsManage') {
                        method = action === 'add' ? 'watchlistManagerGroupAdd' : 'watchlistManagerGroupRemove';
                    } else if (this.contentType === 'alliances') {
                        method = action === 'add' ? 'watchlistAllianceAdd' : 'watchlistAllianceRemove';
                    } else {
                        method = action === 'add' ? 'watchlistCorporationAdd' : 'watchlistCorporationRemove';
                    }
                } else if (this.type === 'WatchlistKicklist') {
                    if (this.contentType === 'alliances') {
                        method = action === 'add' ? 'watchlistKicklistAllianceAdd' : 'watchlistKicklistAllianceRemove';
                    } else {
                        method = action === 'add' ?
                            'watchlistKicklistCorporationAdd' : 'watchlistKicklistCorporationRemove';
                    }
                } else if (this.type === 'WatchlistAllowlist') {
                    if (this.contentType === 'alliances') {
                        method = action === 'add' ?
                            'watchlistAllowlistAllianceAdd' : 'watchlistAllowlistAllianceRemove';
                    } else {
                        method = action === 'add' ?
                            'watchlistAllowlistCorporationAdd' : 'watchlistAllowlistCorporationRemove';
                    }
                }
                param1 = this.typeId;
                param2 = id;
            } else if (this.type === 'Role') {
                api = new RoleApi();
                method = action === 'add' ? 'userRoleAddRequiredGroup' : 'userRoleRemoveRequiredGroup';
                param1 = this.typeName;
                param2 = id;
            }
            if (!api || !method) {
                return;
            }

            callApi(this, api, method, param1, param2, () => {
                if (
                    (this.type === 'Player' && this.typeId === this.player.id) ||
                    (
                        this.type === 'Watchlist' &&
                        (this.contentType === 'groups' || this.contentType === 'groupsManage')
                    ) ||
                    (
                        (this.type === 'Group' || this.type === 'App') &&
                        this.contentType === 'managers' &&
                        id === this.player.id
                    ) ||
                    (this.type === 'Corporation' && this.contentType === 'groups') // Tracking Admin
                ) {
                    this.emitter.emit('playerChange');
                }
                if (
                    this.type === 'Group' &&
                    (this.contentType === 'corporations' || this.contentType === 'alliances')
                ) {
                    this.getWithGroups();
                }

                this.getTableContent();
            });
        },

        addAllGroupsToApp() {
            if (!this.typeId || this.type !== 'App') {
                return;
            }
            const groups = [...this.currentSelectContent];
            const api = new AppApi();

            function addGroup(vm) {
                if (groups.length > 0) {
                    const id = groups[0].id;
                    groups.splice(0, 1);
                    callApi(vm, api, 'userAppAddGroup', vm.typeId, id, () => {
                        addGroup(vm);
                    });
                } else {
                    vm.getTableContent();
                }
            }
            addGroup(this);
        },
    },
}

function callApi(vm, api, method, param1, param2, callback) {
    api[method].apply(api, [param1, param2, (error, data, response) => {
        if (vm.type === 'Player' && method === 'addMember' && response.statusCode === 400) {
            vm.h.message(Data.messages.errorRequiredForbiddenGroup, 'warning');
        } else if (
            (
                (vm.type === 'Group' && method === 'userGroupAddManager') ||
                (vm.type === 'App' && method === 'addManager')
            ) &&
            response.statusCode === 400
        ) {
            vm.h.message(Data.messages.errorRoleRequiredGroup, 'warning');
        }
        if (error) { // 403 usually
            return;
        }
        callback();
    }]);
}

function setUseCharacterSearch(vm) {
    vm.useCharacterSearch =
        (vm.type === 'App' && vm.contentType === 'managers') ||
        (vm.type === 'Group' && vm.contentType === 'managers');
    vm.searchAdmin = vm.useCharacterSearch; // same conditions atm.
}

function fetchDirector(vm) {
    if (vm.type !== 'Corporation' || vm.contentType !== 'groups') {
        return;
    }
    vm.directors = [];
    new CorporationApi().corporationTrackingDirector(vm.typeId, (error, data) => {
        if (error) { // 403 usually
            return;
        }
        vm.directors = data;
    });
}
</script>

<style scoped>
    table thead.sticky th {
        position: sticky;
    }
</style>
