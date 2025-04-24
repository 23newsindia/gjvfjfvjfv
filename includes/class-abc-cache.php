<?php
class ABC_Cache {
    private static $cache_group = 'abc_banners';
    private static $object_cache_time = 12 * HOUR_IN_SECONDS;
    private static $fragment_cache_time = 6 * HOUR_IN_SECONDS;
    private static $page_cache_time = 3 * HOUR_IN_SECONDS;
    private static $parsed_shortcodes = array();
    private static $redis = null;
    private static $memory_cache = array();

    public function __construct() {
        $this->init_hooks();
        $this->maybe_init_redis();
    }

    public static function register_hooks() {
        $instance = self::init();
        $instance->init_hooks();
    }

    protected function init_hooks() {
        add_action('abc_banner_updated', [$this, 'clear_banner_cache']);
        add_action('abc_banner_deleted', [$this, 'clear_banner_cache']);
        add_action('added_post_meta', [$this, 'handle_media_update'], 10, 4);
        add_action('updated_post_meta', [$this, 'handle_media_update'], 10, 4);
        add_action('deleted_post_meta', [$this, 'handle_media_update'], 10, 4);
        add_action('abc_settings_updated', [$this, 'clear_all_cache']);
        add_action('init', [$this, 'maybe_preload_banners'], 20);
        add_action('template_redirect', [$this, 'maybe_prime_page_cache']);
        add_action('shutdown', [$this, 'maybe_save_page_cache']);
    }

    protected function maybe_init_redis() {
        if (class_exists('Redis') && defined('REDIS_HOST')) {
            try {
                self::$redis = new Redis();
                self::$redis->connect(REDIS_HOST, defined('REDIS_PORT') ? REDIS_PORT : 6379);
                if (defined('REDIS_AUTH') && REDIS_AUTH) {
                    self::$redis->auth(REDIS_AUTH);
                }
                self::$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            } catch (Exception $e) {
                error_log('ABC Redis connection failed: ' . $e->getMessage());
            }
        }
    }

    public function get_banner($slug, $settings = []) {
        if ($this->should_bypass_cache()) {
            return ABC_DB::get_banner($slug);
        }

        $cache_key = $this->get_cache_key('banner', $slug, $settings);
        
        // Check memory cache first
        if (isset(self::$memory_cache[$cache_key])) {
            return self::$memory_cache[$cache_key];
        }
        
        // Try object cache
        $banner = wp_cache_get($cache_key, self::$cache_group);
        
        if ($banner === false) {
            // Try Redis
            if (self::$redis) {
                try {
                    $banner = self::$redis->get($cache_key);
                } catch (Exception $e) {
                    error_log('ABC Redis get failed: ' . $e->getMessage());
                }
            }
            
            // Fall back to database
            if ($banner === false) {
                $banner = ABC_DB::get_banner($slug);
                if ($banner) {
                    $this->set_banner_cache($cache_key, $banner);
                }
            }
        }
        
        // Store in memory cache
        if ($banner) {
            self::$memory_cache[$cache_key] = $banner;
        }
        
        return $banner;
    }

    protected function set_banner_cache($key, $banner) {
        // Set object cache
        wp_cache_set($key, $banner, self::$cache_group, self::$object_cache_time);
        
        // Set Redis if available
        if (self::$redis) {
            try {
                self::$redis->setex($key, self::$object_cache_time, $banner);
            } catch (Exception $e) {
                error_log('ABC Redis set failed: ' . $e->getMessage());
            }
        }
        
        // Set transient as fallback
        set_transient($key, $banner, self::$object_cache_time);
        
        // Update memory cache
        self::$memory_cache[$key] = $banner;
    }

    public function clear_banner_cache($identifier) {
        if (is_numeric($identifier)) {
            $banner = ABC_DB::get_banner_by_id($identifier);
            $identifier = $banner ? $banner->slug : $identifier;
        }
        
        $base_key = $this->get_cache_key('banner', $identifier);
        $this->clear_cache_by_prefix($base_key);
        
        // Clear Redis cache
        if (self::$redis) {
            try {
                $pattern = $base_key . '*';
                $keys = self::$redis->keys($pattern);
                if (!empty($keys)) {
                    self::$redis->del($keys);
                }
            } catch (Exception $e) {
                error_log('ABC Redis delete failed: ' . $e->getMessage());
            }
        }
        
        // Clear memory cache
        foreach (array_keys(self::$memory_cache) as $key) {
            if (strpos($key, $base_key) === 0) {
                unset(self::$memory_cache[$key]);
            }
        }
        
        // Clear parsed shortcodes
        unset(self::$parsed_shortcodes[$identifier]);
    }

    protected function clear_cache_by_prefix($prefix) {
        global $wpdb;
        
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group(self::$cache_group);
        } else {
            wp_cache_flush();
        }
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_' . $prefix . '%',
            '_transient_timeout_' . $prefix . '%'
        ));
    }

    public function handle_media_update($meta_id, $post_id, $meta_key, $_meta_value) {
        if ($meta_key === '_wp_attachment_metadata') {
            $this->clear_all_cache();
        }
    }

    public function clear_all_cache() {
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group(self::$cache_group);
        } else {
            wp_cache_flush();
        }
        
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_abc_%' 
             OR option_name LIKE '_transient_timeout_abc_%'"
        );
        
        if (self::$redis) {
            try {
                $keys = self::$redis->keys('abc_*');
                if (!empty($keys)) {
                    self::$redis->del($keys);
                }
            } catch (Exception $e) {
                error_log('ABC Redis flush failed: ' . $e->getMessage());
            }
        }
        
        self::$memory_cache = array();
        self::$parsed_shortcodes = array();
    }

    public function maybe_preload_banners() {
        if (!is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
            $banners = ABC_DB::get_lightweight_banners();
            foreach ($banners as $banner) {
                $cache_key = $this->get_cache_key('banner', $banner->slug);
                if (!isset(self::$memory_cache[$cache_key]) && 
                    wp_cache_get($cache_key, self::$cache_group) === false) {
                    $this->get_banner($banner->slug);
                }
            }
        }
    }

    public function maybe_prime_page_cache() {
        if (!$this->should_bypass_cache() && is_singular()) {
            global $post;
            $cache_key = 'page_' . $post->ID . '_' . md5($_SERVER['REQUEST_URI']);
            $cached = wp_cache_get($cache_key, self::$cache_group);
            if ($cached !== false) {
                echo $cached;
                exit;
            }
        }
    }

    public function maybe_save_page_cache() {
        if (!$this->should_bypass_cache() && is_singular()) {
            global $post;
            $cache_key = 'page_' . $post->ID . '_' . md5($_SERVER['REQUEST_URI']);
            $content = ob_get_clean();
            wp_cache_set($cache_key, $content, self::$cache_group, self::$page_cache_time);
            echo $content;
        }
    }

    protected function get_cache_key($type, $identifier, $settings = []) {
        $key_parts = [$type, $identifier];
        
        if (!empty($settings)) {
            ksort($settings);
            foreach ($settings as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                $key_parts[] = $key . '_' . $value;
            }
        }
        
        if (function_exists('get_locale')) {
            $key_parts[] = 'lang_' . get_locale();
        }
        
        if (is_user_logged_in()) {
            $key_parts[] = 'user_' . get_current_user_id();
        }
        
        return 'abc_' . md5(implode('_', $key_parts)) . '_' . ABC_VERSION;
    }

    public function should_bypass_cache() {
        if (defined('WP_DEBUG') && WP_DEBUG) return true;
        if (is_admin()) return true;
        if (defined('DOING_AJAX') && DOING_AJAX) return true;
        if (defined('WP_CLI') && WP_CLI) return true;
        if (defined('DOING_CRON') && DOING_CRON) return true;
        return false;
    }

    public static function init() {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new self();
        }
        return $instance;
    }
}
