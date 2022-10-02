<template>
<div class="container-fluid">

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Role Administration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 sticky-column">
            <div class="card border-secondary mb-3">
                <h4 class="card-header">Roles</h4>
                <div class="list-group">
                    <span v-for="roleName in availableRoles" class="list-item-wrap">
                        <a class="list-group-item list-group-item-action"
                           :class="{ active: currentRoleName === roleName }"
                           :href="`#RoleAdmin/${roleName}`">
                            {{ roleName }}
                        </a>
                    </span>
                </div>
            </div>
        </div>
        <div v-cloak v-if="currentRoleName" class="col-lg-8">
            <div class="card border-secondary mb-3" >
                <div class="card-header">
                    <h4>{{ currentRoleName }}</h4>
                </div>
            </div>

            <admin v-cloak v-if="currentRoleName" ref="admin"
                   :type="'Role'" :typeName="currentRoleName" :contentType="'requiredGroups'"></admin>

        </div>
    </div>
</div>
</template>

<script>
import Data from "../../classes/Data";
import Admin from '../../components/EntityRelationEdit.vue';

export default {
    components: {
        Admin,
    },

    props: {
        route: Array,
    },

    data() {
        return {
            currentRoleName: '',
            availableRoles: Data.userRoles.filter(val =>
                // These roles are only assigned based on groups.
                ['tracking', 'watchlist', 'watchlist-manager'].indexOf(val) === -1
            ),
        }
    },

    mounted() {
        window.scrollTo(0,0);
        setRoleName(this);
    },

    watch: {
        route() {
            setRoleName(this);
        },
    },
}

function setRoleName(vm) {
    vm.currentRoleName = '';
    const currentRoleName = vm.route[1] ? vm.route[1] : null;
    if (vm.availableRoles.indexOf(currentRoleName) !== -1) {
        vm.currentRoleName = currentRoleName;
    }
}

</script>

<style scoped>
</style>
