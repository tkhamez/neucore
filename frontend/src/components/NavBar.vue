<template>
    <nav class="navbar navbar-expand-md navbar-dark bg-primary sticky-top">
        <a v-cloak class="navbar-brand" :href="settings.customization_website">
            <img v-if="settings.customization_nav_logo" class="align-top mr-2" alt="Logo"
                 :src="settings.customization_nav_logo">
            {{ settings.customization_nav_title }}
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse"
                data-target="#navbar01" aria-controls="navbar01"
                aria-expanded="true" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div v-cloak class="navbar-collapse collapse" id="navbar01" style="">
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
                <li v-if="hasAnyRole(['group-admin', 'app-admin', 'user-admin', 'settings'])"
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
                        <a v-if="hasRole('settings')"
                           class="dropdown-item" :class="{ active: page === 'SystemSettings' }"
                           href="#SystemSettings">Settings</a>
                    </div>
                </li>
                <li v-if="hasAnyRole(['tracking', 'esi'])"
                    class="nav-item dropdown" :class="{ active: otherPages.indexOf(page) !== -1 }">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Other</a>
                    <div class="dropdown-menu">
                        <a v-if="hasRole('tracking')"
                           class="dropdown-item" :class="{ active: page === 'Tracking' }"
                           href="#Tracking">Tracking</a>
                        <a v-if="hasRole('esi')"
                           class="dropdown-item" :class="{ active: page === 'Esi' }"
                           href="#Esi">Esi</a>
                    </div>
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

            <a v-if="authChar" href="#logout" class="btn btn-outline-danger" title="Sign Out">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </nav>
</template>

<script>
module.exports = {
    props: {
        authChar: [null, Object],
        page: String,
        settings: Object,
    },

    data: function() {
        return {
            managePages: ['GroupManagement', 'AppManagement', 'PlayerGroupManagement'],
            adminPages: ['UserAdmin', 'GroupAdmin', 'AppAdmin', 'SystemSettings'],
            otherPages: ['Tracking', 'Esi'],
            selectedTheme: '',
        }
    },

    mounted: function() {
    },

    watch: {
        settings: function () {
            if (this.selectedTheme === '') {
                this.selectTheme(this.settings.customization_default_theme);
            }
        },
    },

    methods: {
        selectTheme: function(name) {
            if (this.themes.indexOf(name) === -1) {
                return;
            }
            this.selectedTheme = name;
            const $link = window.jQuery("head link[href*='dist/theme-']");
            if ($link.length === 0) {
                // first load
                const $appCss = window.jQuery("head link[href*='dist/app.']");
                let hash = '';
                if ($appCss.attr('href') !== 'dist/app.css') {
                    // find hash in prod mode
                    hash = $appCss.attr('href').replace(/^dist\/app(\.[a-zAZ0-9]+)\.css$/, '$1');
                }
                window.jQuery('head').append(
                    '<link href="dist/theme-' + this.selectedTheme.toLowerCase() + hash + '.css" rel="stylesheet">'
                );
            } else {
                const oldHref = $link.attr('href');
                const newHref = oldHref.replace(/^(.*theme-)[a-z]+(.*)$/, '$1' + this.selectedTheme.toLowerCase() + '$2');
                $link.attr('href', newHref);
            }
        }
    },
}
</script>

<style scoped>
    @media (min-width: 768px) {
        .dropdown:hover .dropdown-menu {
            display: block;
        }
    }
    .dropdown-menu {
        margin: 0;
    }
    .scrollable-menu {
        height: auto;
        max-height: calc(100vh - 80px);
        overflow-x: hidden;
    }
</style>
