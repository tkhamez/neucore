
import {CharacterApi, PlayerApi} from "neucore-js-client";
import Helper from "./Helper";

export default class Character {

    static buildCharacterMovements(data) {
        if (!data) {
            return [];
        }
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
    }

    constructor(vm) {
        this.vm = vm;
        this.helper = new Helper(vm);
    }

    /**
     * @param {int} characterId
     * @param {function} [callback]
     * @param {string} [successMessage]
     */
    updateCharacter(characterId, callback, successMessage) {
        const self = this;
        new CharacterApi().update(characterId, (error, data, response) => {
            if (error) { // usually 403 (from Core) or 503 (ESI down)
                if (error.message) {
                    self.helper.message(error.message, 'error');
                }
                return;
            }
            if (response.statusCode === 204) {
                self.helper.message(
                    'The character was removed because it was deleted or ' +
                    'no longer belongs to the same EVE account.',
                    'info'
                );
            } else {
                self.helper.message(successMessage || 'Character updated.', 'success');
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
            error => {
                if (error) { // 403 usually
                    self.helper.message('Deletion denied.', 'error');
                    return;
                }
                self.helper.message('Deleted character.', 'success');
                if (typeof callback === typeof Function) {
                    callback();
                }
            }
        );
    }
}
