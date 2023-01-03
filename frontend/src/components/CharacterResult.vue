<!--
Result table for the character search
 -->

<template>
    <div v-cloak v-if="searchResult.length > 0" class="search-result border bg-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" aria-describedby="search result">
                <thead>
                    <tr>
                        <th scope="col">{{ !admin ? 'Main' : '' }} Character</th>
                        <th scope="col" v-if="h.hasRole('user-chars')">Player Account</th>
                        <th scope="col" v-if="selectedPlayers">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="char in searchResult">
                        <td>
                            <img :src="h.characterPortrait(char.characterId, 32)" alt="portrait">
                            {{ char.characterName }}
                        </td>
                        <td v-if="h.hasRole('user-chars')">
                            <a href="#" v-on:click.prevent="h.showCharacters(char.playerId)">
                                {{ char.playerName }}</a> #{{ char.playerId }}
                        </td>
                        <td v-if="selectedPlayers">
                            <button v-if="!isSelected(char.playerId)" class="btn btn-success btn-sm"
                                    @click="$emit('add', char.playerId)">Add</button>
                            <button v-if="isSelected(char.playerId)" class="btn btn-danger btn-sm"
                                    @click="$emit('remove', char.playerId)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import Helper from "../classes/Helper";

export default {
    props: {
        selectedPlayers: Array,
        searchResult: Array,
        admin: Boolean // false = search only for "mains", otherwise all characters and add "alts" modal button
    },

    data() {
        return {
            h: new Helper(this),
        }
    },

    methods: {
        isSelected(playerId) {
            if (!this.selectedPlayers) {
                return false;
            }
            for (const member of this.selectedPlayers) {
                if (member.id === playerId) {
                    return true;
                }
            }
            return false;
        },
    }
}
</script>
