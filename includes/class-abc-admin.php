<?php


class ABC_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_abc_save_banner', array($this, 'save_banner_ajax'));
        add_action('wp_ajax_abc_get_banner', array($this, 'get_banner_ajax'));
        add_action('wp_ajax_abc_delete_banner', array($this, 'delete_banner_ajax'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Banner Carousel',
            'Banner Carousel',
            'manage_options',
            'abc-banners',
            array($this, 'render_admin_page'),
            'dashicons-slides',
            30
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_abc-banners') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('abc-admin-css', ABC_PLUGIN_URL . 'assets/css/admin.css', array(), ABC_VERSION);
        wp_enqueue_script('abc-admin-js', ABC_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-util'), ABC_VERSION, true);

        wp_localize_script('abc-admin-js', 'abc_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('abc_admin_nonce'),
            'default_settings' => get_option('abc_default_settings')
        ));
    }
    
    // Update the get_optimized_image_data method
private function get_optimized_image_data($image_url, $position = 1, $alt_text = '') {
    static $dimensions_cache = array();
    
    $cache_key = md5($image_url);
    
    if (!isset($dimensions_cache[$cache_key])) {
        // Only calculate dimensions once per image
        $dimensions_cache[$cache_key] = array(
            'width' => '480',  // Default fallback
            'height' => '460'  // Default fallback
        );
        
        // Optional: Add actual image dimension detection here
        // if (function_exists('getimagesize') && filter_var($image_url, FILTER_VALIDATE_URL)) {
        //     try {
        //         list($width, $height) = @getimagesize($image_url);
        //         if ($width && $height) {
        //             $dimensions_cache[$cache_key] = array(
        //                 'width' => $width,
        //                 'height' => $height
        //             );
        //         }
        //     } catch (Exception $e) {}
        // }
    }
    
    return array_merge(
        $dimensions_cache[$cache_key],
        array(
            'url' => esc_url($image_url),
            'placeholder' => $this->get_placeholder_image($image_url),
            'alt' => sanitize_text_field($alt_text),
            'loading' => $position <= 2 ? 'eager' : 'lazy',
            'fetchpriority' => $position === 1 ? 'high' : 'auto',
            'decoding' => $position === 1 ? 'sync' : 'async'
        )
    );
}
    

    public function render_admin_page() {
        ?>
        <div class="wrap abc-admin">
            <h1>Banner Carousel</h1>
            
            <div class="abc-admin-container">
                <div class="abc-banners-list">
                    <div class="abc-header">
                        <h2>Your Banners</h2>
                        <button id="abc-add-new" class="button button-primary">Add New Banner</button>
                    </div>
                    
                    <div class="abc-banners-table">
                        <?php $this->render_banners_table(); ?>
                    </div>
                </div>
                
                <div class="abc-banner-editor" style="display: none;">
                    <div class="abc-editor-header">
                        <h2>Edit Banner</h2>
                        <div class="abc-editor-actions">
                            <button id="abc-save-banner" class="button button-primary">Save Banner</button>
                            <button id="abc-cancel-edit" class="button">Cancel</button>
                        </div>
                    </div>
                    
                    <div class="abc-editor-form">
                        <div class="abc-form-group">
                            <label for="abc-banner-name">Banner Name</label>
                            <input type="text" id="abc-banner-name" class="regular-text" required>
                        </div>
                        
                        <div class="abc-form-group">
                            <label for="abc-banner-slug">Banner Slug (shortcode)</label>
                            <input type="text" id="abc-banner-slug" class="regular-text" required>
                            <p class="description">This will be used in the shortcode like [abc_banner slug="your-slug"]</p>
                        </div>
                        
                        <h3>Slides</h3>
                        <div id="abc-slides-container">
                            <!-- Slides will be added here -->
                        </div>
                        
                        <button id="abc-add-slide" class="button">Add Slide</button>
                        
                        <h3>Carousel Settings</h3>
                        <div class="abc-settings-grid">
                            <div class="abc-form-group">
                                <label>
                                    <input type="checkbox" id="abc-autoplay" checked>
                                    Autoplay
                                </label>
                            </div>
                            
                            <div class="abc-form-group">
                                <label for="abc-autoplay-speed">Autoplay Speed (ms)</label>
                                <input type="number" id="abc-autoplay-speed" value="5000" min="1000" step="500">
                            </div>
                            
                            <div class="abc-form-group">
                                <label for="abc-animation-speed">Animation Speed (ms)</label>
                                <input type="number" id="abc-animation-speed" value="500" min="100" step="50">
                            </div>
                            
                            <div class="abc-form-group">
                                <label>
                                    <input type="checkbox" id="abc-pause-on-hover" checked>
                                    Pause on Hover
                                </label>
                            </div>
                            
                            <div class="abc-form-group">
                                <label>
                                    <input type="checkbox" id="abc-infinite-loop" checked>
                                    Infinite Loop
                                </label>
                            </div>
                            
                            <div class="abc-form-group">
                                <label>
                                    <input type="checkbox" id="abc-show-arrows" checked>
                                    Show Navigation Arrows
                                </label>
                            </div>
                            
                            <div class="abc-form-group">
                                <label>
                                    <input type="checkbox" id="abc-show-dots">
                                    Show Pagination Dots
                                </label>
                            </div>
                            
                            <div class="abc-form-group">
                                <label for="abc-slides-to-show">Slides to Show</label>
                                <input type="number" id="abc-slides-to-show" value="1.2" step="0.1" min="1">
                            </div>
                            
                            <div class="abc-form-group">
                                <label>
                                    <input type="checkbox" id="abc-variable-width" checked>
                                    Variable Width (Souled Store style)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Update the render_banners_table method
private function render_banners_table() {
    $banners = ABC_DB::get_lightweight_banners();
    
    if (empty($banners)) {
        echo '<p>No banners found. Create your first banner!</p>';
        return;
    }
    
    // Start output buffering
    ob_start();
    ?>
    <form method="post" action="<?php echo admin_url('admin.php?page=abc-banners'); ?>">
        <?php wp_nonce_field('abc_bulk_actions'); ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="abc-select-all"></th>
                    <th>Name</th>
                    <th>Shortcode</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banners as $banner) : ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="abc_banner_ids[]" value="<?php echo $banner->id; ?>">
                        </th>
                        <td><?php echo esc_html($banner->name); ?></td>
                        <td><code>[abc_banner slug="<?php echo esc_attr($banner->slug); ?>"]</code></td>
                        <td><?php echo date('M j, Y', strtotime($banner->created_at)); ?></td>
                        <td>
                            <button class="button abc-edit-banner" data-id="<?php echo $banner->id; ?>">Edit</button>
                            <button class="button abc-delete-banner" data-id="<?php echo $banner->id; ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <select name="abc_bulk_action">
                <option value="">Bulk Actions</option>
                <option value="clear_cache">Clear Cache</option>
                <option value="regenerate">Regenerate</option>
            </select>
            <input type="submit" class="button action" value="Apply">
        </div>
    </form>
    
    <script>
    jQuery(document).ready(function($) {
        $('#abc-select-all').on('change', function() {
            $('input[name="abc_banner_ids[]"]').prop('checked', $(this).prop('checked'));
        });
    });
    </script>
    <?php
    // Output the buffered content
    ob_end_flush();
}

    public function save_banner_ajax() {
        check_ajax_referer('abc_admin_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name']);
        $slug = sanitize_text_field($_POST['slug']);
        $settings = stripslashes($_POST['settings']);
        $slides = stripslashes($_POST['slides']);
        
        // Validate data
        if (empty($name) || empty($slug)) {
            wp_send_json_error('Name and slug are required');
        }
        
        $slides_data = json_decode($slides, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($slides_data)) {
            wp_send_json_error('Invalid slides data');
        }
        
        $settings_data = json_decode($settings, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($settings_data)) {
            wp_send_json_error('Invalid settings data');
        }
        
        // Sanitize slides data
        $sanitized_slides = array();
foreach ($slides_data as $slide) {
    // Ensure image URL is valid
    $image_url = esc_url_raw($slide['image']);
    if (empty($image_url)) {
        continue; // Skip slides with empty images
    }
    
    $sanitized_slides[] = array(
        'image' => $image_url,
        'link' => esc_url_raw($slide['link']),
        'title' => sanitize_text_field($slide['title']),
        'alt_text' => sanitize_text_field($slide['alt_text'])
    );
}
        
        $data = array(
            'name' => $name,
            'slug' => $slug,
            'settings' => maybe_serialize($settings_data),
            'slides' => maybe_serialize($sanitized_slides),
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($_POST['id'])) {
            $id = intval($_POST['id']);
            $result = ABC_DB::update_banner($id, $data);
        } else {
            $data['created_at'] = current_time('mysql');
            $result = ABC_DB::save_banner($data);
        }
        
        if ($result) {
         do_action('abc_banner_updated', $result); 
            wp_send_json_success('Banner saved successfully');
        } else {
            wp_send_json_error('Failed to save banner');
        }
    }

// Modify the get_banner_ajax method
 public function get_banner_ajax() {
        check_ajax_referer('abc_admin_nonce', 'nonce');
        
        $id = intval($_POST['id']);
        $cache_key = 'abc_banner_' . $id;
        $cached = wp_cache_get($cache_key);
        
        if (false !== $cached) {
            wp_send_json_success($cached);
        }
        
        $banner = ABC_DB::get_banner_by_id($id);
        
        if (!$banner) {
            wp_send_json_error('Banner not found');
        }
        
        $response = array(
            'id' => $banner->id,
            'name' => $banner->name,
            'slug' => $banner->slug,
            'settings' => maybe_unserialize($banner->settings),
            'slides' => maybe_unserialize($banner->slides),
            'created_at' => $banner->created_at,
            'updated_at' => $banner->updated_at
        );
        
        // Use the constant (defined in banner-carousel.php)
        wp_cache_set($cache_key, $response, '', ABC_AJAX_CACHE_TIME);
        wp_send_json_success($response);
    }

    public function delete_banner_ajax() {
    check_ajax_referer('abc_admin_nonce', 'nonce');
    
    $id = intval($_POST['id']);
    $banner = ABC_DB::get_banner_by_id($id);
    
    if (!$banner) {
        wp_send_json_error('Banner not found');
    }
    
    $result = ABC_DB::delete_banner($id);
    
    if ($result) {
        ABC_Cache::clear_banner_cache($banner->slug);
        ABC_Frontend::clear_shortcode_cache($banner->slug);
        wp_send_json_success('Banner deleted successfully');
    } else {
        wp_send_json_error('Failed to delete banner');
    }
}
}