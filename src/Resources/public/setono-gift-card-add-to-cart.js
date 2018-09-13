(function ( $ ) {
    'use strict';

    $.fn.extend({
        addGiftCardToCart: function () {
            var element = $(this);
            var redirectUrl = $(element).data('redirect');
            var csrfToken = $(element).data('csrf-token');
            var inputElement = $('.gift-card input');
            var validationElement = $('#setono-cart-validation-error');

            $(inputElement).on('keypress',function(e) {
                if (e.keyCode === 13) {
                    e.preventDefault();

                    $(element).click();
                }
            });

            $(element).api({
                method: 'POST',
                on: 'click',
                beforeSend: function (settings) {
                    settings.data = {
                        _csrf_token: csrfToken,
                        code: inputElement.val()
                    };

                    return settings;
                },
                onSuccess: function (response) {
                    validationElement.addClass('hidden');
                    window.location.replace(redirectUrl);
                },
                onFailure: function (response) {
                    validationElement.html(response.error);

                    validationElement.removeClass('hidden');

                    $('.form').removeClass('loading');
                },
            });
        }
    });
})( jQuery );
