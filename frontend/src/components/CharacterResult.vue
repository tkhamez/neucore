<!--
Result table for the character search
 -->

<template>
    <div v-cloak v-if="searchResult.length > 0" class="search-result border bg-body">
        <table class="table table-hover table-sm mb-0" aria-describedby="search result">
            <thead>
                <tr>
                    <th scope="col">Character</th>
                    <th scope="col">Account</th>
                    <th scope="col" v-if="withCharacters">Characters</th>
                    <th scope="col" v-if="selectedPlayers">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="char in searchResult">
                    <td>
                        <img :src="characterPortrait(char.character_id, 32)" alt="portrait">
                        {{ char.character_name }}
                    </td>
                    <td>{{ char.player_name }} #{{ char.player_id }}</td>
                    <td v-if="withCharacters">
                        <button class="btn btn-info btn-sm" v-on:click="showCharacters(char.player_id)">
                            Show characters
                        </button>
                    </td>
                    <td v-if="selectedPlayers">
                        <button v-if="! isSelected(char.player_id)" class="btn btn-success btn-sm"
                                @click="$emit('add', char.player_id)">Add to group</button>
                        <button v-if="isSelected(char.player_id)" class="btn btn-danger btn-sm"
                                @click="$emit('remove', char.player_id)">Remove from group</button>
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
        withCharacters: Boolean
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
