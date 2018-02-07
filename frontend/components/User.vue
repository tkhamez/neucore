
<template>
    <div>

    </div>
</template>

<script lang="ts">
import Vue from "vue";
import { UserApi, User } from "../api";
const userApi = new UserApi();

export default Vue.extend({
  props: [],
  data() {
    return {};
  },
  created() {
    this.fetchUser();
  },
  methods: {
    fetchUser(): Promise<User> {
      return userApi.userInfoGet();
    }
  },
  computed: {}
});
</script>

<style>
.greeting {
  font-size: 20px;
}
</style>