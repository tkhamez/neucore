<template>
<div class="card">
    <div class="card-header">
        <strong>Access</strong>: Groups whose members are allowed to view the lists.
    </div>
    <div class="card-body">
        <admin ref="admin" :contentType="'groups'" :type="'Watchlist'" :typeId="id"></admin>
    </div>

    <div class="card-header">
        <strong>Red Flags</strong>: Alliances and corporations whose members are included in the list if they
        also have characters in other (not NPC) corporations.
    </div>
    <div class="card-body">
        <admin ref="admin" :contentType="'alliances'" :type="'Watchlist'" :typeId="id"></admin>
        <admin ref="admin" :contentType="'corporations'" :type="'Watchlist'" :typeId="id"></admin>
    </div>

    <div class="card-header">
        <strong>Blacklist</strong>: Accounts from the Red Flags list are moved to the blacklist
        if they have a character in one of these alliances or corporations.
    </div>
    <div class="card-body">
        <p class="small text-muted">
            You can add missing alliances and corporations on the <a href="#GroupAdmin">Group Administration</a>
            page.
        </p>
        <admin ref="admin" :contentType="'alliances'" :type="'WatchlistBlacklist'" :typeId="id"></admin>
        <admin ref="admin" :contentType="'corporations'" :type="'WatchlistBlacklist'" :typeId="id"></admin>
    </div>

    <div class="card-header">
        <strong>Whitelist</strong>: Alliances and corporations that should be treated like NPC corporations
        (usually <strong>P</strong>ersonal <strong>A</strong>lt <strong>C</strong>orp<strong>s</strong>).
    </div>
    <div class="card-body">
        <admin ref="admin" :contentType="'alliances'" :type="'WatchlistWhitelist'" :typeId="id"></admin>
        <admin ref="admin" :contentType="'corporations'" :type="'WatchlistWhitelist'" :typeId="id"></admin>
        <p class="small text-muted">
            * Corporations are automatically added (and removed accordingly) if all their members belong to
            the same account.
        </p>
    </div>
</div>
</template>

<script>
import Admin from '../components/EntityRelationEdit.vue';

export default {
    components: {
        Admin,
    },

    props: {
        id: Number,
    },
}
</script>

<style scoped>

</style>
