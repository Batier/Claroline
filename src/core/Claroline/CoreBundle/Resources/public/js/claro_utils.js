$('#bootstrap-modal').modal({
    show:false,
    backdrop:false
});

var utils = {};
window.ClaroUtils = utils;

utils.ajaxAuthenticationErrorHandler = function(callBack){
    $.ajax({
        type: 'GET',
        url: Routing.generate('claro_security_login'),
        cache: false,

        success: function(data){
            $('#modal-body').append(data);
            $('#bootstrap-modal').modal("show");
            $('#login_form').submit(function(e){
                e.preventDefault();
                $.ajax({
                    type: 'POST',
                    url: Routing.generate('claro_security_login_check'),
                    cache: false,
                    data: $('#login_form').serialize(),
                    success: function(data){
                        $('#bootstrap-modal').modal("hide");
                        callBack();
                    }
                });
            });
        }
    });
}

utils.sendRequest = function (route, successHandler){
    var url = '';
    ('string' == typeof route)? url = route : url = Routing.generate(route.name, route.parameters);
    $.ajax({
        type: 'POST',
        url: url,
        cache: false,
        success: function(data){successHandler(data)},
        error: function(xhr){
            (xhr.status == 403) ? utils.ajaxAuthenticationErrorHandler(function(){
                (undefined != typeof successHandler)? sendRequest(route, successHandler) : window.location.reload();
             }): alert('error');
        }
    });
}

utils.sendForm = function(route, form, successHandler){
    var url = '';
    ('string' == typeof route)? url = route : url = Routing.generate(route.name, route.parameters);
    var formData = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('X_Requested_With', 'XMLHttpRequest');
    xhr.onload = function(e){successHandler(xhr)};
    xhr.onreadystatechange = function() {
         if(xhr.readyState == 4){
             if(xhr.status == 403){
                 window.location.reload();
             }
         }
    }
    xhr.send(formData);
}