jQuery(document).ready(function($) {
    console.log("Backend JavaScript loaded");

    // Click event handler for the "Generate Tags" button
    $('#generate-tags-button').on('click', function(event) {
        event.preventDefault(); // Prevent default button behavior
        var postId = wpvars.post_id;
        var tagQuantity = $('#tag-quantity').val(); // Get the quantity input value

        console.log('Button clicked for post ID:', postId, 'with tag quantity:', tagQuantity);

        // Show a loading message or spinner (optional)
        $('#generate-tags-container').append('<p id="loading-message">Generating tags, please wait...</p>');

        // Send AJAX request to the server to generate tags
        $.ajax({
            url: wpvars.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_tags', // Custom action for generating tags
                post_id: postId,
                tag_quantity: tagQuantity
            },
            success: function(response) {
                $('#loading-message').remove(); // Remove loading message

                if (response.success) {
                    alert(response.data.message + " (" + response.data.tag_count + " tags generated)");
                    // Add the tags to the tag input field (comma-separated)
                    $('#tagsdiv-post_tag input').val(response.data.tags.join(', '));
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                $('#loading-message').remove(); // Remove loading message
                alert('Error generating tags.');
            }
        });
    });
});
