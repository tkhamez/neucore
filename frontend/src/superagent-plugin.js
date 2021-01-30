
export { superAgentPlugin as default };

/**
 * @param {object} vm
 * @param {function} setCsrfHeader
 * @returns {function}
 */
function superAgentPlugin(vm, setCsrfHeader) {
    return function (request) {
        setCsrfHeader(vm, request);

        vm.ajaxLoading(true);
        request.on('end', function () {
            vm.ajaxLoading(false);
        });

        return request;
    };
}
