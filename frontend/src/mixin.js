
import portrait from './assets/portrait_32.jpg';

export default {

    methods: {
        ajaxLoading: function(status) {
            if (status) {
                this.$root.loadingCount ++;
            } else {
                this.$root.loadingCount --;
            }
        },

        /**
         * @param {string} text
         * @param {string} [type] One of: error, warning, info or success
         * @param {number} [timeout]
         */
        message: function(text, type, timeout) {
            type = type || 'info';
            switch (type) {
                case 'error':
                case 'info':
                case 'warning':
                    type = type === 'error' ? 'danger' : type;
                    timeout = timeout || null;
                    break;
                default: // success
                    timeout = timeout || 1500;
                    break;
            }
            this.emitter.emit('message', { text: text, type: type, timeout: timeout });
        },

        showCharacters: function(playerId) {
            this.emitter.emit('showCharacters', playerId);
        },

        hasRole: function(name, player) {
            player = player || this.$root.player;
            if (! player) {
                return false;
            }
            return player.roles.indexOf(name) !== -1;
        },

        hasAnyRole: function(names) {
            for (const name of names) {
                if (this.hasRole(name)) {
                    return true;
                }
            }
            return false;
        },

        /**
         * @param {Date|null} date
         * @param {boolean} [dateOnly]
         * @returns {string}
         */
        formatDate: function(date, dateOnly) {
            if (!date) {
                return '';
            }
            let str = date.toISOString();
            str = str.replace('T', ' ').replace('.000Z', '');
            str = str.substring(0, str.length - 3); // remove seconds
            //str = str.substring(0, str.length - 3); // remove seconds
            if (dateOnly) {
                str = str.substring(0, 10); // remove time
            }
            return str;
        },

        characterPortrait(id, size) {
            if (this.$root.settings.esiDataSource === 'singularity') {
                // there are no character images on Sisi at the moment.
                return portrait;
            }
            return `${this.$root.envVars.eveImageServer}/characters/${id}/portrait?size=${size}&tenant=tranquility`;
        },

    },
};
