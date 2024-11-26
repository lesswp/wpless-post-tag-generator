<?php
/*
Plugin Name: WPLess Post Tag Generator
Description: Generate tags for posts using Gemini API.
Version: 1.0
Author: WPLess
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue scripts and styles
function wpless_enqueue_scripts($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    wp_enqueue_script(
        'wpless-post-tag-generator-js',
        plugins_url('assets/js/wpless-post-tag-generator.js', __FILE__),
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('wpless-post-tag-generator-js', 'wpvars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('generate_tags_nonce'),
    ]);
}
add_action('admin_enqueue_scripts', 'wpless_enqueue_scripts');

// Add Gemini API key field in settings
function wpless_add_settings_page() {
    add_options_page(
        'WPLess Post Tag Generator',
        'Tag Generator Settings',
        'manage_options',
        'wpless-post-tag-generator',
        'wpless_settings_page'
    );
}
add_action('admin_menu', 'wpless_add_settings_page');

function wpless_settings_page() {
    ?>
    <div class="wrap">
        <h1>WPLess Post Tag Generator</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpless_settings');
            do_settings_sections('wpless-post-tag-generator');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function wpless_register_settings() {
    register_setting('wpless_settings', 'wpless_gemini_api_key');
    add_settings_section('wpless_main_section', '', null, 'wpless-post-tag-generator');

    add_settings_field(
        'wpless_gemini_api_key',
        'Gemini API Key',
        'wpless_api_key_field',
        'wpless-post-tag-generator',
        'wpless_main_section'
    );
}
add_action('admin_init', 'wpless_register_settings');

function wpless_api_key_field() {
    $api_key = get_option('wpless_gemini_api_key', '');
    echo '<input type="text" name="wpless_gemini_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}

// Handle tag generation request
function wpless_handle_generate_tags() {
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'generate_tags_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }

    $post_id = intval($_POST['post_id']);
    $tag_count = isset($_POST['tag_count']) ? intval($_POST['tag_count']) : 10;

    if ($tag_count < 5 || $tag_count > 10) {
        wp_send_json_error(['message' => 'Tag count must be between 5 and 10']);
        return;
    }

    $post_content = get_post_field('post_content', $post_id);
    if (empty($post_content)) {
        wp_send_json_error(['message' => 'No content found for this post']);
        return;
    }

    $tags = wpless_generate_tags_from_gemini_api($post_content, $tag_count);
    if (empty($tags)) {
        wp_send_json_error(['message' => 'Error generating tags']);
        return;
    }

    wp_set_post_tags($post_id, $tags);

    wp_send_json_success([
        'message' => 'Tags generated successfully!',
        'tags'    => $tags,
    ]);
}
add_action('wp_ajax_generate_tags', 'wpless_handle_generate_tags');

function wpless_generate_tags_from_gemini_api($content, $count) {
    $api_key = get_option('wpless_gemini_api_key', '');
    if (empty($api_key)) {
        return [];
    }

    $response = wp_remote_post('https://api.gemini.example.com/generate-tags', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode([
            'content' => $content,
            'count'   => $count,
        ]),
    ]);

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gemini API request failed: ' . $response->get_error_message());
        }
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return isset($body['tags']) ? $body['tags'] : [];
}
