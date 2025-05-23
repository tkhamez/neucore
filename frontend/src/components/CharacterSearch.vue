<!--
Input element to search for characters
 -->

<template>
    <div class="input-group input-group-sm mb-1">
        <label class="input-group-text" for="characterSearchInput">
            Add {{ admin ? 'Character' : 'Player' }}
        </label>
        <input type="text" class="form-control" id="characterSearchInput" ref="searchInput"
               placeholder="Name (min. 3 characters)" title="Name (min. 3 characters)"
               v-model="searchTerm" v-on:click="findCharacter" v-on:input="findCharacter($event.target.value)">
        <button class="btn" type="button" v-on:click="findCharacter('')" title="Clear input">&times;</button>
    </div>
    <label v-cloak v-if="optionPlugin" class="mb-2">
        <input type="checkbox" v-model="plugin"> Include results from service plugins.
    </label>
</template>

<script>
import _  from 'lodash';
import {CharacterApi} from 'neucore-js-client';

export default {
    props: {
        admin: Boolean, // false = search only for mains, otherwise all characters
        optionPlugin: Boolean, // Include results from plugins or not. (needs admin = true)
        currentOnly: Boolean, // false = include renamed and moved characters or not (only for admin=true)
    },

    emits: ['result'],

    data() {
        return {
            searchTerm: '',
            plugin: false,
        }
    },

    watch: {
        plugin() {
            this.findCharacter(this.searchTerm)
        }
    },

    methods: {
        findCharacter(value) {
            if (typeof value === typeof '') {
                this.searchTerm = value;
            }

            if (this.searchTerm === '' || this.searchTerm.length < 3) {
                this.$emit('result', []);
                this.$refs.searchInput.focus();
            } else {
                findCharacter(this);
            }
        },
    },
}

const findCharacter = _.debounce(vm => {
    const api = new CharacterApi();
    const callback = (error, data) => {
        if (error) {
            vm.$emit('result', []);
            return;
        }
        vm.$emit('result', data);
    };
    const query = encodeURIComponent(vm.searchTerm);
    if (vm.admin) {
        api.findCharacter(query, {
            currentOnly: vm.currentOnly ? 'true' : 'false',
            plugin: vm.plugin ? 'true' : 'false',
        }, callback);
    } else {
        api.findPlayer(query, callback);
    }
}, 250);
</script>
