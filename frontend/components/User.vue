
<template>
    <div>
      {{ user }}
    </div>
</template>

<script lang="ts">
import Vue from "vue";
import { User } from "../api";

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
  }
});
</script>

<style>
.greeting {
  font-size: 20px;
}
</style>