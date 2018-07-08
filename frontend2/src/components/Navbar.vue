<template>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <a class="navbar-brand" href="https://www.bravecollective.com/" target="_blank"
            title="Brave Collective: What's your fun per hour?">
            <img src="images/brave_32.png" class="align-top mr-2" alt="Brave logo">
            Brave Collective
        </a>
        <ul class="navbar-nav mr-auto">
            <li v-cloak v-if="hasAnyRole(['group-manager', 'group-admin'])"
                class="nav-item" :class="{ active: page == 'Home' }">
                <a class="nav-link" href="#Home">Home</a>
            </li>
            <li v-cloak v-if="hasRole('group-manager')"
                class="nav-item" :class="{ active: page == 'GroupManagement' }">
                <a class="nav-link" href="#GroupManagement">Group Management</a>
            </li>
        </ul>
        <span v-cloak v-if="authChar" class="navbar-brand">
            <img :src="'https://image.eveonline.com/Character/' + authChar.id + '_32.jpg'"
                class="d-inline-block align-top mr-2" alt="Character Portrait">
            {{ authChar.name }}
        </span>
        <a v-cloak v-if="authChar" href="#logout"
            class="btn btn-outline-success">Sign Out</a>
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
