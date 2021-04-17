
export { superAgentPlugin as default };

/**
 * @param {object} vm
 * @param {function} setCsrfHeader
 * @returns {function}
 */
function superAgentPlugin(vm, setCsrfHeader) {
    return function (request) {
        setCsrfHeader(vm, request);

        request.withCredentials();

        vm.ajaxLoading(true);
        request.on('end', function () {
            vm.ajaxLoading(false);
        });

        return request;
    };
}
