import Data from "./Data";
import portrait from "../assets/portrait_32.jpg";

/**
 * Helper functions that need the vue instance.
 */
export default class Helper {

    constructor(vm) {

        /**
         * The vue instance
         */
        this.vm = vm;
    }

    ajaxLoading(status) {
        if (status) {
            this.vm.globalStore.increaseLoadingCount();
        } else {
            this.vm.globalStore.decreaseLoadingCount();
        }
    }

    fetch(resource, options) {
        this.ajaxLoading(true);
        return window.fetch(resource, options)
            .then(response => {
                this.ajaxLoading(false);
                return new Promise(resolve => resolve(response));
            })
            .catch(error => {
                this.ajaxLoading(false);
                return new Promise((resolve, reject) => reject(error));
            });
    }

    hasRole(name, player) {
        player = player || this.vm.$root.player;
        if (! player) {
            return false;
        }
        return player.roles.indexOf(name) !== -1;
    }

    hasAnyRole(names) {
        for (const name of names) {
            if (this.hasRole(name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param {string} text
     * @param {string} [type] One of: error, warning, info or success
     * @param {number} [timeout]
     */
    message(text, type, timeout) {
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
        this.vm.emitter.emit('message', { text: text, type: type, timeout: timeout });
    }

    showCharacters(playerId) {
        this.vm.emitter.emit('showCharacters', playerId);
    }

    characterPortrait(id, size) {
        if (this.vm.$root.settings.esiDataSource === 'singularity') {
            // there are no character images on Sisi at the moment.
            return portrait;
        }
        return `${Data.envVars.eveImageServer}/characters/${id}/portrait?size=${size}&tenant=tranquility`;
    }
}
