<template>
    <nav class="navbar navbar-expand-md navbar-dark bg-primary sticky-top">
        <a class="navbar-brand" href="https://www.bravecollective.com/"
            title="Brave Collective: What's your fun per hour?">
            <img src="images/brave_32.png" class="align-top mr-2" alt="Brave logo">
            Brave Collective
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse"
                data-target="#navbarColor01" aria-controls="navbarColor01"
                aria-expanded="true" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="navbar-collapse collapse" id="navbarColor01" style="">
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
                        <a v-cloak v-if="hasRole('settings')"
                           class="dropdown-item" :class="{ active: page === 'SystemSettings' }"
                           href="#SystemSettings">Settings</a>
                    </div>
                </li>
                <li v-cloak v-if="hasRole('esi')"
                    class="nav-item" :class="{ active: page === 'Esi' }">
                    <a class="nav-link" href="#Esi">ESI</a>
                </li>
            </ul>
            <span v-cloak v-if="authChar" class="navbar-brand">
                <img :src="'https://image.eveonline.com/Character/' + authChar.id + '_32.jpg'"
                    class="d-inline-block align-top mr-2" alt="Character Portrait">
                {{ authChar.name }}
            </span>
            <a v-cloak v-if="authChar" href="#logout"
                class="btn btn-outline-success">Sign Out</a>
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
        }
    },
}
</script>

<style scoped>

</style>
