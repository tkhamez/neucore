
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
        request.on('end', () => {
            helper.ajaxLoading(false);
        });

        return request;
    };
}
