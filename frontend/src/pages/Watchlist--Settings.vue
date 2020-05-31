<template>
<div>
    <add-entity ref="addEntityModal" :settings="settings" v-on:success="addEntitySuccess()"></add-entity>

    <div class="card">
        <div class="card-header">Red Flags</div>
        <div class="card-body">
            Alliances and corporations whose members are included in the list if they
            also have characters in other (not NPC) corporations.
        </div>
        <admin :contentType="'alliances'" :type="'Watchlist'" :typeId="id" :sticky="sticky"></admin>
        <admin :contentType="'corporations'" :type="'Watchlist'" :typeId="id" :sticky="sticky"></admin>

        <div class="card-header">Blacklist</div>
        <div class="card-body">
            Accounts from the Red Flags list are moved to the blacklist
            if they have a character in one of these alliances or corporations.
            <br>
            <span class="small text-muted">
                Add missing
                <a href="#" @click.prevent="showAddEntityModal('Alliance')">
                    <span class="far fa-plus-square"></span> alliances
                </a>
                or
                <a href="#" @click.prevent="showAddEntityModal('Corporation')">
                    <span class="far fa-plus-square"></span> corporations
                </a>
            </span>
        </div>
        <admin ref="adminAlliance" :contentType="'alliances'" :type="'WatchlistBlacklist'" :typeId="id"
               :sticky="sticky"></admin>
        <admin ref="adminCorp" :contentType="'corporations'" :type="'WatchlistBlacklist'" :typeId="id"
               :sticky="sticky"></admin>

        <div class="card-header">Whitelist</div>
        <div class="card-body">
            Alliances and corporations that should be treated like NPC corporations
            (usually personal alt corporations).
        </div>
        <admin :contentType="'alliances'" :type="'WatchlistWhitelist'" :typeId="id" :sticky="sticky"></admin>
        <admin :contentType="'corporations'" :type="'WatchlistWhitelist'" :typeId="id" :sticky="sticky"></admin>
        <div class="card-body small text-muted">
            * Corporations are automatically added (and removed accordingly) if all their members belong to
            the same account.
        </div>
    </div>
</div>
</template>

<script>
import AddEntity from '../components/EntityAdd.vue';
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

    data: function() {
        return {
            sticky: 130,
        }
    },

    methods: {
        showAddEntityModal (addType) {
            this.$refs.addEntityModal.showModal(addType);
        },

        addEntitySuccess () {
            this.$refs.adminAlliance.getSelectContent();
            this.$refs.adminCorp.getSelectContent();
        },
    }
}
</script>

<style type="text/css" scoped>
    .card-header {
        position: sticky;
        top: 80px;
        z-index: 2;
    }
</style>
