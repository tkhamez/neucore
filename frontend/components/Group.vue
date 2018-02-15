<template>
	<!--
		TODO:
			should re-use User component for the list of users in a group
			should be able to change roles for users (if you have permission)
			should be able to remove users
		-->
	<md-card md-card class="group md-elevation-6">
		<md-table v-model="members">
			<md-table-toolbar>
				<h1 class="md-title">{{ group.name }}</h1>
			</md-table-toolbar>
			<md-table-row slot="md-table-row" slot-scope="{ item }">
				<md-table-cell md-label="Name">{{ item.name }}</md-table-cell>
				<md-table-cell md-label="Roles">{{ item.roles }}</md-table-cell>
			</md-table-row>
		</md-table>
		<md-divider/>
		<md-card v-if="isAdmin" class="md-elevation-0">
			<md-card-header>
				Add a Member
			</md-card-header>
			<md-card-content>
				TODO user dropdown
			</md-card-content>
			<md-card-actions>
				<md-button v-on:click="addUser">Add</md-button>
			</md-card-actions>
		</md-card>
	</md-card>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import UserComponent from "./User.vue";

export default Vue.extend({
  props: ["group", "user"],
  data() {
    return {};
  },
  created() {},
  methods: {
    async addUser(): Promise<void> {
      // TODO commit new user to vuex
      console.log("foobar");
    }
  },
  computed: {
    isAdmin(): boolean {
      return this.group.admins.includes(this.user.characterId);
    },
    members(): User[] {
      return this.$store.state.allUsers.filter((user: User) =>
        this.group.members.includes(user.characterId)
      );
    },
    canAddMembers(): User[] {
      return this.$store.state.allUsers.filter(
        (user: User) => !this.group.members.includes(user.characterId)
      );
    }
  },
  components: {
    UserComponent
  }
});
</script>
