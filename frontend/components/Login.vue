<template>
	<md-card md-with-hover>
		<md-ripple>
			<md-card-content>
				<a v-bind:href="loginRedirect">
					<img src='/images/EVE_SSO_Login_Buttons_Large_Black.png'>
				</a>
			</md-card-content>
		</md-ripple>
	</md-card>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import { SSOApi } from "../api";
const SSO = new SSOApi();

export default Vue.extend({
  props: [],
  data() {
    return {
      loginRedirect: ""
    };
  },
  async created() {
    // TODO should get callback URL from environment
    const { oauth_url } = await SSO.userAuthLoginGet({
      redirectUrl: "https://localhost/api/user/auth/result"
    });
    this.loginRedirect = oauth_url;
  },
  methods: {},
  computed: {}
});
</script>