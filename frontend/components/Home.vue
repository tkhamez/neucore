<template>
	<div>
		<md-card>
	 		<md-card-content>
				 <user-component :user="user"/>
			</md-card-content>
		</md-card>
	</div>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import UserComponent from "./User.vue";

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
    }
  },
  components: {
    UserComponent
  }
});
</script>