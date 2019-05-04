<!--
Input element to search for characters
 -->

<template>
<div class="input-group input-group-sm mb-1">
    <div class="input-group-prepend">
        <label class="input-group-text" for="characterSearchInput">Search Character</label>
    </div>
    <input type="text" class="form-control" id="characterSearchInput"
           placeholder="Name (min. 3 characters)" title="Name (min. 3 characters)"
           v-model="searchTerm" v-on:click="findCharacter" v-on:input="findCharacter($event.target.value)">
    <div class="input-group-append">
        <button class="btn" type="button" v-on:click="findCharacter('')">&times;</button>
    </div>
</div>
</template>

<script>
module.exports = {
    props: {
        swagger: Object,
    },

    data: function() {
        return {
            searchTerm: '',
        }
    },

    methods: {
        findCharacter(value) {
            if (typeof value === typeof '') {
                this.searchTerm = value;
            }

            if (this.searchTerm === '') {
                this.$emit('result', []);
            } else if (this.searchTerm.length >= 3) {
                this.doFindCharacter(this);
            }
        },

        doFindCharacter: window._.debounce((vm) => {
            vm.loading(true);
            new vm.swagger.CharacterApi().findBy(vm.searchTerm, function(error, data) {
                vm.loading(false);
                if (error) {
                    vm.$emit('result', []);
                    return;
                }
                vm.$emit('result', data);
            });
        }, 250),

    },
}
</script>

<style scoped>

</style>
