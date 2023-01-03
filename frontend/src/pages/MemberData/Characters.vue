<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Characters</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <character-search v-if="h.hasRole('user-chars')" v-on:result="searchResult = $event"
                              :admin="true" :optionPlugin="true"></character-search>
            <character-result v-if="h.hasRole('user-chars')" :searchResult="searchResult"
                              :admin="true"></character-result>
        </div>
        <div class="col-lg-6 col2">
            <p>
                <label>
                    Paste a list of character names, one name per line.
                    <textarea class="form-control" v-model="input" rows="10"></textarea>
                </label>
                <br>
                <small class="text-muted">
                    This will only return alts that are included in the input,
                    not all alts from the account.
                </small>
                <br>
                <button type="submit" class="btn btn-primary" v-on:click.prevent="find()">Submit</button>
            </p>
            <table class="table table-hover table-sm mb-0" aria-describedby="Characters grouped by account">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Main</th>
                        <th scope="col">Alts</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="group in result">
                        <td class="text-nowrap">
                            <span v-if="group.player_id">
                                <a href="#" v-on:click.prevent="h.showCharacters(group.player_id)">
                                    {{ group.characters[0].name }}
                                </a>
                            </span>
                            <span v-else>[no player account]</span>
                        </td>
                        <td>
                            <span v-for="(character, index) in group.characters">
                                <span v-if="index > 0 || !group.player_id">
                                    {{ character.name }},
                                </span>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</template>

<script>
import {PlayerApi} from "neucore-js-client";
import Helper from "../../classes/Helper";
import CharacterSearch   from '../../components/CharacterSearch.vue';
import CharacterResult   from '../../components/CharacterResult.vue';

export default {
    components: {
        CharacterSearch,
        CharacterResult,
    },
    data() {
        return {
            h: new Helper(this),
            input: '',
            result: [],
            searchResult: [],
        }
    },
    methods: {
        find() {
            this.result = [];
            new PlayerApi().playerGroupCharactersByAccount(this.input, (error, data) => {
                if (! error) {
                    this.result = data;
                }
            })
        }
    }
}
</script>

<style scoped>
    @media (max-width: 991px) {
        .col2 {
            margin-top: 1rem;
        }
    }
</style>
