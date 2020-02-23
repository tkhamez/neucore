<template>
<div>
    <add-entity ref="addEntityModal" :settings="settings" v-on:success="addEntitySuccess()"></add-entity>

    <div class="card">
        <div class="card-header">
            <strong>Access</strong>: Groups whose members are allowed to view the lists.
        </div>
        <div class="card-body">
            <admin :contentType="'groups'" :type="'Watchlist'" :typeId="id"></admin>
        </div>

        <div class="card-header">
            <strong>Red Flags</strong>: Alliances and corporations whose members are included in the list if they
            also have characters in other (not NPC) corporations.
        </div>
        <div class="card-body">
            <admin :contentType="'alliances'" :type="'Watchlist'" :typeId="id"></admin>
            <admin :contentType="'corporations'" :type="'Watchlist'" :typeId="id"></admin>
        </div>

        <div class="card-header">
            <strong>Blacklist</strong>: Accounts from the Red Flags list are moved to the blacklist
            if they have a character in one of these alliances or corporations.
        </div>
        <div class="card-body">
            <p class="small text-muted">
                Add missing <strong><a href="#" @click.prevent="showAddEntityModal('Alliance')">alliances</a></strong>
                or <strong><a href="#" @click.prevent="showAddEntityModal('Corporation')">corporations</a></strong>.
            </p>
            <admin ref="adminAlliance" :contentType="'alliances'" :type="'WatchlistBlacklist'" :typeId="id"></admin>
            <admin ref="adminCorp" :contentType="'corporations'" :type="'WatchlistBlacklist'" :typeId="id"></admin>
        </div>

        <div class="card-header">
            <strong>Whitelist</strong>: Alliances and corporations that should be treated like NPC corporations
            (usually <strong>P</strong>ersonal <strong>A</strong>lt <strong>C</strong>orp<strong>s</strong>).
        </div>
        <div class="card-body">
            <admin :contentType="'alliances'" :type="'WatchlistWhitelist'" :typeId="id"></admin>
            <admin :contentType="'corporations'" :type="'WatchlistWhitelist'" :typeId="id"></admin>
            <p class="small text-muted">
                * Corporations are automatically added (and removed accordingly) if all their members belong to
                the same account.
            </p>
        </div>
    </div>
</div>
</template>

<script>
import AddEntity from '../components/AddEntity.vue';
import Admin     from '../components/EntityRelationEdit.vue';

export default {
    components: {
        AddEntity,
        Admin,
    },

    props: {
        id: Number,
        settings: Object,
    },

    methods: {
        showAddEntityModal: function(addType) {
            this.$refs.addEntityModal.showModal(addType);
        },

        addEntitySuccess: function() {
            this.$refs.adminAlliance.getSelectContent();
            this.$refs.adminCorp.getSelectContent();
        },
    }
}
</script>

<style scoped>

</style>
