<!--
Result table for the character search
 -->

<template>
    <div v-cloak v-if="searchResult.length > 0" class="search-result border bg-body">
        <table class="table table-hover table-sm mb-0" aria-describedby="search result">
            <thead>
                <tr>
                    <th scope="col" v-if="! admin">Player ID</th>
                    <th scope="col">{{ ! admin ? 'Main' : '' }} Character</th>
                    <th scope="col" v-if="admin">Player Account</th>
                    <th scope="col" v-if="selectedPlayers">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="char in searchResult">
                    <td v-if="! admin">{{ char.player_id }}</td>
                    <td>
                        <img :src="characterPortrait(char.character_id, 32)" alt="portrait">
                        {{ char.character_name }}
                    </td>
                    <td v-if="admin">
                        <a href="#" v-on:click.prevent="showCharacters(char.player_id)">{{ char.player_name }}</a>
                        #{{ char.player_id }}
                    </td>
                    <td v-if="selectedPlayers">
                        <button v-if="! isSelected(char.player_id)" class="btn btn-success btn-sm"
                                @click="$emit('add', char.player_id)">Add</button>
                        <button v-if="isSelected(char.player_id)" class="btn btn-danger btn-sm"
                                @click="$emit('remove', char.player_id)">Remove</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    props: {
        selectedPlayers: Array,
        searchResult: Array,
        admin: Boolean // false = search only for "mains", otherwise all characters and add "alts" modal button
    },
    methods: {
        isSelected (playerId) {
            if (! this.selectedPlayers) {
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
