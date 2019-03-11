<template>
    <nav class="navbar navbar-expand-md navbar-dark bg-primary sticky-top">
        <a class="navbar-brand" href="https://www.bravecollective.com/"
            title="Brave Collective: What's your fun per hour?">
            <img src="images/brave_32.png" class="align-top mr-2" alt="Brave logo">
            Brave Collective
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse"
                data-target="#navbar01" aria-controls="navbar01"
                aria-expanded="true" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="navbar-collapse collapse" id="navbar01" style="">
            <ul class="navbar-nav mr-auto">
                <li v-cloak class="nav-item" :class="{ active: page === 'Home' }">
                    <a class="nav-link" href="#Home">Home</a>
                </li>
                <li v-cloak v-if="hasAnyRole(['group-manager', 'app-manager'])"
                    class="nav-item dropdown" :class="{ active: managePages.indexOf(page) !== -1 }">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Management
                    </a>
                    <div class="dropdown-menu">
                        <a v-cloak v-if="hasRole('group-manager')"
                           class="dropdown-item" :class="{ active: page === 'GroupManagement' }"
                           href="#GroupManagement">Groups</a>
                        <a v-cloak v-if="hasRole('app-manager')"
                           class="dropdown-item" :class="{ active: page === 'AppManagement' }"
                           href="#AppManagement">Apps</a>
                    </div>
                </li>
                <li v-cloak v-if="hasAnyRole(['group-admin', 'app-admin', 'user-admin', 'settings'])"
                    class="nav-item dropdown" :class="{ active: adminPages.indexOf(page) !== -1 }">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Admin
                    </a>
                    <div class="dropdown-menu">
                        <a v-cloak v-if="hasRole('group-admin')"
                            class="dropdown-item" :class="{ active: page === 'GroupAdmin' }"
                            href="#GroupAdmin">Groups</a>
                        <a v-cloak v-if="hasRole('app-admin')"
                            class="dropdown-item" :class="{ active: page === 'AppAdmin' }"
                            href="#AppAdmin">Apps</a>
                        <a v-cloak v-if="hasRole('user-admin')"
                           class="dropdown-item" :class="{ active: page === 'UserAdmin' }"
                           href="#UserAdmin">Users</a>
                        <a v-cloak v-if="hasRole('user-admin')"
                           class="dropdown-item" :class="{ active: page === 'PlayerGroupAdmin' }"
                           href="#PlayerGroupAdmin">Player Groups</a>
                        <a v-cloak v-if="hasRole('settings')"
                           class="dropdown-item" :class="{ active: page === 'SystemSettings' }"
                           href="#SystemSettings">Settings</a>
                    </div>
                </li>
                <li v-cloak v-if="hasRole('tracking')"
                    class="nav-item" :class="{ active: page === 'Tracking' }">
                    <a class="nav-link" href="#Tracking">Member Tracking</a>
                </li>
                <li v-cloak v-if="hasRole('esi')"
                    class="nav-item" :class="{ active: page === 'Esi' }">
                    <a class="nav-link" href="#Esi">ESI</a>
                </li>
            </ul>

            <img v-cloak v-if="authChar"
                 :src="'https://image.eveonline.com/Character/' + authChar.id + '_32.jpg'"
                 class="d-inline-block align-top mr-2"
                 alt="Character Portrait">
            <div v-cloak v-if="authChar" class="dropdown">
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

            <a v-cloak v-if="authChar" href="#logout" class="btn btn-outline-danger">Sign Out</a>
        </div>
    </nav>
</template>

<script>
module.exports = {
    props: {
        authChar: [null, Object],
        page: String,
    },

    data: function() {
        return {
            managePages: ['GroupManagement', 'AppManagement'],
            adminPages: ['UserAdmin', 'GroupAdmin', 'AppAdmin', 'SystemSettings'],
            selectedTheme: 'Darkly',
            themes: [
                'Basic',
                'Cerulean',
                'Cosmo',
                'Cyborg',
                'Darkly',
                'Flatly',
                'Journal',
                'Litera',
                'Lumen',
                'Lux',
                'Materia',
                'Minty',
                'Pulse',
                'Sandstone',
                'Simplex',
                //'Sketchy',
                'Slate',
                'Solar',
                'Spacelab',
                'Superhero',
                'United',
                'Yeti',
            ]
        }
    },

    methods: {
        selectTheme: function(name) {
            this.selectedTheme = name;
            const $link = window.jQuery("head link[href*='dist/theme-']");
            const oldHref = $link.attr('href');
            const newHref = oldHref.replace(/^(.*theme-)[a-z]+(.*)$/, '$1' + this.selectedTheme.toLowerCase() + '$2');
            $link.attr('href', newHref);
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
