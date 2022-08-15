
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
}
