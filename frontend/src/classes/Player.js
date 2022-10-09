
import {ServiceApi} from "neucore-js-client";
import Character from "./Character";
import Helper from "./Helper";

export default class Player {

    constructor(vm) {
        this.vm = vm;
        this.helper = new Helper(vm);
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
                character.updateCharacter(id, () => {
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
            if (player.id === self.vm.globalStore.state.player.id) {
                self.vm.emitter.emit('playerChange');
            }
        }

        updateCharacters();
    }

    /**
     * Update all service accounts of the player.
     *
     * @param {object} player
     * @param {function} callback
     */
    updateServices(player, callback) {
        const self = this;
        new ServiceApi().serviceUpdateAllAccounts(player.id, (error, data) => {
            if (error) {
                self.helper.message('Failed to update the service accounts.', 'error');
                return;
            }
            self.helper.message(`Updated ${data} service account(s).`, 'success', 3000);
            callback();
            if (player.id === self.vm.globalStore.state.player.id) {
                self.vm.emitter.emit('playerChange');
            }
        });
    }
}
