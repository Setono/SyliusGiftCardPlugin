/**
 * The admin's gift card create form chooses the channel and the design together, so it offers the designs of every
 * channel. This narrows the design picker to the designs the chosen channel offers, and falls back to no design when
 * the one picked is not among them. The form turns down a design of another channel either way: without this script
 * every design is shown, and the wrong one is refused when the form is saved.
 */
(function () {
    'use strict';

    var pickers = document.querySelectorAll('[data-js-gift-card-design-picker][data-channel-field]');

    Array.prototype.forEach.call(pickers, function (picker) {
        var channel = document.getElementById(picker.getAttribute('data-channel-field'));
        if (!channel) {
            return;
        }

        var none = picker.querySelector('input[type="radio"][value=""]');

        function narrow() {
            Array.prototype.forEach.call(picker.querySelectorAll('label[data-channels]'), function (choice) {
                var offered = choice.getAttribute('data-channels').split(' ').indexOf(channel.value) !== -1;
                var input = choice.querySelector('input[type="radio"]');

                choice.hidden = !offered;
                if (!offered && input && input.checked && none) {
                    none.checked = true;
                }
            });
        }

        channel.addEventListener('change', narrow);
        narrow();
    });
})();
