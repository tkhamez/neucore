
<template>
    <div>
      {{ user }}
    </div>
</template>

<script lang="ts">
import Vue from "vue";

export default Vue.extend({
  props: ["user"],
  data() {
    return {};
  },
  created() {},
  methods: {},
  computed: {}
});
</script>

<style>
.greeting {
  font-size: 20px;
}
</style>