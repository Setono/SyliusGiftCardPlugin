(function ($) {
  'use strict';

  $.fn.extend({
    addGiftCardToOrder: function () {
      const $element = $(this);
      const url = $element.attr('action');
      const redirectUrl = $element.data('redirect');
      const validationElement = $('#setono-sylius-gift-card-cart-validation-error');

      $.ajax(url, {
        method: 'POST',
        beforeSend(settings) {
          settings.data = $element.serialize();

          return settings;
        },
        success: function () {
          validationElement.addClass('hidden');
          window.location.href = redirectUrl;
        },
        error: function (xhr) {
          validationElement.removeClass('hidden');
          let validationMessage = '';

          Object.entries(xhr.responseJSON.errors.errors).forEach(([, message]) => {
            validationMessage += message;
          });
          validationElement.html(validationMessage);
          $element.removeClass('loading');
        },
      });
    },
  });
})(jQuery);
