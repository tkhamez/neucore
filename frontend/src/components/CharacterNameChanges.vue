<!--
Tooltip with list of name changes.
 -->

<template>
    <span v-if="character.characterNameChanges.length > 0"
          class="character-name-changes" data-toggle="tooltip"
          :title="getNameChangesHtml(character.characterNameChanges)">
        <span role="img" class="fas fa-info-circle"></span>
    </span>
</template>

<script>
import $ from "jquery";

export default {
    props: {
        character: Object,
    },

    mounted () {
        window.setTimeout(() => {
            $('.character-name-changes[data-toggle="tooltip"]').tooltip({
                html: true,
                customClass: 'character-name-changes'
            });
        }, 100);
    },

    methods: {

        /**
         * @param {array} characterNameChanges
         * @returns {string}
         */
        getNameChangesHtml(characterNameChanges) {
            const vm = this;
            let html = 'Character name changes:<br>';
            for (const change of characterNameChanges) {
                html += `
                    ${vm.$root.formatDate(change.changeDate, true)}
                    <strong>${change.oldName}</strong>
                    <br>
                `;
            }
            return html;
        }
    }
}
</script>

<!--suppress CssUnusedSymbol -->
<style>
    .character-name-changes .tooltip-inner {
        max-width: initial;
        text-align: left;
    }
</style>
