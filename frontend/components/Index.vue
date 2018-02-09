<template>
  <!-- TODO make this pretty -->
	<div class="page-container">
		<md-app>
			<md-app-toolbar class="md-primary">
				<span class="md-title">My Title</span>
			</md-app-toolbar>
			<md-app-content>
        <!-- TODO should have a loading spinner while we attempt to fetch user, if we have an authorization header -->
				<user-component v-if="user"/>
				<login-component v-else/>
			</md-app-content>
		</md-app>
	</div>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import LoginComponent from "./Login.vue";
import UserComponent from "./User.vue";

export default Vue.extend({
  props: [],
  data() {
    return {};
  },
  created() {},
  methods: {},
  computed: {
    user(): User {
      return this.$store.state.user;
    }
  },
  components: {
    LoginComponent,
    UserComponent
  }
});
</script>