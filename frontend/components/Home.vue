<template>
	<div>
		<md-card class="md-elevation-6">
	 		<md-card-content>
				 <user-component :user="user"/>
			</md-card-content>
		</md-card>
		<template v-for="group in groups">
			<group-component :group="group" :user="user"/>
		</template>
	</div>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import UserComponent from "./User.vue";
import GroupComponent from "./Group.vue";

export default Vue.extend({
  props: [],
  data() {
    return {
      loginRedirect: ""
    };
  },
  methods: {},
  computed: {
    user(): User {
      return this.$store.state.user;
    },
    groups(): Group[] {
      return this.$store.state.groups;
    }
  },
  components: {
    UserComponent,
    GroupComponent
  }
});
</script>