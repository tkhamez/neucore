
import {CharacterApi, PlayerApi} from "neucore-js-client";

export default class Character {

    constructor(vm) {
        this.vm = vm;
    }

    /**
     * @param {int} characterId
     * @param {function} [callback]
     */
    updateCharacter(characterId, callback) {
        const self = this;
        new CharacterApi().update(characterId, function(error, data, response) {
            if (error) { // usually 403 (from Core) or 503 (ESI down)
                if (error.message) {
                    self.vm.message(error.message, 'error');
                }
                return;
            }
            if (response.statusCode === 204) {
                self.vm.message(
                    'The character was removed because it was deleted or ' +
                    'no longer belongs to the same EVE account.',
                    'info'
                );
            } else {
                self.vm.message('Update done.', 'success');
            }
            if (typeof callback === typeof Function) {
                callback();
            }
        });
    }

    /**
     * Updates all characters of the player.
     *
     * @param {object} player
     * @param {function} [callback]
     */
    updatePlayer(player, callback) {
        const self = this;
        const characters = [...player.characters];

        function updateCharacters() {
            if (characters.length > 0) {
                const id = characters[0].id;
                characters.splice(0, 1);
                self.updateCharacter(id, function() {
                    updateCharacters();
                });
            } else {
                if (typeof callback === typeof Function) {
                    callback();
                }
                if (player.id === self.vm.$root.player.id) {
                    self.vm.emitter.emit('playerChange');
                }
            }
        }

        updateCharacters();
    }

    /**
     * @param {int} characterId
     * @param {string|null} [adminReason]
     * @param {function} [callback]
     */
    deleteCharacter(characterId, adminReason, callback) {
        const self = this;
        new PlayerApi().deleteCharacter(
            characterId,
            { adminReason: adminReason || '' },
            function(error) {
                if (error) { // 403 usually
                    self.vm.message('Deletion denied.', 'error');
                    return;
                }
                self.vm.message('Deleted character.', 'success');
                if (typeof callback === typeof Function) {
                    callback();
                }
            }
        );
    }
}
