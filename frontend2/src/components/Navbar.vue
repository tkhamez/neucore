<template>
    <nav class="navbar navbar-expand-md navbar-dark bg-primary sticky-top">
        <a class="navbar-brand" href="https://www.bravecollective.com/" target="_blank"
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
                <li v-cloak class="nav-item" :class="{ active: page == 'Home' }">
                    <a class="nav-link" href="#Home">Home</a>
                </li>
                <li v-cloak v-if="hasRole('group-manager')"
                    class="nav-item" :class="{ active: page == 'GroupManagement' }">
                    <a class="nav-link" href="#GroupManagement">Group Management</a>
                </li>
                <li v-cloak v-if="hasAnyRole(['group-admin', 'app-admin', 'user-admin'])"
                    class="nav-item dropdown"
                    :class="{ active: ['UserAdmin', 'GroupAdmin', 'AppAdmin'].indexOf(page) !== -1 }">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Admin
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown" >
                        <a class="dropdown-item" :class="{ active: page == 'GroupAdmin' }"
                            href="#GroupAdmin">Groups</a>
                        <a class="dropdown-item" :class="{ active: page == 'UserAdmin' }"
                            href="#UserAdmin">Users</a>
                        <a class="dropdown-item" :class="{ active: page == 'AppAdmin' }"
                            href="#AppAdmin">Apps</a>
                    </div>
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
}
</script>

<style scoped>

</style>
