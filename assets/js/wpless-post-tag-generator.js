jQuery(document).ready(function ($) {
    $('#generate-tags-button').on('click', function (e) {
        e.preventDefault();

        var postId = $('#post_ID').val();
        var tagCount = $('#tag-quantity-input').val();

        $.ajax({
            url: wpvars.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_tags',
                post_id: postId,
                tag_count: tagCount,
                _wpnonce: wpvars.nonce, // Secure the request
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#tagsdiv-post_tag input').val(response.data.tags.join(', '));
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('Error generating tags.');
            },
        });
    });
});
