/**
 * The admin's gift card create form chooses the channel and the currency together, so it offers the currencies of
 * every channel. This narrows the currencies to the ones the chosen channel offers, and picks the channel's base
 * currency when the channel is changed, or when the currency picked is not one the channel offers. The form turns down
 * a currency the channel does not offer either way: without this script every currency is offered, and the wrong one
 * is refused when the form is saved.
 */
(function () {
    'use strict';

    var selects = document.querySelectorAll('select[data-js-gift-card-currency][data-channel-field]');

    Array.prototype.forEach.call(selects, function (select) {
        var channel = document.getElementById(select.getAttribute('data-channel-field'));
        if (!channel) {
            return;
        }

        function listed(option, attribute) {
            return (option.getAttribute(attribute) || '').split(' ').indexOf(channel.value) !== -1;
        }

        function narrow(pickBaseCurrency) {
            var baseCurrency = null;

            Array.prototype.forEach.call(select.options, function (option) {
                var offered = listed(option, 'data-channels');

                // Browsers that do not hide an option still keep a disabled one from being picked
                option.hidden = !offered;
                option.disabled = !offered;

                if (listed(option, 'data-base-channels')) {
                    baseCurrency = option;
                }
            });

            var selected = select.options[select.selectedIndex];
            if (baseCurrency && (pickBaseCurrency || !selected || selected.disabled)) {
                baseCurrency.selected = true;
            }
        }

        channel.addEventListener('change', function () {
            narrow(true);
        });
        narrow(false);
    });
})();
