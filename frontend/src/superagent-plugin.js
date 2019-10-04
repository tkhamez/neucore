
module.exports = function (vm) {
    return function (request) {
        vm.ajaxLoading(true);

        request.on('end', function () {
            vm.ajaxLoading(false);
        });

        /*
        const end = request.end;
        request.end = function(callback) {
            if (typeof callback !== 'function') {
                return;
            }
            return end.call(this, function(error, response) {
                vm.ajaxLoading(false);
                callback(error, response);
            });
        };
        */

        return request;
    };
};
