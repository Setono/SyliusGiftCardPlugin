(function () {
    'use strict';

    function init() {
        var container = document.getElementById('setono-gift-card-information');
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-js-gc-card]');
        var previewImage = container.querySelector('[data-js-gc-image]');
        var previewAmount = container.querySelector('[data-js-gc-amount]');
        var previewMessage = container.querySelector('[data-js-gc-message]');
        var frame = container.querySelector('.ssgc-preview__frame');

        var messagePlaceholder = container.getAttribute('data-preview-message-placeholder') || '';
        var currency = container.getAttribute('data-currency') || 'USD';
        var locale = container.getAttribute('data-locale') || 'en-US';

        var amountInput = container.querySelector('input[type="text"], input[type="number"]');
        var messageInput = container.querySelector('textarea');
        var designInputs = container.querySelectorAll('[data-js-gift-card-design-picker] input[type="radio"]');

        var formatter = null;
        try {
            formatter = new Intl.NumberFormat(locale, { style: 'currency', currency: currency });
        } catch (e) {
            formatter = null;
        }

        function formatAmount(raw) {
            var value = parseFloat(raw);
            if (isNaN(value)) {
                return '';
            }
            if (formatter) {
                return formatter.format(value);
            }
            return value.toFixed(2);
        }

        function updateAmount() {
            if (previewAmount && amountInput) {
                previewAmount.textContent = formatAmount(amountInput.value);
            }
        }

        function updateMessage() {
            if (!previewMessage) {
                return;
            }
            var value = messageInput ? messageInput.value.trim() : '';
            previewMessage.textContent = value !== '' ? value : messagePlaceholder;
        }

        function updateDesign() {
            if (!previewImage) {
                return;
            }
            var selected = container.querySelector('[data-js-gift-card-design-picker] input[type="radio"]:checked');
            var input = selected || (designInputs.length ? designInputs[0] : null);
            if (!input) {
                return;
            }
            var label = input.closest('label');
            var img = label ? label.querySelector('img[data-design-image]') : null;
            if (img) {
                previewImage.src = img.getAttribute('data-design-image');
                previewImage.alt = img.alt;
            }
        }

        function scaleCard() {
            if (!card || !frame) {
                return;
            }
            var scale = frame.clientWidth / 560;
            card.style.transform = 'scale(' + scale + ')';
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
