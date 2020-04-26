
export { superAgentPlugin as default };

function superAgentPlugin(vm) {
    return function (request) {
        vm.ajaxLoading(true);

        request.on('end', function () {
            vm.ajaxLoading(false);
        });

        return request;
    };
}
