<?php
class ABC_DB {
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abc_banners';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            settings longtext NOT NULL,
            slides longtext NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add default settings
        add_option('abc_default_settings', json_encode(array(
            'autoplay' => true,
            'autoplay_speed' => 5000,
            'animation_speed' => 500,
            'pause_on_hover' => true,
            'infinite_loop' => true,
            'show_arrows' => true,
            'show_dots' => false,
            'slides_to_show' => 1.2, // Partial slides visible like Souled Store
            'slides_to_scroll' => 1,
            'center_mode' => false,
            'variable_width' => true, // For Souled Store style
            'responsive' => array(
                array(
                    'breakpoint' => 768,
                    'settings' => array(
                        'slides_to_show' => 1,
                        'variable_width' => false
                    )
                )
            )
        )));
      
        // Preload banners after table creation
    ABC_Cache::preload_banners();
}


    public static function check_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'abc_banners';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        self::create_tables();
        error_log('ABC Carousel: Created missing database table'); // Moved inside the if block
    }
    
    // Register cache hooks regardless of whether table was just created
    ABC_Cache::register_hooks();
}

    public static function delete_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abc_banners';
        
        // Delete the table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Delete the settings option
        delete_option('abc_default_settings');
        
        // Log the table deletion (for debugging)
        error_log('ABC Carousel: Removed database tables on uninstall');
    }

    public static function get_banner($slug) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_banners WHERE slug = %s",
            $slug
        ));
    }

    public static function get_banner_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_banners WHERE id = %d",
            $id
        ));
    }

    public static function get_all_banners() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}abc_banners ORDER BY created_at DESC"
        );
    }

    public static function save_banner($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abc_banners';
        
        $defaults = array(
            'name' => '',
            'slug' => '',
            'settings' => '',
            'slides' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if ($wpdb->insert($table_name, $data)) {
            return $wpdb->insert_id;
        }
        
        return false;
    }

    public static function update_banner($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abc_banners';
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $table_name,
            $data,
            array('id' => $id)
        );
    }
    
    // Add these methods to your ABC_DB class
public static function get_banner_with_cache($slug) {
    $cache_key = 'abc_banner_data_' . $slug;
    $banner = wp_cache_get($cache_key);
    
    if (false === $banner) {
        global $wpdb;
        $banner = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, slug, settings, slides 
             FROM {$wpdb->prefix}abc_banners 
             WHERE slug = %s",
            $slug
        ));
        
        if ($banner) {
            wp_cache_set($cache_key, $banner, '', DAY_IN_SECONDS);
        }
    }
    
    return $banner;
}

public static function get_lightweight_banners() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT id, name, slug, created_at 
         FROM {$wpdb->prefix}abc_banners 
         ORDER BY created_at DESC"
    );
}

    public static function delete_banner($id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'abc_banners',
            array('id' => $id)
        );
    }
}