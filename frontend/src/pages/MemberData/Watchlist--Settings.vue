<template>
<div>
    <add-entity ref="addEntityModal" v-on:success="addEntitySuccess()"></add-entity>

    <div class="card">
        <div  v-cloak v-if="! list.lockWatchlistSettings" class="card-header bg-light text-dark">
            <strong>Watchlist</strong>
        </div>
        <div v-cloak v-if="! list.lockWatchlistSettings" class="card-body">
            <p>
                Alliances and corporations whose members are included in the list if they
                also have characters in other (not NPC) corporations.
            </p>
            <admin :sticky="sticky" :contentType="'alliances'" :type="'Watchlist'" :typeId="list.id"></admin>
            <admin :sticky="sticky" :contentType="'corporations'" :type="'Watchlist'" :typeId="list.id"></admin>
        </div>

        <div class="card-header bg-light text-dark"><strong>Kicklist</strong></div>
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
            <admin ref="adminAllianceKick" :sticky="sticky"
                   :contentType="'alliances'" :type="'WatchlistKicklist'" :typeId="list.id"></admin>
            <admin ref="adminCorpKick" :sticky="sticky"
                   :contentType="'corporations'" :type="'WatchlistKicklist'" :typeId="list.id"></admin>
        </div>

        <div class="card-header bg-light text-dark"><strong>Allowlist</strong></div>
        <div class="card-body">
            <p>
                Alliances and corporations that should be treated like NPC corporations
                (usually personal alt corporations).
            </p>
            <admin :sticky="sticky"
                   :contentType="'alliances'" :type="'WatchlistAllowlist'" :typeId="list.id"></admin>
            <admin :sticky="sticky"
                   :contentType="'corporations'" :type="'WatchlistAllowlist'" :typeId="list.id"></admin>
        </div>
    </div>
</div>
</template>

<script>
import AddEntity from '../../components/EntityAdd.vue';
import Admin     from '../../components/EntityRelationEdit.vue';

export default {
    components: {
        AddEntity,
        Admin,
    },

    props: {
        list: Object,
    },

    data: function() {
        return {
            sticky: 87,
        }
    },

    methods: {
        showAddEntityModal (addType) {
            this.$refs.addEntityModal.showModal(addType);
        },

        addEntitySuccess () {
            this.$refs.adminAllianceKick.getSelectContent();
            this.$refs.adminCorpKick.getSelectContent();
        },
    }
}
</script>

<style scoped>
    .card-header {
        position: sticky;
        top: 51px;
        z-index: 2;
    }
</style>
