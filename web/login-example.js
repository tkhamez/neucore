window.$(function() {
	app.ready();
});

var app = (function($) {

    // Only needed if frontend and backend are on different hosts.
    // Backend must allow the other host (config/settings.php [CORS][allow_origin]).
    var appBaseUrl = ''; // https://backend.domain.tld
    var clientBaseUrl = ''; // https://frontend.domain.tld

    var oauthUrl = "";

    function fetch(url, method, callback) {
        $.ajax({
        	method: method,
			url: url,
       		headers: {'Accept': 'application/json'},
			xhrFields: {
				// only needed if frontend and backend are on different hosts.
				withCredentials: true
			}
       	}).done(function(data, statusText, jqXHR) {
       		handleResult(jqXHR.status, statusText, data);
       	}).fail(function(jqXHR) {
       		handleResult(jqXHR.status, jqXHR.statusText);
       	});

        function handleResult(status, statusText, data) {
        	$('#status').text(status + ' ' + statusText);
   			$('#body').text(data ? JSON.stringify(data) : '');
            if (callback) {
                callback(data);
            }
        }
    }

	return {
		ready: function() {
			if (window.location.hash) {
				fetch(window.location.hash.substr(1));
	        }
		},

        getLogin: function() {
            var redirectPath = clientBaseUrl + '/login-example.html#' + appBaseUrl + '/api/user/auth/result';
            var url = appBaseUrl + '/api/user/auth/login-url?redirect=' + encodeURIComponent(redirectPath);
            fetch(url, 'GET', function(data) {
                oauthUrl = data || "";
            });
        },

        redirect: function() {
            if (oauthUrl !== "") {
            	window.top.location.href = oauthUrl;
            }
        },

		getUser: function() {
            fetch(appBaseUrl + '/api/user/auth/character', 'GET');
        },

        logout: function() {
        	fetch(appBaseUrl + '/api/user/auth/logout', 'POST');
        }
	};
})(window.$);

