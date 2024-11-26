<?php
/**
 * Plugin Name: WPLess Post Tag Generator
 * Description: A plugin to generate and assign tags to posts based on content using the Gemini API with customizable quantity.
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue CSS and JS for the backend
function wpless_post_tag_generator_enqueue_assets() {
    global $pagenow;
    // Enqueue the JS and CSS files only on the post edit page
    if ('post.php' === $pagenow || 'post-new.php' === $pagenow) {
        wp_enqueue_script('wpless-post-tag-generator-js', plugin_dir_url(__FILE__) . 'assets/js/wpless-post-tag-generator.js', array('jquery'), null, true);
        wp_enqueue_style('wpless-post-tag-generator-css', plugin_dir_url(__FILE__) . 'assets/css/wpless-post-tag-generator.css');
    }
}
add_action('admin_enqueue_scripts', 'wpless_post_tag_generator_enqueue_assets');

// Add the "Generate Tags" button and quantity input field
function add_generate_tags_button_and_quantity_input() {
    global $post;

    if ('post' === $post->post_type) {
        ?>
        <div id="generate-tags-container" style="margin-top: 10px;">
            <label for="tag-quantity">Tag Quantity:</label>
            <input type="number" id="tag-quantity" value="10" min="1" max="20" style="width: 60px; margin-right: 10px;">
            <button id="generate-tags-button" class="button">Generate Tags</button>
        </div>
        <?php
    }
}
add_action('edit_form_after_title', 'add_generate_tags_button_and_quantity_input');

// AJAX handler to generate tags for the post with specified quantity
function handle_generate_tags() {
    if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
        wp_send_json_error(array('message' => 'Invalid post ID'));
        return;
    }

    $post_id = intval($_POST['post_id']);
    $post_content = get_post_field('post_content', $post_id);
    $tag_quantity = isset($_POST['tag_quantity']) ? intval($_POST['tag_quantity']) : 10;

    if (empty($post_content)) {
        wp_send_json_error(array('message' => 'No content found for this post'));
        return;
    }

    // Generate tags from the Gemini API
    $tags = generate_tags_from_gemini_api($post_content, $tag_quantity);

    if (count($tags) < $tag_quantity) {
        wp_send_json_error(array('message' => 'Not enough tags generated. Please try again later.'));
        return;
    }

    wp_set_post_tags($post_id, $tags);

    wp_send_json_success(array(
        'message' => 'Tags generated successfully!',
        'tags' => $tags,
        'tag_count' => count($tags)
    ));
}
add_action('wp_ajax_generate_tags', 'handle_generate_tags');

// Function to call Gemini API to generate tags based on post content
function generate_tags_from_gemini_api($post_content, $quantity) {
    $api_key = get_option('gemini_api_key');
    if (!$api_key) {
        return [];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;
    $prompt = "Give me $quantity tags for this post without any irrelevant word:" . $post_content;
    $data = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);

    $response = wp_remote_post($url, [
        'method'    => 'POST',
        'body'      => $data,
        'headers'   => ['Content-Type' => 'application/json']
    ]);

    if (is_wp_error($response)) {
        error_log('Gemini API request failed: ' . $response->get_error_message());
        return [];
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $response_text = $response_data['candidates'][0]['content']['parts'][0]['text'];
        preg_match_all('/\d+\.\s*([^\n]+)/', $response_text, $matches);

        if (isset($matches[1])) {
            $tags = array_slice($matches[1], 0, $quantity);
            return $tags;
        }
    }

    return [];
}

// Add a settings page to enter the Gemini API Key
function wpless_post_tag_generator_menu() {
    add_options_page('WPLess Post Tag Generator Settings', 'WPLess Post Tag Generator', 'manage_options', 'wpless-post-tag-generator-settings', 'wpless_post_tag_generator_settings_page');
}
add_action('admin_menu', 'wpless_post_tag_generator_menu');

// Display the settings page
function wpless_post_tag_generator_settings_page() {
    ?>
    <div class="wrap">
        <h1>WPLess Post Tag Generator Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpless_post_tag_generator_options');
            do_settings_sections('wpless-post-tag-generator-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Gemini API Key</th>
                    <td>
                        <input type="password" name="gemini_api_key" value="<?php echo esc_attr(get_option('gemini_api_key')); ?>" class="regular-text" />
                        <p class="description">Enter your Gemini API key here.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register the settings
function wpless_post_tag_generator_register_settings() {
    register_setting('wpless_post_tag_generator_options', 'gemini_api_key');
}
add_action('admin_init', 'wpless_post_tag_generator_register_settings');
