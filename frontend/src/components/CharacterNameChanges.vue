<!--
Tooltip with list of name changes.
 -->

<template>
    <span v-if="character.characterNameChanges.length > 0"
          data-bs-toggle="tooltip" data-bs-html="true" data-bs-custom-class="character-name-changes"
          :title="getNameChangesHtml(character.characterNameChanges)">
        <span role="img" class="fas fa-info-circle"></span>
    </span>
</template>

<script>
import Util from "../classes/Util";

export default {
    props: {
        character: Object,
    },

    methods: {

        /**
         * @param {array} characterNameChanges
         * @returns {string}
         */
        getNameChangesHtml(characterNameChanges) {
            let html = 'Character name changes:<br>';
            for (const change of characterNameChanges) {
                html += `
                    ${Util.formatDate(change.changeDate, true)}
                    <strong>${change.oldName}</strong>
                    <br>
                `;
            }
            return html;
        }
    }
}
</script>
