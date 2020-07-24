<template>
<div>
    <add-entity ref="addEntityModal" :settings="settings" v-on:success="addEntitySuccess()"></add-entity>

    <div class="card">
        <div class="card-header bg-dark text-light"><strong>Warnings</strong></div>
        <div class="card-body">
            <p>
                Alliances and corporations whose members are included in the list if they
                also have characters in other (not NPC) corporations.
            </p>
            <admin :contentType="'alliances'" :type="'Watchlist'" :typeId="id" :sticky="sticky"></admin>
            <admin :contentType="'corporations'" :type="'Watchlist'" :typeId="id" :sticky="sticky"></admin>
        </div>

        <div class="card-header bg-dark text-light"><strong>Kicklist</strong></div>
        <div class="card-body">
            <p>
                Accounts from the warning list are moved to the kicklist
                if they have a character in one of these alliances or corporations.
            </p>
            <p class="small text-muted">
                Add missing
                <a href="#" @click.prevent="showAddEntityModal('Alliance')">
                    <span class="far fa-plus-square"></span> alliances
                </a>
                or
                <a href="#" @click.prevent="showAddEntityModal('Corporation')">
                    <span class="far fa-plus-square"></span> corporations
                </a>
            </p>
            <admin ref="adminAlliance" :contentType="'alliances'" :type="'WatchlistKicklist'" :typeId="id"
                   :sticky="sticky"></admin>
            <admin ref="adminCorp" :contentType="'corporations'" :type="'WatchlistKicklist'" :typeId="id"
                   :sticky="sticky"></admin>
        </div>

        <div class="card-header bg-dark text-light"><strong>Allowlist</strong></div>
        <div class="card-body">
            <p>
                Alliances and corporations that should be treated like NPC corporations
                (usually personal alt corporations).
            </p>
            <admin :contentType="'alliances'" :type="'WatchlistAllowlist'" :typeId="id" :sticky="sticky"></admin>
            <admin :contentType="'corporations'" :type="'WatchlistAllowlist'" :typeId="id" :sticky="sticky"></admin>
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
            sticky: 100,
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
        top: 51px;
        z-index: 2;
    }
</style>
