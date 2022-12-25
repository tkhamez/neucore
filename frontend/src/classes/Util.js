
import _ from "lodash";
import {AllianceApi, CorporationApi} from "neucore-js-client";

export default class Util {

    /**
     * @param {string} name
     * @param {string|null} [defaultValue]
     * @returns {string|null}
     */
    static getHashParameter(name, defaultValue = null) {
        const parts = window.location.hash.substring(1).split('?');
        if (parts.length < 2) {
            return defaultValue;
        }
        const parameters = parts[1].split('&');
        for (const parameter of parameters) {
            const parameterParts = parameter.split('=');
            if (parameterParts[0] === name) {
                if (!parameterParts[1]) {
                    return '';
                }
                return decodeURIComponent(parameterParts[1]);
            }
        }
        return defaultValue;
    }

    /**
     * @param {object} event
     * @param {string} [type]
     */
    static addHighlight(event, type) {
        type = type || 'info';
        event.target.classList.add(`text-${type}`);
    }

    /**
     * @param {object} event
     * @param {string} [type]
     */
    static removeHighlight(event, type) {
        type = type || 'info';
        event.target.classList.remove(`text-${type}`);
    }

    /**
     * @param {HTMLElement|string} elementOrSelectors
     */
    static isVisible(elementOrSelectors) {
        let elem = elementOrSelectors;
        if (typeof elementOrSelectors === typeof '') {
            elem = document.querySelector(elementOrSelectors);
        }
        // The following line is from jQuery.expr.pseudos.visible
        return !!(elem.offsetWidth || elem.offsetHeight || elem.getClientRects().length);
    }

    /**
     * @param {string} name
     */
    static removeHashParameter(name) {
        const parts = window.location.hash.substring(1).split('?');
        if (parts.length < 2) {
            return null;
        }
        const remainingVariables = [];
        const parameters = parts[1].split('&');
        for (const parameter of parameters) {
            const parameterParts = parameter.split('=');
            if (parameterParts[0] !== name) {
                remainingVariables.push(parameter);
            }
        }
        window.location.hash = `${parts[0]}?${remainingVariables.join('&')}`;
    }

    /**
     * @param {Date|null} date
     * @param {boolean} [dateOnly]
     * @returns {string}
     */
    static formatDate(date, dateOnly) {
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
    }

    /**
     * Builds a list from the "id" property of an object.
     *
     * @param {object} model With a property "id".
     * @returns {array}
     */
    static buildIdList(model) {
        const ids = [];
        for (const item of model) {
            ids.push(item.id);
        }
        return ids;
    }

    /**
     * Builds a comma separated string from the "id" property of an object.
     *
     * @param {object} model With a property "id".
     * @returns {string}
     */
    static buildIdString(model) {
        return this.buildIdList(model).join(',');
    }

    static findCorporationsOrAlliancesDelayed = _.debounce((query, type, callback) => {
        if (['Corporations', 'Alliances'].indexOf(type) === -1) {
            return;
        }

        if (typeof query !== typeof '') {
            return;
        }
        if (query === '') {
            callback([]);
        }
        if (query.length < 3) {
            return;
        }

        const api = type === 'Corporations' ? new CorporationApi() : new AllianceApi();
        const method = type === 'Corporations' ? 'userCorporationFind' : 'userAllianceFind';

        api[method].apply(api, [query, (error, data) => {
            if (error) {
                callback([]);
                return;
            }
            callback(data);
        }]);
    }, 250);
}
