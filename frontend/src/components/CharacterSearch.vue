<!--
Input element to search for characters
 -->

<template>
<div class="input-group input-group-sm mb-1">
    <div class="input-group-prepend">
        <label class="input-group-text" for="characterSearchInput">
            Search {{ admin ? 'Character' : 'Player' }}
        </label>
    </div>
    <input type="text" class="form-control" id="characterSearchInput" ref="searchInput"
           placeholder="Name (min. 3 characters)" title="Name (min. 3 characters)"
           v-model="searchTerm" v-on:click="findCharacter" v-on:input="findCharacter($event.target.value)">
    <div class="input-group-append">
        <button class="btn" type="button" v-on:click="findCharacter('')" title="Clear input">&times;</button>
    </div>
</div>
</template>

<script>
import _  from 'lodash';
import {CharacterApi} from 'neucore-js-client';

export default {
    props: {
        admin: Boolean, // false = search only for mains, otherwise all characters
        currentOnly: Boolean, // false = include renamed and moved characters or not (only for admin=true)
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
                this.$refs.searchInput.focus();
            } else {
                findCharacter(this);
            }
        },
    },
}

const findCharacter = _.debounce((vm) => {
    if (vm.searchTerm.length < 3) {
        return;
    }
    const api = new CharacterApi();
    const callback = (error, data) => {
        if (error) {
            vm.$emit('result', []);
            return;
        }
        vm.$emit('result', data);
    };
    if (vm.admin) {
        api.findCharacter(vm.searchTerm, { currentOnly: vm.currentOnly ? 'true' : 'false' }, callback);
    } else {
        api.findPlayer(vm.searchTerm, callback);
    }
}, 250);
</script>
