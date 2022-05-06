(function ($) {
  'use strict';

  $.fn.extend({
    sendNotificationEmailInput: function () {
      const $customerInput = $(this);
      const $sendCustomerNotificationEmail = $($customerInput.data('target'));

      $('label[for="setono_sylius_gift_card_gift_card_customer"]').prepend('<i class="delete icon js-ssgc-clear-input" style="float: right;cursor: pointer"></i>');

      $customerInput.on('change', () => {
        if ($customerInput.val() !== '') {
          $sendCustomerNotificationEmail.show();
        } else {
          $sendCustomerNotificationEmail.hide();
        }
      });

      $('.js-ssgc-clear-input').on('click', () => {
        $customerInput.parent().children('div.text').html('');
        $customerInput.val('');
        $customerInput.trigger('change');
      });
    }
  });
})(jQuery);
