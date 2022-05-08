
import {ServiceApi} from "neucore-js-client";
import Character from "./Character";

export default class Player {

    constructor(vm) {
        this.vm = vm;
    }

    /**
     * Updates all characters and groups of the player.
     *
     * @param {object} player
     * @param {function} [callback]
     */
    updatePlayer(player, callback) {
        const self = this;
        const character = new Character(self.vm);
        const characters = [...player.characters];
        const totalCharacters = characters.length;

        function updateCharacters() {
            if (characters.length > 0) {
                const id = characters[0].id;
                characters.splice(0, 1);
                character.updateCharacter(id, function() {
                    updateCharacters();
                }, `Character ${totalCharacters - characters.length}/${totalCharacters} updated.`);
            } else {
                charactersUpdateComplete()
            }
        }

        function charactersUpdateComplete() {
            if (typeof callback === typeof Function) {
                callback();
            }
            if (player.id === self.vm.$root.player.id) {
                self.vm.emitter.emit('playerChange');
            }
        }

        updateCharacters();
    }

    /**
     * Update all service accounts of the player.
     *
     * @param {object} player
     */
    updateServices(player) {
        const self = this;
        new ServiceApi().serviceUpdateAllAccounts(player.id, (error, data) => {
            if (error) {
                self.vm.message('Failed to update the service accounts.', 'error');
                return;
            }
            self.vm.message(`Updated ${data.length} service account(s).`, 'success', 3000);
            if (player.id === self.vm.$root.player.id) {
                self.vm.emitter.emit('playerChange');
            }
        });
    }
}
