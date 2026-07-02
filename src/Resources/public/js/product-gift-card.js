(function () {
    'use strict';

    function init() {
        var container = document.getElementById('setono-gift-card-information');
        if (!container) {
            return;
        }

        var previewImage = container.querySelector('[data-js-gift-card-preview-image]');
        var previewAmount = container.querySelector('[data-js-gift-card-preview-amount]');
        var previewMessage = container.querySelector('[data-js-gift-card-preview-message]');
        var messagePlaceholder = container.getAttribute('data-preview-message-placeholder') || '';

        var amountInput = container.querySelector('input[type="text"], input[type="number"]');
        var messageInput = container.querySelector('textarea');
        var designInputs = container.querySelectorAll('[data-js-gift-card-design-picker] input[type="radio"]');

        function updateAmount() {
            if (!previewAmount || !amountInput) {
                return;
            }
            previewAmount.textContent = amountInput.value ? amountInput.value : '';
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
            if (!selected) {
                return;
            }
            var label = selected.closest('label');
            var img = label ? label.querySelector('img[data-design-image]') : null;
            if (img) {
                previewImage.src = img.getAttribute('data-design-image');
                previewImage.alt = img.alt;
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

        updateAmount();
        updateMessage();
        updateDesign();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
