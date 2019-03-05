
<template>
<div class="input-group input-group-sm mb-1">
    <div class="input-group-prepend">
        <span class="input-group-text">Search Character</span>
    </div>
    <input type="text" class="form-control"
       placeholder="Name (min. 3 characters)" title="Name (min. 3 characters)"
        v-model="searchTerm" v-on:click="findCharacter">
    <div class="input-group-append">
        <button class="btn" type="button" v-on:click="searchTerm = ''">&times;</button>
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

    watch: {
        searchTerm: function() {
            this.findCharacter();
        }
    },

    methods: {

        findCharacter() {
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
