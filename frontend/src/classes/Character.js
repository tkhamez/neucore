
import {CharacterApi, PlayerApi} from "neucore-js-client";

export default class Character {

    constructor(vm) {
        this.vm = vm;
    }

    /**
     * @param {int} characterId
     * @param {function} [callback]
     * @param {string} [successMessage]
     */
    updateCharacter(characterId, callback, successMessage) {
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
                self.vm.message(successMessage || 'Character updated.', 'success');
            }
            if (typeof callback === typeof Function) {
                callback();
            }
        });
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
