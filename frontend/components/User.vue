
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
    async fetchUser(): Promise<User | null> {
      try {
        const user = await userApi.userInfoGet();
        console.log("user", user);
        return user;
      } catch (err) {
        // handle case the user is not logged in
        return null;
      }
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