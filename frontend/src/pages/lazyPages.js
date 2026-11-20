/**
 * Barrel module bundling pages into a single lazy chunk: App.vue loads all of
 * them through this module, so the bundler emits one shared chunk that is only
 * downloaded on the first visit to any of the pages.
 */

import GroupAdmin from './Administration/GroupAdmin.vue';
import AppAdmin from './Administration/AppAdmin.vue';
import UserAdmin from './Administration/UserAdmin.vue';
import RoleAdmin from './Administration/RoleAdmin.vue';
import PluginAdmin from './Administration/PluginAdmin.vue';
import TrackingAdmin from './Administration/TrackingAdmin.vue';
import WatchlistAdmin from './Administration/WatchlistAdmin.vue';
import SystemSettings from './Administration/SystemSettings.vue';
import EVELoginAdmin from './Administration/EVELoginAdmin.vue';

import GroupManagement from './Management/GroupManagement.vue';
import AppManagement from './Management/AppManagement.vue';
import PlayerManagement from './Management/PlayerManagement.vue';

import Watchlist from './MemberData/Watchlist.vue';
import Characters from './MemberData/Characters.vue';
import Esi from './MemberData/Esi.vue';

export {
    GroupAdmin,
    AppAdmin,
    UserAdmin,
    RoleAdmin,
    PluginAdmin,
    TrackingAdmin,
    WatchlistAdmin,
    SystemSettings,
    EVELoginAdmin,

    GroupManagement,
    AppManagement,
    PlayerManagement,

    Watchlist,
    Characters,
    Esi,
};
