jQuery(document).ready(function($) {
    $(document).on('click', '#cdw-plugin-base-cancel-button', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to cancel the current license? This will clear your license key and data.')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Cancelling...');

        $.post(ajaxurl, {
            action: 'cdw_plugin_base_cancel_license',
            _ajax_nonce: cdw_plugin_base_ajax.cancel_nonce
        }, function(response) {
            $button.prop('disabled', false).text('Cancel License');

            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        }).fail(function() {
            $button.prop('disabled', false).text('Cancel License');
            alert('An unknown error occurred during license cancellation.');
        });
    });
});