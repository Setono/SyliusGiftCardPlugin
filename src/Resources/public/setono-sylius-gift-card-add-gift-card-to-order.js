(function ($) {
  'use strict';

  $.fn.extend({
    addGiftCardToOrder: function () {
      const $element = $(this);
      const url = $element.attr('data-action');
      const redirectUrl = $element.data('redirect');

      $element.on('submit', function (event) {
        event.preventDefault();

        $.ajax(url, {
          method: 'POST',
          data: $element.serialize(),
          success: function () {
            window.location.href = redirectUrl;
          },
          error: function (xhr) {
            $('.setono-sylius-gift-card-gift-card-block').replaceWith(xhr.responseText);

            $('#setono-sylius-gift-card-add-gift-card-to-order').addGiftCardToOrder();
          },
        });
      });

    },
  });
})(jQuery);
