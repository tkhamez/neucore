
import portrait from './assets/portrait_32.jpg';

export default {
    data: function () {
        return {
            themes: [
                'Basic',
                'Cerulean',
                'Cosmo',
                'Cyborg',
                'Darkly',
                'Flatly',
                'Journal',
                'Litera',
                'Lumen',
                'Lux',
                'Materia',
                'Minty',
                'Pulse',
                'Sandstone',
                'Simplex',
                'Sketchy',
                'Slate',
                'Solar',
                'Spacelab',
                'Superhero',
                'United',
                'Yeti',
            ],

            messages: {
                errorRequiredForbiddenGroup: 'This player is not a member of any of the required groups or a member' +
                    ' of one of the forbidden groups.',
                itemNameAllowedCharsHelp: 'Allowed characters (no spaces): A-Z a-z 0-9 - . _',
            },

            loginNames: {
                default:    'core.default',
                alt:        'core.alt',
                managed:    'core.managed',
                managedAlt: 'core.managed-alt',
                mail:       'core.mail',
                director:   'core.director',
            }
        }
    },

    methods: {
        ajaxLoading: function(status) {
            if (status) {
                this.$root.loadingCount ++;
            } else {
                this.$root.loadingCount --;
            }
        },

        /**
         * @param {string} name
         * @param {string|null} [defaultValue]
         * @returns {string|null}
         */
        getHashParameter: function(name, defaultValue = null) {
            const parts = window.location.hash.substring(1).split('?');
            if (parts.length < 2) {
                return defaultValue;
            }
            const parameters = parts[1].split('&');
            for (let i = 0; i < parameters.length; i++) {
                const parameterParts = parameters[i].split('=');
                if (parameterParts[0] === name) {
                    if (!parameterParts[1]) {
                        return '';
                    }
                    return decodeURIComponent(parameterParts[1]);
                }
            }
            return defaultValue;
        },

        /**
         * @param {string} name
         */
        removeHashParameter: function(name) {
            const parts = window.location.hash.substring(1).split('?');
            if (parts.length < 2) {
                return null;
            }
            const remainingVariables = [];
            const parameters = parts[1].split('&');
            for (let i = 0; i < parameters.length; i++) {
                const parameterParts = parameters[i].split('=');
                if (parameterParts[0] !== name) {
                    remainingVariables.push(parameters[i]);
                }
            }
            window.location.hash = parts[0] + '?' + remainingVariables.join('&');
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
            str = str.substr(0, str.length - 3); // remove seconds
            if (dateOnly) {
                str = str.substr(0, 10); // remove time
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

        buildCharacterMovements(data) {
            const movements = [];
            for (const removed of data.removedCharacters) {
                if (removed.reason === 'moved') {
                    removed.reason = 'removed';
                    removed.playerName = removed.newPlayerName;
                    removed.playerId = removed.newPlayerId;
                }
                movements.push(removed);
            }
            for (const incoming of data.incomingCharacters) {
                if (incoming.reason === 'moved') {
                    incoming.reason = 'incoming';
                    incoming.playerName = incoming.player.name;
                    incoming.playerId = incoming.player.id;
                }
                movements.push(incoming);
            }
            return movements.sort((a, b) => a.removedDate - b.removedDate);
        },
    },
};
