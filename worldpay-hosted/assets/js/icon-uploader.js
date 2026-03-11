/**
 * WorldPay Icon Uploader
 * Handles icon upload for payment gateway settings
 */
(function($) {
    'use strict';

    $(function() {
        // Handle upload button clicks
        $(document).on('click', '.worldpay-icon-upload-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var fieldId = $button.data('field');
            var $input = $('#' + fieldId);
            var $container = $button.closest('.forminp');

            // Check if wp.media exists
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media library is not available. Please reload the page.');
                return;
            }

            var frame = wp.media({
                title: worldpayIconUpload.selectTitle || 'Select Icon',
                button: {
                    text: worldpayIconUpload.useButton || 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                
                // Set the value in the hidden input
                $input.val(attachment.url);

                // Update preview
                var $preview = $container.find('#' + fieldId + '_preview');
                var previewHtml = '<div style="margin-bottom: 10px;" id="' + fieldId + '_preview">' +
                    '<img src="' + attachment.url + '" alt="Payment Icon" style="max-height: 60px; max-width: 150px;">' +
                    '</div>';

                if ($preview.length) {
                    $preview.replaceWith(previewHtml);
                } else {
                    $button.before(previewHtml);
                }

                // Show remove button if not exists
                var $removeBtn = $container.find('.worldpay-icon-remove-btn[data-field="' + fieldId + '"]');
                if (!$removeBtn.length) {
                    $button.after('<button type="button" class="button worldpay-icon-remove-btn" data-field="' + fieldId + '" style="margin-left: 5px;">' +
                        'Remove Icon' +
                        '</button>');
                }
                
                // Trigger change event to enable save button
                $input.trigger('change');
            });

            frame.open();
        });

        // Handle remove button clicks
        $(document).on('click', '.worldpay-icon-remove-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var fieldId = $button.data('field');
            var $input = $('#' + fieldId);
            var $container = $button.closest('.forminp');

            // Clear the input value
            $input.val('');

            // Remove preview
            $container.find('#' + fieldId + '_preview').remove();

            // Remove the remove button
            $button.remove();
            
            // Trigger change event to enable save button
            $input.trigger('change');
        });
    });
})(jQuery);
