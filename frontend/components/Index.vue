<template>
	<div class="page-container">
		<md-app>
			<md-app-toolbar class="md-primary">
				<span class="md-title">My Title</span>
			</md-app-toolbar>
			<md-app-content>
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
    user() {
      console.log(this.$store.state);
      return this.$store.state.user;
    }
  },
  components: {
    LoginComponent,
    UserComponent
  }
});
</script>