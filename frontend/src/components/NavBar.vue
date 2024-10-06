<!--suppress HtmlUnknownAnchorTarget -->
<template>
<div v-cloak class="modal" id="generatePasswordModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>
                    This password can be used together with your account ID to log in instead
                    of using EVE SSO.
                </p>
                <code>{{ newPassword }}</code>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container-fluid">
        <a v-cloak class="navbar-brand" :href="settings.customization_website">
            <img v-if="settings.customization_nav_logo" class="align-top me-2 align-middle" alt="Logo"
                 :src="settings.customization_nav_logo">
            {{ settings.customization_nav_title }}
        </a>
        <button v-if="hasNavigation()" v-cloak class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbar01" aria-controls="navbar01"
                aria-expanded="true" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div v-if="hasNavigation()" v-cloak class="navbar-collapse collapse" id="navbar01">
            <ul class="navbar-nav me-auto">
                <li v-if="h.hasRole('user')" class="nav-item">
                    <a class="nav-link" :class="{ active: page === 'Home' }" href="#Home">Home</a>
                </li>
                <li v-if="h.hasRole('user') && settings.navigationShowGroups === '1'" class="nav-item">
                    <a class="nav-link" :class="{ active: page === 'Groups' }" href="#Groups">Groups</a>
                </li>
                <li v-for="menu in getDropdownMenus()" class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle"
                       :class="{ active: isActiveDropdown(menu.id) }"
                       href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{ menu.name }}
                    </a>
                    <div class="dropdown-menu">
                        <a v-for="item in getNavigationItems(menu.id)"
                           class="dropdown-item" :class="{ active: isActiveItem(item) }"
                           :href="item.href" :target="item.target">{{ item.name }}</a>
                    </div>
                </li>
                <li v-for="item in getNavigationItems(navigationParent.root.id)" class="nav-item">
                    <a class="nav-link" :href="item.href" :target="item.target">{{ item.name }}</a>
                </li>
            </ul>

            <img v-if="authChar" :src="h.characterPortrait(authChar.id, 32)"
                 class="d-inline-block align-top me-2" alt="portrait">

            <div v-if="authChar" class="dropdown">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                           aria-haspopup="true" aria-expanded="false">
                            {{ authChar.name }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-end scrollable-menu">
                            <a href="#" @click.prevent="logout()" class="dropdown-item">
                                <span role="img" class="fas fa-sign-out"></span>
                                Sign out
                            </a>
                            <a href="#" class="dropdown-item" @click.prevent="generatePassword()">
                                Generate password
                            </a>
                            <h6 class="dropdown-header">Themes</h6>
                            <a v-for="theme in themes" class="dropdown-item" href="#"
                               :class="{ 'active': selectedTheme === theme }"
                               v-on:click.prevent="selectTheme(theme)">{{ theme }}</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
</template>

<script>
import {toRef} from "vue";
import {Collapse, Dropdown, Modal} from 'bootstrap';
import { AuthApi } from 'neucore-js-client';
import Data from '../classes/Data';
import Helper from "../classes/Helper";
import Util from "../classes/Util";

export default {
    inject: ['store'],

    props: {
        authChar: Object,
        route: Array,
        logout: Function,
    },

    data() {
        return {
            h: new Helper(this),
            settings: toRef(this.store.state, 'settings'),
            navigationParent: {
                root:           { id: 'root',           name: '' },
                services:       { id: 'services',       name: 'Services' },
                management:     { id: 'management',     name: 'Management' },
                administration: { id: 'administration', name: 'Administration' },
                member_data:    { id: 'member_data',    name: 'Member Data' },
            },
            backendHost: Data.envVars.backendHost,
            navigationItems: {
                services: [],
                management: [
                    {path: 'GroupManagement', name: 'Groups', roles: ['group-manager']},
                    {path: 'AppManagement', name: 'Apps', roles: ['app-manager']},
                    {path: 'PlayerManagement', name: 'Player', roles: ['user-manager']},
                ],
                administration: [
                    {path: 'GroupAdmin', name: 'Groups', roles: ['group-admin']},
                    {path: 'AppAdmin', name: 'Apps', roles: ['app-admin']},
                    {path: 'UserAdmin', name: 'Users', roles: ['user-admin']},
                    {path: 'RoleAdmin', name: 'Roles', roles: ['user-admin']},
                    {path: 'PluginAdmin', name: 'Plugins', roles: ['plugin-admin']},
                    {path: 'TrackingAdmin', name: 'Tracking', roles: ['tracking-admin']},
                    {path: 'WatchlistAdmin', name: 'Watchlist', roles: ['watchlist-admin']},
                    {path: 'SystemSettings', name: 'Settings', roles: ['settings']},
                    {path: 'EVELoginAdmin', name: 'EVE Logins', roles: ['settings']},
                    {path: 'Statistics', name: 'Statistics', roles: ['statistics']},
                ],
                member_data: [
                    {path: 'Tracking', name: 'Member Tracking', roles: ['tracking']},
                    {path: 'Watchlist', name: 'Watchlist', roles: ['watchlist', 'watchlist-manager']},
                    {path: 'Characters', name: 'Characters', roles: ['user-chars']},
                    {path: 'Esi', name: 'ESI', roles: ['esi']},
                ],
            },
            page: '',
            themes: Data.themes,
            selectedTheme: '',
            generatePasswordModal: null,
            newPassword: '',
        }
    },

    mounted() {
        this.$nextTick(() => {
            window.setTimeout(addNavBehaviour, 500);
        });
        this.page = this.route[0];
        if (this.selectedTheme === '') {
            this.selectedTheme = window.APP_DEFAULT_THEME;
        }

        for (const serviceItem of this.settings.navigationServices) {
            this.navigationItems.services.push({
                path: `Service/${serviceItem.id}`,
                name: serviceItem.name,
                roles: ['user'],
            });
        }
    },

    watch: {
        route() {
            this.page = this.route[0];
        },
        selectedTheme() {
            for (const theme of this.themes) {
                document.body.classList.remove(theme.toLowerCase());
            }
            document.body.classList.add(this.selectedTheme.toLowerCase());
        }
    },

    methods: {
        selectTheme(name) {
            if (this.themes.indexOf(name) === -1) {
                return;
            }
            this.selectedTheme = name;
            const enable = document.querySelector(`head link[href*='css/theme-${this.selectedTheme.toLowerCase()}']`);
            if (!enable || enable.getAttribute('rel') === 'stylesheet') {
                return;
            }
            document.querySelectorAll("head link[href*='css/theme-']").forEach(link => {
                link.setAttribute('rel', 'alternate stylesheet');
            });
            enable.setAttribute('rel', 'stylesheet');
        },

        hasNavigation() {
            return (
                this.h.hasRole('user') ||
                this.hasNavigationItem(this.navigationParent.root.id) ||
                this.hasNavigationItem(this.navigationParent.services.id) ||
                this.hasNavigationItem(this.navigationParent.management.id) ||
                this.hasNavigationItem(this.navigationParent.administration.id) ||
                this.hasNavigationItem(this.navigationParent.member_data.id)
            );
        },

        getDropdownMenus() {
            return [
                this.navigationParent.services,
                this.navigationParent.management,
                this.navigationParent.administration,
                this.navigationParent.member_data,
            ].filter(menu => this.hasNavigationItem(menu.id));
        },

        hasNavigationItem(parent) {
            return this.getNavigationItems(parent).length > 0;
        },

        isActiveDropdown(menu) {
            if (!this.navigationItems[menu]) {
                return false;
            }
            for (const menuItem of this.navigationItems[menu]) {
                let check = this.page;
                if (menu === 'services') {
                    check += '/' + parseInt(this.route[1], 10);
                }
                if (menuItem.path === check) {
                    return true;
                }
            }
            return false;
        },

        isActiveItem(item) {
            if (this.page === 'Service') {
                return `${this.page}/` + parseInt(this.route[1], 10) === item.active;
            }
            return this.page === item.active;
        },

        getNavigationItems(parent) {
            let items = [];

            Object.entries(this.navigationItems)
                .filter(entry => entry[0] === parent)
                .map(entry => entry[1]
                    .filter(coreItem => this.h.hasAnyRole(coreItem.roles))
                    .map(coreItem => items.push({
                        active: coreItem.path,
                        href: `#${coreItem.path}`,
                        name: coreItem.name,
                        target: ''
                    }))
                );

            this.settings.navigationGeneralPlugins
                .filter(item => item.parent === parent)
                .map(pluginItem => items.push({
                    active: '',
                    href: this.backendHost + pluginItem.url,
                    name: pluginItem.name,
                    target: pluginItem.target,
                }))

            return items;
        },

        generatePassword() {
            if (!this.generatePasswordModal) {
                this.generatePasswordModal = new Modal('#generatePasswordModal');
            }
            this.generatePasswordModal.show();

            new AuthApi().userAuthPasswordGenerate((error, data) => {
                if (error) {
                    this.newPassword = 'Error, please try again.';
                    return;
                }
                this.newPassword = data;
            });
        },
    },
}

function addNavBehaviour() {
    const navMain = document.getElementById('navbar01');
    if (!navMain) {
        return;
    }
    const collapse = new Collapse('#navbar01', {toggle: false });

    // Close the un-collapsed navigation on click on a navigation item
    navMain.querySelectorAll('a:not([data-bs-toggle])').forEach(navItem => {
        navItem.addEventListener('click', () => {
            if (Util.isVisible('.navbar .navbar-toggler')) {
                collapse.hide();
            }
        });
    });

    // Open/close dropdown on mouse over/out.
    navMain.querySelectorAll('.dropdown').forEach(subNav => {
        ['mouseover', 'mouseout'].forEach(type => {
            subNav.addEventListener(type, evt => {
                if (Util.isVisible('.navbar .navbar-toggler')) {
                    return;
                }
                // noinspection JSUnresolvedFunction
                const element = evt.currentTarget.querySelector('.dropdown-toggle');
                // Can't use toggle(), that gets it wrong sometimes.
                if (evt.type === 'mouseover') {
                    new Dropdown(element).show();
                } else {
                    new Dropdown(element).hide();
                    document.activeElement.blur(); // sometimes needed for some reason to remove the "active" color
                }
            });
        });
    });
}
</script>

<!--suppress CssUnusedSymbol -->
<style scoped>
    .navbar {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
    .lux .navbar,
    .sketchy .navbar {
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
    }
    .materia .navbar {
        padding-top: 0.1rem;
        padding-bottom: 0.1rem;
    }
    .simplex .navbar,
    .slate .navbar {
        padding-top: 0;
        padding-bottom: 0;
    }
    .simplex .navbar-brand,
    .slate .navbar-brand {
        padding-top: 0;
        padding-bottom: 0;
    }
    .slate .navbar .nav-link {
        padding-top: 0.8rem;
        padding-bottom: 0.8rem;
    }
    .lux .navbar .btn,
    .slate .navbar .btn {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    .dropdown-menu {
        margin: 0;
    }
    .sketchy .dropdown-menu {
        margin: -3px;
    }
    @media (min-width: 992px) {
        .scrollable-menu {
            height: auto;
            max-height: calc(100vh - 80px);
            overflow-x: hidden;
            overflow-y: scroll;
        }
    }
    .navbar-brand img {
        max-height: 100px;
    }
</style>
