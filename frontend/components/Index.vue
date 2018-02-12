<template>
	<div class="page-container">
		<md-app>
			<md-app-toolbar class="md-primary">
				<div class="md-toolbar-row">
					<div class="md-toolbar-section-start">
						<span class="md-title">Brave Core</span>
					</div>
					<div class="md-toolbar-section-end">
						<md-button class="md-accent" v-on:click="logout">Logout</md-button>
					</div>
				</div>
			</md-app-toolbar>
			<md-app-content>
				<!-- TODO should have a loading spinner while we attempt to fetch user, if we have an authorization header -->
				<home-component v-if="user"/>
				<login-component v-else/>
			</md-app-content>
		</md-app>
	</div>
</template>

<style lang="scss" scoped>
@import "~vue-material/dist/theme/engine"; // Import theme engine
@include md-register-theme("default", (
	primary: rgb(27, 123, 164),
	theme: dark
));
@import "~vue-material/dist/theme/all"; // apply theme

.page-container {
  display: flex;
  flex: 1;
}
.md-app {
  flex: 1;
}
</style>

<script lang="ts">
import Vue from "vue";
import LoginComponent from "./Login.vue";
import HomeComponent from "./Home.vue";
import { userLogout } from "../api";

export default Vue.extend({
  props: [],
  data() {
    return {};
  },
  created() {},
  methods: {
    logout: async () => {
      await userLogout();
      location.reload();
    }
  },
  computed: {
    user(): User {
      return this.$store.state.user;
    }
  },
  components: {
    LoginComponent,
    HomeComponent
  }
});
</script>