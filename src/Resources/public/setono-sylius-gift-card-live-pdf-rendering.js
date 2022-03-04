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

  $.fn.extend({
    loadDefaultPdfCss: function () {
      const $element = $(this);
      const url = $element.data('url');

      $element.on('click', function (event) {
        event.preventDefault();

        $.ajax(url, {
          method: 'POST',
          success(response) {
            $('#setono_sylius_gift_card_gift_card_configuration_pdfRenderingCss').val(response.css);
            const src = document.getElementById('js-ssgc-live-render-box').src;
            $('.js-ssgc-live-render-container').html(`<embed id="js-ssgc-live-render-box" src="data:application/pdf;base64,${response.pdfContent}" style="width: 100%;height: 100%;"/>`);
          },
        });
      });
    },
  });
})(jQuery);
