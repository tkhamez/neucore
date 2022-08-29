
export default class Helper {

    constructor(vm) {
        this.vm = vm;
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
}
