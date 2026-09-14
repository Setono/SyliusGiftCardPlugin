(function () {
    'use strict';

    function init() {
        var container = document.getElementById('setono-gift-card-information');
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-js-gc-card]');
        var previewImage = container.querySelector('[data-js-gc-image]');
        var amountTargets = container.querySelectorAll('[data-js-gc-amount]');
        var messageTargets = container.querySelectorAll('[data-js-gc-message]');
        var frame = container.querySelector('.ssgc-preview__frame');

        var messagePlaceholder = container.getAttribute('data-preview-message-placeholder') || '';
        var currency = container.getAttribute('data-currency') || '';
        var locale = container.getAttribute('data-locale') || 'en-US';

        // The form type marks the amount field, so the preview does not have to guess which input it is
        var amountInput = container.querySelector('[data-js-gc-amount-input]') ||
            container.querySelector('input[type="text"], input[type="number"]');
        var messageInput = container.querySelector('textarea');
        var designInputs = container.querySelectorAll('[data-js-gift-card-design-picker] input[type="radio"]');

        var formatter = null;
        try {
            formatter = currency !== ''
                ? new Intl.NumberFormat(locale, { style: 'currency', currency: currency })
                : new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (e) {
            formatter = null;
        }

        // The customer types the amount the way the page is localised, so "50,50" is fifty and a half in da or fr
        var decimalSeparator = '.';
        try {
            new Intl.NumberFormat(locale).formatToParts(1.1).forEach(function (part) {
                if (part.type === 'decimal') {
                    decimalSeparator = part.value;
                }
            });
        } catch (e) {
            decimalSeparator = '.';
        }

        function parseAmount(raw) {
            var input = String(raw === null || raw === undefined ? '' : raw).trim();
            if (input === '') {
                return NaN;
            }

            // Everything that is not a digit or the locale's decimal separator (grouping separators, currency
            // symbols, spaces) is noise as far as the value goes
            var normalized = '';
            for (var i = 0; i < input.length; i++) {
                var character = input.charAt(i);
                if (character >= '0' && character <= '9') {
                    normalized += character;
                } else if (character === decimalSeparator) {
                    normalized += '.';
                }
            }

            if (normalized === '') {
                return NaN;
            }

            return parseFloat(input.charAt(0) === '-' ? '-' + normalized : normalized);
        }

        function formatAmount(raw) {
            var value = parseAmount(raw);
            if (isNaN(value)) {
                return '';
            }
            return formatter ? formatter.format(value) : value.toFixed(2);
        }

        function setAll(nodes, text) {
            Array.prototype.forEach.call(nodes, function (node) {
                node.textContent = text;
            });
        }

        function updateAmount() {
            if (amountInput) {
                setAll(amountTargets, formatAmount(amountInput.value));
            }
        }

        function updateMessage() {
            var value = messageInput ? messageInput.value.trim() : '';
            setAll(messageTargets, value !== '' ? value : messagePlaceholder);
        }

        function updateDesign() {
            if (!card) {
                return;
            }
            var selected = container.querySelector('[data-js-gift-card-design-picker] input[type="radio"]:checked');
            var input = selected || (designInputs.length ? designInputs[0] : null);
            var label = input ? input.closest('label') : null;
            var img = label ? label.querySelector('img[data-design-image]') : null;

            if (img && previewImage) {
                previewImage.src = img.getAttribute('data-design-image');
                previewImage.alt = img.alt;
                card.className += card.className.indexOf('ssgc-card--has-image') === -1 ? ' ssgc-card--has-image' : '';
            } else {
                // Design without an image: fall back to the framed default card
                card.className = card.className.replace(/\s*ssgc-card--has-image/g, '');
            }
        }

        function scaleCard() {
            if (card && frame) {
                card.style.transform = 'scale(' + (frame.clientWidth / 560) + ')';
            }
        }

        if (amountInput) {
            amountInput.addEventListener('input', updateAmount);
        }
        if (messageInput) {
            messageInput.addEventListener('input', updateMessage);
        }
        Array.prototype.forEach.call(designInputs, function (input) {
            input.addEventListener('change', updateDesign);
        });
        window.addEventListener('resize', scaleCard);

        updateAmount();
        updateMessage();
        updateDesign();
        scaleCard();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
