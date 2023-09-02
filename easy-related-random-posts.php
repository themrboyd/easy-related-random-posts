<?php
/*
Plugin Name: Easy Related Random Posts (ERRP)
Description: The Easy Related Random Posts plugin is a versatile tool that allows you to display related or random posts on your WordPress website with just a few simple steps. Whether you want to keep your visitors engaged by showing related content or add an element of surprise with random posts, this plugin has you covered.
Version: 1.0
Author: Boyd Duang
*/

// Shortcode to display related or random posts
function errp_related_random_posts_shortcode($atts) {
    // Get the headline and limit from settings
    $headline = get_option('errp_headline', 'Related Posts');
    $limit = get_option('errp_limit', 5);

    // Get the selected display type from settings
    $display_type = get_option('errp_display_type', 'related');

    // Get the cache time from settings (default to 1800 seconds)
    $cache_time = intval(get_option('errp_cache_time', 1800));

    // Check if caching is disabled (cache time less than or equal to 0)
    if ($cache_time <= 0) {
        // Cache is disabled, so always fetch fresh data
        $args = array(
            'posts_per_page' => $limit,
            'post_type' => 'post', // Adjust to your post type
            'orderby' => ($display_type === 'random') ? 'rand' : 'date',
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            ob_start();
            echo '<div class="errp-posts">';
            echo '<h4 class="errp-headline">' . esc_html($headline) . '</h4>';
            echo '<ul>';
            while ($query->have_posts()) {
                $query->the_post();
                echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
            }
            echo '</ul>';
            echo '</div>';
            $output = ob_get_clean();
        }

        // Return the fresh data without caching
        return $output;
    }

    // Shortcode attributes
    $atts = shortcode_atts(array(
        'type' => $display_type, // Use the selected display type as the default
    ), $atts);

    // Validate and sanitize attributes
    $type = sanitize_text_field($atts['type']);

    // Cache key
    $cache_key = 'errp_' . $type . '_posts_' . $limit;

    // Attempt to get data from cache
    $output = get_transient($cache_key);

    // Check if the cache has expired or is not set
    if (false === $output) {
        // Cache is expired or not set, so query and cache new data
        $args = array(
            'posts_per_page' => $limit,
            'post_type' => 'post', // Adjust to your post type
            'orderby' => ($type === 'random') ? 'rand' : 'date',
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            ob_start();
            echo '<div class="errp-posts">';
            echo '<h4 class="errp-headline">' . esc_html($headline) . '</h4>';
            echo '<ul>';
            while ($query->have_posts()) {
                $query->the_post();
                echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
            }
            echo '</ul>';
            echo '</div>';
            $output = ob_get_clean();
        }

        // Cache the data for the specified cache time
        set_transient($cache_key, $output, $cache_time);
    }

    // Reset post data
    wp_reset_postdata();

    return $output;
}

add_shortcode('easy_related_random_posts', 'errp_related_random_posts_shortcode');
 
// Add a menu item under the Settings menu
function errp_add_settings_menu() {
    add_options_page(
        'Easy Related Random Posts Settings',
        'Easy Related Random Posts',
        'manage_options',
        'errp-settings',
        'errp_settings_page'
    );
}
add_action('admin_menu', 'errp_add_settings_menu');

// Define the settings page content 
function errp_settings_page() {
    ?>
    <div class="wrap">
        <h2>Easy Related Random Posts Settings</h2>

        <form method="post" action="options.php">
            <?php
            settings_fields('errp-settings-group');
            do_settings_sections('errp-settings');
            ?>
        <h3>Display Options</h3>
        <p>Select the type of posts you want to display in the Easy Related Random Posts (ERRP) shortcode:</p>
            <select name="errp_display_type">
                <option value="related" <?php selected(get_option('errp_display_type', 'related'), 'related'); ?>>Related Posts</option>
                <option value="random" <?php selected(get_option('errp_display_type', 'related'), 'random'); ?>>Random Posts</option>
            </select>

             
            <?php submit_button(); ?>
        </form>

        <h3>Cache Management</h3>
        <p>Click the button below to clear the cache for the Related Random Posts (ERRP) shortcode:</p>
        <form method="post" action="">
            <?php wp_nonce_field('clear_cache_nonce', 'clear_cache_nonce'); ?>
            <input type="submit" name="clear_cache" class="button" value="Clear Cache">
        </form>
        
    </div>
    <?php
}



// Register and initialize settings
function errp_initialize_settings() {
    register_setting('errp-settings-group', 'errp_headline', 'sanitize_text_field');
    register_setting('errp-settings-group', 'errp_limit', 'intval');
    register_setting('errp-settings-group', 'errp_display_type');  
    register_setting('errp-settings-group', 'errp_cache_time', 'intval'); // Register the cache_time option

    add_settings_section('errp-main-section', 'Plugin Settings', 'errp_settings_section_callback', 'errp-settings');

    add_settings_field('errp-headline-field', 'Headline Text', 'errp_headline_field_callback', 'errp-settings', 'errp-main-section');
    add_settings_field('errp-limit-field', 'Post Limit', 'errp_limit_field_callback', 'errp-settings', 'errp-main-section');
    add_settings_field('errp-cache-time-field', 'Cache Time (in seconds)', 'errp_cache_time_field_callback', 'errp-settings', 'errp-main-section'); // Add this line
}
add_action('admin_init', 'errp_initialize_settings');

// Add an action hook to handle cache clearing
add_action('admin_init', 'errp_handle_cache_clearing');

// Callback function for cache clearing
function errp_handle_cache_clearing() {
    if (isset($_POST['clear_cache']) && check_admin_referer('clear_cache_nonce', 'clear_cache_nonce')) {
        // Clear the cache
        $cache_time = intval(get_option('errp_cache_time', 1800)); // Get the cache time value
        $cache_keys_to_clear = array();

        if ($cache_time > 0) {
            // Only clear cache if caching is enabled (cache_time > 0)
            $limit = get_option('errp_limit', 5); // Get the limit value
            $display_type = get_option('errp_display_type', 'related'); // Get the display type value

            // Create a cache key based on the cache settings
            $cache_key = 'errp_' . $display_type . '_posts_' . $limit;

            // Add the cache key to the array
            $cache_keys_to_clear[] = $cache_key;
        }

        // Clear the cache for each cache key
        foreach ($cache_keys_to_clear as $cache_key) {
            delete_transient($cache_key);
        }

        // Redirect back to the settings page
        wp_redirect(admin_url('options-general.php?page=errp-settings'));
        exit;
    }
}


// Callback function for the settings section
function errp_settings_section_callback() {
    echo 'Customize the display of related or random posts shortcode. <code>[easy_related_random_posts]</code>';
}

// Callback function for the headline field
function errp_headline_field_callback() {
    $headline = get_option('errp_headline', 'Related Posts');
    echo '<input type="text" name="errp_headline" value="' . esc_attr($headline) . '" />';
}

// Callback function for the limit field
function errp_limit_field_callback() {
    $limit = get_option('errp_limit', 5);
    echo '<input type="number" name="errp_limit" value="' . esc_attr($limit) . '" />';
}
// Callback function for the cache_time field
function errp_cache_time_field_callback() {
    $cache_time = intval(get_option('errp_cache_time', 1800)); // Get the current cache time value

    echo '<input type="number" name="errp_cache_time" value="' . esc_attr($cache_time) . '" min="0" />';
    echo '<p class="description">Enter 0 for no caching or a positive value for caching.</p>';

    // Display an error message if the cache time is negative
    if ($cache_time < 0) {
        echo '<p class="error">Cache time cannot be negative.</p>';
    }
}



