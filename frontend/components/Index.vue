<template>
	<div class="page-container">
		<md-app>
			<md-app-toolbar class="md-primary">
				<span class="md-title">My Title</span>
			</md-app-toolbar>
			<md-app-content>
				<login-component/>
			</md-app-content>
		</md-app>
	</div>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import LoginComponent from "./Login.vue";

export default Vue.extend({
  props: [],
  data() {
    return {};
  },
  created() {},
  methods: {},
  computed: {},
  components: {
    LoginComponent
  }
});
</script>