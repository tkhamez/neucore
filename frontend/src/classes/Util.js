
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
}
