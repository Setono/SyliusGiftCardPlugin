(function ($) {
  'use strict';

  $.fn.extend({
    applyPdfChanges: function () {
      const $element = $(this);
      const url = $element.data('url');

      $element.on('click', function (event) {
        event.preventDefault();

        $.ajax(url, {
          method: 'POST',
          data: $('form[name="setono_sylius_gift_card_gift_card_configuration"]').serialize(),
          success(response) {
            $('.js-ssgc-live-render-container').html(`<embed id="js-ssgc-live-render-box" src="data:application/pdf;base64,${response}" style="width: 100%;height: 100%;"/>`);
          },
        });
      });
    },
  });
})(jQuery);
