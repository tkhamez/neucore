<template>
	<md-card md-with-hover>
    <md-card-content>
      <a v-bind:href="loginRedirect">
        <img src='/images/EVE_SSO_Login_Buttons_Large_Black.png'>
      </a>
    </md-card-content>
  </md-card>
</template>

<style lang="scss" scoped>

</style>

<script lang="ts">
import Vue from "vue";
import { userAuthLoginGet } from "../api";

export default Vue.extend({
  props: [],
  data() {
    return {
      loginRedirect: ""
    };
  },
  async created() {
    this.loginRedirect = await userAuthLoginGet();
  },
  methods: {},
  computed: {}
});
</script>