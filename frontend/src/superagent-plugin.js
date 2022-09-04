
export { superAgentPlugin as default };

/**
 * @param {Helper} helper
 * @param {function} setCsrfHeader
 * @returns {function}
 */
function superAgentPlugin(helper, setCsrfHeader) {
    return function (request) {
        setCsrfHeader(helper.vm, request);

        request.withCredentials();

        helper.ajaxLoading(true);
        request.on('end', function () {
            helper.ajaxLoading(false);
        });

        return request;
    };
}
