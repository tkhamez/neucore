<template>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <a v-cloak class="navbar-brand" :href="settings.customization_website">
            <img v-if="settings.customization_nav_logo" class="align-top mr-2 align-middle" alt="Logo"
                 :src="settings.customization_nav_logo">
            {{ settings.customization_nav_title }}
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse"
                data-target="#navbar01" aria-controls="navbar01"
                aria-expanded="true" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div v-cloak class="navbar-collapse collapse" id="navbar01">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item" :class="{ active: page === 'Home' }">
                    <a class="nav-link" href="#Home">Home</a>
                </li>
                <li v-if="hasRole('user')" class="nav-item" :class="{ active: page === 'Groups' }">
                    <a class="nav-link" href="#Groups">Groups</a>
                </li>
                <li v-if="hasAnyRole(['group-manager', 'app-manager', 'user-manager'])"
                    class="nav-item dropdown" :class="{ active: managePages.indexOf(page) !== -1 }">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Management
                    </a>
                    <div class="dropdown-menu">
                        <a v-if="hasRole('group-manager')"
                           class="dropdown-item" :class="{ active: page === 'GroupManagement' }"
                           href="#GroupManagement">Groups</a>
                        <a v-if="hasRole('app-manager')"
                           class="dropdown-item" :class="{ active: page === 'AppManagement' }"
                           href="#AppManagement">Apps</a>
                        <a v-if="hasRole('user-manager')"
                           class="dropdown-item" :class="{ active: page === 'PlayerGroupManagement' }"
                           href="#PlayerGroupManagement">Player Groups</a>
                    </div>
                </li>
                <li v-if="hasAnyRole(['group-admin', 'app-admin', 'user-admin', 'tracking-admin', 'settings'])"
                    class="nav-item dropdown" :class="{ active: adminPages.indexOf(page) !== -1 }">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Admin</a>
                    <div class="dropdown-menu">
                        <a v-if="hasRole('group-admin')"
                            class="dropdown-item" :class="{ active: page === 'GroupAdmin' }"
                            href="#GroupAdmin">Groups</a>
                        <a v-if="hasRole('app-admin')"
                            class="dropdown-item" :class="{ active: page === 'AppAdmin' }"
                            href="#AppAdmin">Apps</a>
                        <a v-if="hasRole('user-admin')"
                           class="dropdown-item" :class="{ active: page === 'UserAdmin' }"
                           href="#UserAdmin">Users</a>
                        <a v-if="hasRole('tracking-admin')"
                           class="dropdown-item" :class="{ active: page === 'TrackingAdmin' }"
                           href="#TrackingAdmin">Tracking</a>
                        <a v-if="hasRole('settings')"
                           class="dropdown-item" :class="{ active: page === 'SystemSettings' }"
                           href="#SystemSettings">Settings</a>
                    </div>
                </li>
                <li v-if="hasRole('tracking')" class="nav-item" :class="{ active: page === 'Tracking' }">
                    <a class="nav-link" href="#Tracking">Tracking</a>
                </li>
                <li v-if="hasAnyRole(['watchlist', 'watchlist-admin'])"
                    class="nav-item" :class="{ active: page === 'Watchlist' }">
                    <a class="nav-link" href="#Watchlist">Watchlist</a>
                </li>
                <li v-if="hasRole('esi')" class="nav-item" :class="{ active: page === 'Esi' }">
                    <a class="nav-link" href="#Esi">ESI</a>
                </li>
            </ul>

            <img v-if="authChar" :src="'https://image.eveonline.com/Character/' + authChar.id + '_32.jpg'"
                 class="d-inline-block align-top mr-2" alt="Character Portrait">
            <div v-if="authChar" class="dropdown">
                <button class="btn btn-primary dropdown-toggle mr-3" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                    {{ authChar.name }}
                </button>
                <div class="dropdown-menu scrollable-menu">
                    <h6 class="dropdown-header">Themes</h6>
                    <a v-for="theme in themes" class="dropdown-item" href="#"
                       :class="{ 'active': selectedTheme === theme }"
                       v-on:click.prevent="selectTheme(theme)">{{ theme }}</a>
                </div>
            </div>

            <a v-if="authChar" href="#logout" class="btn btn-outline-danger" title="Sign out">
                <span class="fas fa-sign-out-alt"></span>
            </a>
        </div>
    </nav>
</template>

<script>
import $ from 'jquery';

export default {
    props: {
        authChar: Object,
        page: String,
        settings: Object,
    },

    data: function() {
        return {
            managePages: ['GroupManagement', 'AppManagement', 'PlayerGroupManagement'],
            adminPages: ['GroupAdmin', 'AppAdmin', 'UserAdmin', 'TrackingAdmin', 'SystemSettings'],
            selectedTheme: '',
        }
    },

    mounted: function() {
        addNavBehaviour();
        this.selectTheme(this.settings.customization_default_theme);
    },

    watch: {
        settings: function () {
            this.selectTheme(this.settings.customization_default_theme);
        },

        selectedTheme () {
            const $body = $('body');
            for (const theme of this.themes) {
                $body.removeClass(theme.toLowerCase());
            }
            $body.addClass(this.selectedTheme.toLowerCase());
        }
    },

    methods: {
        selectTheme: function(name) {
            if (this.themes.indexOf(name) === -1) {
                return;
            }
            this.selectedTheme = name;
            const $enable = $("head link[href*='dist/theme-" + this.selectedTheme.toLowerCase() + "']");
            if ($enable.attr('rel') === 'stylesheet') {
                return;
            }
            const $disable = $("head link[href*='dist/theme-']");
            $disable.attr('rel', 'alternate stylesheet');
            $disable.attr('disabled', true);
            $enable.attr('rel', 'stylesheet');
            $enable.attr('disabled', false);
        }
    },
}

function addNavBehaviour() {
    const $navMain = $("#navbar01");
    $navMain.on("click", "a:not([data-toggle])", null, function() {
        $navMain.collapse('hide');
        $(this).closest('.dropdown').removeClass('show');
        $(this).closest('.dropdown-toggle').attr('aria-expanded', true);
        $(this).closest('.dropdown-menu').removeClass('show');
    });
    $navMain.on("mouseover", ".dropdown", null, function() {
        if ($('.navbar .navbar-toggler').is(':visible')) {
            return;
        }
        $(this).addClass('show');
        $(this).find('.dropdown-toggle').attr('aria-expanded', true);
        $(this).find('.dropdown-menu').addClass('show');
    });
    $navMain.on("mouseout", ".dropdown", null, function() {
        if ($('.navbar .navbar-toggler').is(':visible')) {
            return;
        }
        $(this).removeClass('show');
        $(this).find('.dropdown-toggle').attr('aria-expanded', false);
        $(this).find('.dropdown-menu').removeClass('show');
    });
}
</script>

<style scoped>
    .dropdown-menu {
        margin: 0;
    }
    .scrollable-menu {
        height: auto;
        max-height: calc(100vh - 80px);
        overflow-x: hidden;
    }
    .navbar-brand img {
        max-height: 100px;
    }
</style>
