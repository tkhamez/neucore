
export { superAgentPlugin as default };

/**
 * @param {Helper} helper
 * @returns {function}
 */
function superAgentPlugin(helper) {
    return request => {
        if (['POST', 'PUT', 'DELETE'].indexOf(request.method) !== -1) {
            request.set('X-CSRF-Token', helper.vm.csrfToken);
        }

        request.withCredentials();

        helper.ajaxLoading(true);

        // The request is settled exactly once: on "end" (any HTTP response was received) or on
        // "error"/"abort" (network error, CORS error, timeout, abort). Without the "error" and
        // "abort" listeners, the loading counter would not be decreased in those cases, leaving
        // the spinner stuck.
        let settled = false;
        const settle = () => {
            if (!settled) {
                settled = true;
                helper.ajaxLoading(false);
            }
        };
        request.on('end', settle);
        request.on('error', settle);
        request.on('abort', settle);

        return request;
    };
}
