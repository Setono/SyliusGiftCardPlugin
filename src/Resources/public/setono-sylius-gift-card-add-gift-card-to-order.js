(function ($) {
  'use strict';

  $.fn.extend({
    addGiftCardToOrder: function () {
      const $element = $(this);
      const url = $element.attr('action');
      let redirectUrl = $element.data('redirect');
      let validationElement = $('#setono-sylius-gift-card-cart-validation-error');

      $.ajax({
        method: 'POST',
        on: 'submit',
        url,
        beforeSend(settings) {
          settings.data = $element.serialize();

          return settings;
        },
        onSuccess: function () {
          validationElement.addClass('hidden');
          window.location.href = redirectUrl;
        },
        onFailure: function (response) {
          validationElement.removeClass('hidden');
          let validationMessage = '';

          Object.entries(response.errors.errors).forEach(([, message]) => {
            validationMessage += message;
          });
          validationElement.html(validationMessage);
          $element.removeClass('loading');
        },
      });
    }
  });
})(jQuery);
