
window.Vue.mixin({
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
                //'Sketchy',
                'Slate',
                'Solar',
                'Spacelab',
                'Superhero',
                'United',
                'Yeti',
            ],
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
         * @param text
         * @param {string} type One of: error, warning, info or success
         */
        message: function(text, type) {
            switch (type) {
                case 'error':
                case 'info':
                case 'warning':
                    type = type === 'error' ? 'danger' : type;
                    this.$root.showMessage(text, type);
                    break;
                default: // success
                    this.$root.showMessage(text, type, 1500);
                    break;
            }
        },

        hasRole: function(name, player) {
            player = player || this.$root.player;
            if (! player) {
                return false;
            }
            return player.roles.indexOf(name) !== -1;
        },

        hasAnyRole: function(names) {
            for (let name of names) {
                if (this.hasRole(name)) {
                    return true;
                }
            }
            return false;
        },

        /**
         * @param {Date} date
         * @returns {string}
         */
        formatDate: function(date) {
            let str = date.toISOString();
            str = str.replace('T', ' ');
            str = str.replace('.000Z', '');
            return str.substr(0, str.length - 3);
        },

        /**
         * @param {int} characterId
         * @param {function} [callback]
         */
        updateCharacter: function(characterId, callback) {
            const vm = this;

            new this.swagger.CharacterApi().update(characterId, function(error, data, response) {
                if (error) { // usually 403 (from Core) or 503 (ESI down)
                    if (error.message) {
                        vm.message(error.message, 'error');
                    }
                    return;
                }
                if (response.statusCode === 204) {
                    vm.message(
                        'The character was removed because it was deleted or ' +
                        'no longer belongs to the same EVE account.',
                        'info'
                    );
                } else {
                    vm.message('Update done.', 'success');
                }
                if (typeof callback === typeof Function) {
                    callback();
                }
            });
        },

        /**
         * @param {int} characterId
         * @param {string|null} [adminReason]
         * @param {function} [callback]
         */
        deleteCharacter(characterId, adminReason, callback) {
            const vm = this;
            new this.swagger.PlayerApi().deleteCharacter(
                characterId,
                { adminReason: adminReason || '' },
                function(error) {
                    if (error) { // 403 usually
                        vm.message('Deletion denied.', 'error');
                        return;
                    }
                    vm.message('Deleted character.', 'success');
                    if (typeof callback === typeof Function) {
                        callback();
                    }
                }
            );
        },
    }
});
