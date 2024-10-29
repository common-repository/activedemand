<?php

/**
 * Plugin Name: ActiveDEMAND
 * Plugin URI: https://www2.activedemand.com/s/Gnf5n
 * Description: Adds the <a href="https://www2.activedemand.com/s/SW5nU">ActiveDEMAND</a> tracking script to your website. Add custom popups, use shortcodes to embed webforms and dynamic website content.
 * Version: 0.2.45
 * Author: JumpDEMAND Inc.
 * Author URI: https://www2.activedemand.com/s/SW5nU
 * License:GPL-2.0+
 * License URI:http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace ActiveDemand;


define(__NAMESPACE__ . '\ACTIVEDEMAND_VER', '0.2.45');
define(__NAMESPACE__ . "\PLUGIN_VENDOR", "ActiveDEMAND");
define(__NAMESPACE__ . "\PLUGIN_VENDOR_LINK", "https://1jp.cc/s/SW5nU");
define(__NAMESPACE__ . "\PREFIX", 'activedemand');
define(__NAMESPACE__ . "\API_URL", 'https://api.activedemand.com/v1/');

require plugin_dir_path(__FILE__) . 'class-SCCollector.php';
require plugin_dir_path(__FILE__) . 'linked-forms.php';
require plugin_dir_path(__FILE__) . 'settings.php';
require plugin_dir_path(__FILE__) . 'landing-pages.php';


//--------------- AD update path --------------------------------------------------------------------------
function activedemand_update()
{

    //get ensure a cookie is set. This call creates a cookie if one does not exist
    activedemand_get_cookie_value();

    $key = PREFIX . '_version';
    $version = get_option($key);

    if (ACTIVEDEMAND_VER === $version) return;
    activedemand_plugin_activation();
    update_option($key, ACTIVEDEMAND_VER);
}

add_action('init', __NAMESPACE__ . '\activedemand_update');


function activedemand_gutenberg_blocks()
{
    if (!function_exists('register_block_type')) {
        return false;
    }

    if (get_option(PREFIX . '_show_gutenberg_blocks', TRUE)) {
        $available_blocks = array(
            array(
                'label' => 'Select a block',
                'value' => 0
            )
        );

        $available_forms = array(
            array(
                'label' => 'Select a form',
                'value' => 0
            )
        );

        $available_storyboard = array(
            array(
                'label' => 'Select a story board',
                'value' => 0
            )
        );

        if (is_admin()) {
            $blocks_cache_key = 'activedemand_blocks';
            $forms_cache_key = 'activedemand_forms';
            $storyboard_cache_key = 'activedemand_storyboard';

            $blocks = get_option($blocks_cache_key);
            $forms = get_option($forms_cache_key);
            $storyboard = get_option($storyboard_cache_key);

            if (!$blocks) {
                $url = activedemand_api_url("smart_blocks.json");
                $blocks = activedemand_getHTML($url, 10);
                update_option($blocks_cache_key, $blocks);
            }

            if (!$forms) {
                $url = activedemand_api_url("forms.json");
                $forms = activedemand_getHTML($url, 10);
                update_option($forms_cache_key, $forms);
            }

            if (!$storyboard) {
                $url = activedemand_api_url("dynamic_story_boards.json");
                $storyboard = activedemand_getHTML($url, 10);
                update_option($storyboard_cache_key, $storyboard);
            }

            $activedemand_blocks = json_decode($blocks);
            $activedemand_forms = json_decode($forms);
            $activedemand_storyboard = json_decode($storyboard);

            if (is_array($activedemand_blocks)) {
                foreach ($activedemand_blocks as $block) {
                    $available_blocks[] = array(
                        'label' => $block->name,
                        'value' => $block->id
                    );
                }
            }

            if (is_array($activedemand_forms)) {
                foreach ($activedemand_forms as $form) {
                    $available_forms[] = array(
                        'label' => $form->name,
                        'value' => $form->id
                    );
                }
            }

            if (is_array($activedemand_storyboard)) {
                foreach ($activedemand_storyboard as $storyboard) {
                    $available_storyboard[] = array(
                        'label' => $storyboard->name,
                        'value' => $storyboard->id
                    );
                }
            }
        }

        /*register js for dynamic blocks block*/
        wp_register_script(
            'activedemand_blocks',
            plugins_url('gutenberg-blocks/dynamic-content-blocks/block.build.js', __FILE__),
            array('wp-blocks', 'wp-element')
        );

        /*pass dynamic blocks list to js*/
        wp_localize_script('activedemand_blocks', 'activedemand_blocks', $available_blocks);

        /* pass vendor name to js*/
        wp_localize_script('activedemand_blocks', 'activedemand_vendor', array(PLUGIN_VENDOR));

        /*register gutenberg block for dynamic blocks*/
        register_block_type('activedemand/content-block', array(
            'attributes' => array(
                'block_id' => array(
                    'type' => 'number'
                )
            ),
            'render_callback' => __NAMESPACE__ . '\activedemand_render_dynamic_content_block',
            'editor_script' => 'activedemand_blocks',
        ));


        /*register js for forms block*/
        wp_register_script(
            'activedemand_forms',
            plugins_url('gutenberg-blocks/forms/block.build.js', __FILE__),
            array('wp-blocks', 'wp-element')
        );

        /*pass forms list to js*/
        wp_localize_script('activedemand_forms', 'activedemand_forms', $available_forms);

        /*register gutenberg block for forms*/
        register_block_type('activedemand/form', array(
            'attributes' => array(
                'form_id' => array(
                    'type' => 'number'
                )
            ),
            'render_callback' => __NAMESPACE__ . '\activedemand_render_form',
            'editor_script' => 'activedemand_forms'
        ));


        /*register js for storyboard block*/
        wp_register_script(
            'activedemand_storyboard',
            plugins_url('gutenberg-blocks/storyboard/block.build.js', __FILE__),
            array('wp-blocks', 'wp-element')
        );

        /*pass storyboard list to js*/
        wp_localize_script('activedemand_storyboard', 'activedemand_storyboard', $available_storyboard);

        /*register gutenberg block for storyboard*/
        register_block_type('activedemand/storyboard', array(
            'attributes' => array(
                'storyboard_id' => array(
                    'type' => 'number'
                )
            ),
            'render_callback' => __NAMESPACE__ . '\activedemand_render_storyboard',
            'editor_script' => 'activedemand_storyboard'
        ));


        /*register gutenberg block category (ActiveDemand Blocks)*/
        add_filter('block_categories_all', __NAMESPACE__ . '\activedemand_block_category', 10, 2);
    }
}

add_action('init', __NAMESPACE__ . '\activedemand_gutenberg_blocks');

function activedemand_render_dynamic_content_block($params)
{
    $block_id = isset($params['block_id']) ? (int)$params['block_id'] : 0;
    if ($block_id) {
        return do_shortcode("[activedemand_block id='$block_id']");
    }
}

function activedemand_block_category($categories, $post)
{
    return array_merge(
        $categories,
        array(
            array(
                'slug' => 'activedemand-blocks',
                'title' => PLUGIN_VENDOR . ' ' . __('Blocks', 'activedemand-blocks'),
            ),
        )
    );
}

function activedemand_render_form($params)
{
    $form_id = isset($params['form_id']) ? (int)$params['form_id'] : 0;
    if ($form_id) {
        return do_shortcode("[activedemand_form id='$form_id']");
    }
}

function activedemand_render_storyboard($params)
{
    $storyboard_id = isset($params['storyboard_id']) ? (int)$params['storyboard_id'] : 0;
    if ($storyboard_id) {
        return do_shortcode("[activedemand_storyboard id='$storyboard_id']");
    }
}

//---------------Version Warning---------------------------//
/**function phpversion_warning_notice(){
 * if(!((int)phpversion()<7)) return;
 * $class='notice notice-warning is-dismissible';
 *
 * $message=(__(PLUGIN_VENDOR.' will deprecate PHP5 support soon -- we recommend updating to PHP7.'));
 * printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
 * }
 * add_action('admin_notices', __NAMESPACE__.'\phpversion_warning_notice');
 */
//--------------- AD Server calls -------------------------------------------------------------------------

function activedemand_api_url($path)
{
    return API_URL . $path;
}

function activedemand_getHTML($url, $timeout, $args = array())
{
    $result = false;
    $fields_string = activedemand_field_string($args, activedemand_api_key());
    $response = wp_remote_get(
        $url . "?" . $fields_string,
        array(
            'timeout' => $timeout,
            'sslverify' => true,
        )
    );

    if (is_array($response) && isset($response['body']) && isset($response['response']['code']) && (int)$response['response']['code'] == 200) {
        $result = $response['body'];
    }

    return $result;
}

function activedemand_postHTML($url, $args, $timeout)
{
    $result = false;
    $fields_string = activedemand_field_string($args, activedemand_api_key());
    $response = wp_remote_post(
        $url,
        array(
            'method' => 'POST',
            'timeout' => $timeout,
            'body' => $fields_string,
            'sslverify'     => true
        )
    );

    if (is_array($response) && isset($response['body']) && isset($response['response']['code']) && (int)$response['response']['code'] == 200) {
        $result = $response['body'];
    }

    return $result;
}

/**
 * Adds ActiveDEMAND popups if API Key isset and activedemand_server_showpopups is true
 *
 * @param string $content
 * @return string $content with popup prefix
 */

function activedemand_api_key()
{
    $options = retrieve_activedemand_options();
    if (is_array($options) && array_key_exists(PREFIX . '_appkey', $options)) {
        $activedemand_appkey = $options[PREFIX . "_appkey"];
    } else {
        $activedemand_appkey = "";
    }

    return $activedemand_appkey;
}

function activedemand_version()
{
	return ACTIVEDEMAND_VER;
}

function activedemand_field_string($args, $activedemand_appkey = '')
{
	$fields_string       = "";
    $cookievalue = activedemand_get_cookie_value();
    $url = "";
    if (!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI'])) {
		$url = home_url( add_query_arg( null, null ) );

	    if (isset($_SERVER['HTTP_REFERER'])) {
	        $referrer = sanitize_url($_SERVER['HTTP_REFERER']);
	    } else {
	        $referrer = "";
	    }
	    $fields = array(
	        'url' => $url,
	        'ip_address' => activedemand_get_ip_address(),
	        'referer' => $referrer,
	        'user_agent' => isset($_SERVER["HTTP_USER_AGENT"]) ? sanitize_text_field($_SERVER["HTTP_USER_AGENT"]) : null,
	        'version' => ACTIVEDEMAND_VER
	    );
	    if ($activedemand_appkey != "") {
	        $fields['api-key'] = $activedemand_appkey;
	    }
	    if ($cookievalue != "") {
	        $fields['activedemand_session_guid'] = $cookievalue;
	    }
	    if (is_array($args)) {
	        $fields = array_merge($fields, $args);
	    }
	    $fields_string = http_build_query($fields);
	}

	return $fields_string;
}

add_action('init', __NAMESPACE__ . '\activedemand_get_cookie_value');

function activedemand_get_cookie_value()
{
    //if (is_admin()) return "";

    static $cookieValue = "";

    if (!empty($cookieValue)) return $cookieValue;
    //not editing an options page etc.

    if (!empty($_COOKIE['activedemand_session_guid'])) {
        $cookieValue = sanitize_text_field($_COOKIE['activedemand_session_guid']);
    } else {
        $server_side = get_option(PREFIX . '_server_side', TRUE);
        if ($server_side) {
            $urlParms = NULL;
            if (!empty($_SERVER['HTTP_HOST'])) {
                $urlParms = sanitize_url($_SERVER['HTTP_HOST']);
            }
            if (NULL != $urlParms) {
                $cookieValue = activedemand_get_GUID();
                $basedomain = activedemand_get_basedomain();
                @setcookie('activedemand_session_guid', $cookieValue, time() + (60 * 60 * 24 * 365 * 10), "/", $basedomain);
            }
        }
    }

    return $cookieValue;
}


function activedemand_get_basedomain()
{
    $result = "";

    $urlParms = NULL;
    if (!empty($_SERVER['HTTP_HOST'])) {
        $urlParms = sanitize_url($_SERVER['HTTP_HOST']);
    }
    if (NULL != $urlParms) {
        $result = str_replace('www.', "", $urlParms);
    }
    return $result;
}

// create a session if one doesn't exist
function activedemand_get_GUID()
{
    if (function_exists('com_create_guid')) {
        return com_create_guid();
    } else {
        mt_srand((float)microtime() * 10000); //optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45); // "-"
        $uuid = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }
}


// get the ip address
function activedemand_get_ip_address()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
        $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
        $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
    } else {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }
    return $ip;
}

//--------------- Admin Menu -------------------------------------------------------------------------
function activedemand_menu()
{
    global $activedemand_plugin_hook;
    $activedemand_plugin_hook = add_options_page(PLUGIN_VENDOR . ' options', PLUGIN_VENDOR, 'manage_options', PREFIX . '_options', __NAMESPACE__ . '\activedemand_plugin_options');
    add_action('admin_init', __NAMESPACE__ . '\register_activedemand_settings');
}

function retrieve_activedemand_options()
{
    $options = is_array(get_option(PREFIX . '_options_field')) ? get_option(PREFIX . '_options_field') : array();
    $woo_options = is_array(get_option(PREFIX . '_woocommerce_options_field')) ? get_option(PREFIX . '_woocommerce_options_field') : array();
    if (!empty($options) && !empty($woo_options)) {
        return \array_merge($options, $woo_options);
    }
    return $options;
}

function register_activedemand_settings()
{
    register_setting(PREFIX . '_options', PREFIX . '_options_field');
    register_setting(PREFIX . '_woocommerce_options', PREFIX . '_woocommerce_options_field');
    register_setting(PREFIX . '_options', PREFIX . '_server_showpopups');
    register_setting(PREFIX . '_options', PREFIX . '_show_tinymce');
    register_setting(PREFIX . '_options', PREFIX . '_show_gutenberg_blocks');
    register_setting(PREFIX . '_options', PREFIX . '_server_side');
    register_setting(PREFIX . '_options', PREFIX . '_v2_script_url');

    register_setting(PREFIX . '_woocommerce_options', PREFIX . '_stale_cart_map');
    register_setting(PREFIX . '_woocommerce_options', PREFIX . '_wc_actions_forms');
}


function activedemand_enqueue_scripts()
{
    $script_url = get_option(PREFIX . '_v2_script_url');
    if (!isset($script_url) || "" == $script_url) {
        $activedemand_appkey = activedemand_api_key();
        if ("" != $activedemand_appkey) {
            $script_url = activedemand_getHTML(activedemand_api_url("script_url"), 10);
            update_option(PREFIX . '_v2_script_url', $script_url);
        }
    }

    $options = retrieve_activedemand_options();
    if (array_key_exists(PREFIX . '_multi_account_site', $options) && $options[PREFIX . '_multi_account_site']) {
        $script_url = 'https://static.activedemand.com/public/javascript/ad.collect.min.js.jgz#adtoken';
    }

    wp_enqueue_script('ActiveDEMAND-Track', $script_url);
}


function activedemand_admin_enqueue_scripts()
{
    global $pagenow;

    if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
    }
}

function activedemand_plugin_action_links($links, $file)
{
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . PREFIX . '_options">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


function get_base_url()
{
    return plugins_url(null, __FILE__);
}

function activedemand_register_tinymce_javascript($plugin_array)
{
    $plugin_array['activedemand'] = plugins_url('/js/tinymce-plugin.js', __FILE__);
    return $plugin_array;
}


function activedemand_buttons()
{
    add_filter("mce_external_plugins", __NAMESPACE__ . '\activedemand_add_buttons');
    add_filter('mce_buttons', __NAMESPACE__ . '\activedemand_register_buttons');
}

function activedemand_add_buttons($plugin_array)
{
    $plugin_array['activedemand'] = get_base_url() . '/includes/activedemand-plugin.js';
    return $plugin_array;
}

function activedemand_register_buttons($buttons)
{
    array_push($buttons, 'insert_form_shortcode');
    return $buttons;
}


function activedemand_add_editor()
{
    global $pagenow;

    // Add html for shortcodes popup
    if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
        // echo "Including Micey!";
        include plugin_dir_path(__FILE__) . 'partials/tinymce-editor.php';
    }
}

function activedemand_clean_url($url)
{
    if (TRUE == strpos($url, '#adtoken')) {
        return str_replace('#adtoken', '', $url) . "' defer='defer' async='async";
    }

    return $url;
}

//Constant used to track stale carts
define(__NAMESPACE__ . '\AD_CARTTIMEKEY', 'ad_last_cart_update');

/**
 * Adds cart timestamp to usermeta
 */
function activedemand_woocommerce_cart_update()
{
    $user_id = get_current_user_id();
    update_user_meta($user_id, AD_CARTTIMEKEY, time());

    if ($user_id && isset($_COOKIE['active_demand_cookie_cart']) && $key = sanitize_text_field($_COOKIE['active_demand_cookie_cart'])) {
        update_user_meta($user_id, AD_CARTTIMEKEY . '_key', $key);
    }
}

add_action('woocommerce_cart_updated', __NAMESPACE__ . '\activedemand_woocommerce_cart_update');

/**
 * Deletes timestamp from current user meta
 */
function activedemand_woocommerce_cart_emptied()
{
    $user_id = get_current_user_id();
    delete_user_meta($user_id, AD_CARTTIMEKEY);
    delete_user_meta($user_id, AD_CARTTIMEKEY . '_key');
}

add_action('woocommerce_cart_emptied', __NAMESPACE__ . '\activedemand_woocommerce_cart_emptied');

/**Periodically scans, and sends stale carts to activedemand
 *
 * @global object $wpdb
 *
 * @uses activedemand_send_stale_carts function to process and send
 */

function activedemand_woocommerce_scan_stale_carts()
{
    if (!class_exists('WooCommerce')) return;

    global $wpdb;
    $options = retrieve_activedemand_options();
    $hours = $options['woocommerce_stalecart_hours'];

    $stale_secs = $hours * 60 * 60;

    $carts = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->usermeta . ' WHERE meta_key=%s', AD_CARTTIMEKEY));
    $blog_id = get_current_blog_id();

    $stale_carts = array();
    $i = 0;
    foreach ($carts as $cart) {
        if ((time() - (int)$cart->meta_value) > $stale_secs) {
            $stale_carts[$i]['user_id'] = $cart->user_id;
            $stale_carts[$i]['cart_key'] = get_user_meta($cart->user_id, AD_CARTTIMEKEY . '_key', true);
            $meta = get_user_meta($cart->user_id, '_woocommerce_persistent_cart', TRUE);
            if (empty($meta)) {
                $meta = get_user_meta($cart->user_id, '_woocommerce_persistent_cart_' . $blog_id, TRUE);
            }
            $stale_carts[$i]['cart'] = $meta;
            $i++;
        }
    }

    activedemand_send_stale_carts($stale_carts);
}

add_action(PREFIX . '_hourly', __NAMESPACE__ . '\activedemand_woocommerce_scan_stale_carts');

register_activation_hook(__FILE__, __NAMESPACE__ . '\activedemand_plugin_activation');

function activedemand_plugin_activation()
{
    global $wpdb;
    include_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name = $wpdb->prefix . 'cart';

    $charset_collate = $wpdb->get_charset_collate();

    $cart_table_sql = "CREATE TABLE $table_name (
      `id_cart` int(10) NOT NULL AUTO_INCREMENT,
      `cookie_cart_id` varchar(32) NOT NULL,
      `cart_key` VARCHAR(512),
      `id_customer` int(10) NOT NULL,
      `currency` varchar(32) NOT NULL,
      `language` varchar(32) NOT NULL,
      `date_add` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id_cart`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    dbDelta($cart_table_sql);

    $table_name_two = $wpdb->prefix . 'cart_product';

    $cart_product_table_sql = "CREATE TABLE $table_name_two (
      `id_cart` int(10) NOT NULL,
      `id_product` int(10) NOT NULL,
      `quantity` int(10) NOT NULL,
      `id_product_variation` int(10) NOT NULL,
      `date_add` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    dbDelta($cart_product_table_sql);


    $table_name_three = $wpdb->prefix . 'activedemand_access';

    $activedemand_access = "CREATE TABLE $table_name_three (
        `id_access` int(11) NOT NULL AUTO_INCREMENT,
		`object_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
		`match` int(11) NOT NULL,
		PRIMARY KEY (`id_access`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    dbDelta($activedemand_access);

    $table_name_four = $wpdb->prefix . 'activedemand_access_rule';

    $activedemand_access_rule = "CREATE TABLE $table_name_four (
        `id_rule` int(11) NOT NULL AUTO_INCREMENT,
  		`id_access` int(11) NOT NULL,
        `url` varchar(128) NOT NULL,
  		PRIMARY KEY (`id_rule`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    dbDelta($activedemand_access_rule);


    if (!wp_next_scheduled(PREFIX . '_hourly')) wp_schedule_event(time(), 'hourly', PREFIX . '_hourly');
}

register_deactivation_hook(__FILE__, __NAMESPACE__ . '\activedemand_plugin_deactivation');

function activedemand_plugin_deactivation()
{
    wp_clear_scheduled_hook(__NAMESPACE__ . '\\' . PREFIX . '_hourly');
    wp_clear_scheduled_hook(PREFIX . '_hourly');
}

/**Processes and send stale carts
 * Delete the timestamp so carts are only used once
 *
 * @param array $stale_carts
 *
 * @used-by activedemand_woocommerce_scan_stale_carts
 * @uses    function _activedemand_send_stale cart to send each cart individually
 */
function activedemand_send_stale_carts($stale_carts)
{
    //$setting=get_setting(PREFIX.'_stale_cart_map');
    //$setting=get_option(PREFIX.'_stale_cart_map');

    $setting = get_option(PREFIX . '_form_' . PREFIX . '_stale_cart_map');

    if (!$setting || empty($setting)) return;
    if (!isset($setting['id']) || !isset($setting['map'])) return;
    $activedemand_form_id = $setting['id'];

    $url = activedemand_api_url("forms/$activedemand_form_id");
    foreach ($stale_carts as $cart) {
        $user = new \WC_Customer($cart['user_id']);
        $form_data = FormLinker::map_field_keys($setting['map'], array(
            'user' => $user,
            'cart' => $cart
        ));

        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => activedemand_api_key()
            ),
            'body' => $form_data
        ));

        if (is_wp_error($response)) {
            $msg = $response->get_error_message();
            new \WP_Error($msg);
        }

        delete_user_meta($user->get_id(), AD_CARTTIMEKEY);
        delete_user_meta($user->get_id(), AD_CARTTIMEKEY . '_key');
    }
}


add_filter('clean_url', __NAMESPACE__ . '\activedemand_clean_url', 11, 1);
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\activedemand_enqueue_scripts');

add_action('admin_enqueue_scripts', __NAMESPACE__ . '\activedemand_admin_enqueue_scripts');

add_action('admin_menu', __NAMESPACE__ . '\activedemand_menu');
add_filter('plugin_action_links', __NAMESPACE__ . '\activedemand_plugin_action_links', 10, 2);

add_filter('the_excerpt_rss', __NAMESPACE__ . '\activedemand_plugin_rss_post_thumbnail', 10, 2);

add_filter('the_content_feed', __NAMESPACE__ . '\activedemand_plugin_rss_post_thumbnail', 10, 2);

//widgets
// add new buttons

if (get_option(PREFIX . '_show_tinymce', TRUE)) {
    add_action('init', __NAMESPACE__ . '\activedemand_buttons');
    add_action('in_admin_footer', __NAMESPACE__ . '\activedemand_add_editor');
}

add_action('woocommerce_after_checkout_form', function () {
    echo "
  <script type='text/javascript'>
    jQuery(document).ready(function($){
      $('script[src$=\"ad.collect.min.js.jgz\"]').load(function(){
        AD.ready(function(){
            AD.flink();
          });
      });
    });
    </script>";
});

//add fetaured image to rss feed
function activedemand_plugin_rss_post_thumbnail($content)
{
    global $post;
    if (has_post_thumbnail($post->ID)) {
        $content = '<p>' . get_the_post_thumbnail($post->ID, 'large') .
            '</p>' . get_the_content();
    }
    return $content;
}

add_action('rss2_item', function () {
    global $post;

    $output = '';
    $tags = get_the_tags($post->ID);
    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $output .= '<ad:tag>' . $tag->name . '</ad:tag>';
        }
    }
    $categories = get_the_category($post->ID);
    if (!empty($categories)) {
        foreach ($categories as $category) {
            $output .= '<ad:category>' . $category->name . '</ad:category>';
        }
    }

    echo $output;
});

function set_active_demand_cookie()
{
    if (!get_option('run_only_once_indexes')) :
        global $wpdb;
        $table_name_1 = $wpdb->prefix . 'cart_product';
        $table_name_2 = $wpdb->prefix . 'cart';
        $wpdb->query("ALTER TABLE $table_name_1
         ADD INDEX `id_cart` (`id_cart`)");
        $wpdb->query("ALTER TABLE $table_name_1
         ADD INDEX `id_product` (`id_product`)");
        $wpdb->query("ALTER TABLE $table_name_1
         ADD INDEX `id_product_variation` (`id_product_variation`)");
        add_option('run_only_once_indexes', 1);
        $wpdb->query("ALTER TABLE $table_name_2
         ADD INDEX `cookie_cart_id` (`cookie_cart_id`)");
        $wpdb->query("ALTER TABLE $table_name_2
         ADD INDEX `id_customer` (`id_customer`)");
        add_option('run_only_once_indexes', 1);
    endif;
    if (!isset($_COOKIE['active_demand_cookie_cart'])) {

        set_cookie('active_demand_cookie_cart', uniqid(), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
}

add_action('init', __NAMESPACE__ . '\set_active_demand_cookie', 10);


function active_demand_recover_cart()
{
    global $wpdb, $woocommerce;
    $redirect = false;

    if (isset($_GET['recover-cart']) && $cookie_cart_id = sanitize_text_field($_GET['recover-cart'])) {
        $id_cart = $wpdb->get_var($wpdb->prepare('SELECT id_cart FROM {$wpdb->prefix}cart WHERE cookie_cart_id = %s', $cookie_cart_id));
        if ($id_cart) {
            $products_to_recover = $wpdb->get_results($wpdb->prepare('SELECT * FROM {$wpdb->prefix}cart_product WHERE id_cart = %n', (int)$id_cart));
            $woocommerce->session->set_customer_session_cookie(true);

            WC()->cart->empty_cart();

            foreach ($products_to_recover as $product_to_recover_key => $product_to_recover) {
                $id_product = $product_to_recover->id_product;
                $quantity = $product_to_recover->quantity;
                $variation_id = isset($product_to_recover->variation_id) ? $product_to_recover->variation_id : '';
                $product_cart_id = WC()->cart->generate_cart_id($id_product);

                if (!WC()->cart->find_product_in_cart($product_cart_id)) {
                    WC()->cart->add_to_cart($id_product, $quantity, $variation_id);
                }
            }
            $redirect = true;
        }
    } elseif (isset($_GET['recover-order']) && $id_order = sanitize_text_field($_GET['recover-order'])) {
        $order = wc_get_order($id_order);
        if (empty($order)) {
            return;
        }
        $items = $order->get_items();
        WC()->cart->empty_cart();

        foreach ($items as $item) {
            $id_product = $item->get_product_id();
            $quantity = $item->get_quantity();
            $variation_id = $item->get_variation_id();

            $product_cart_id = WC()->cart->generate_cart_id($id_product);

            if (!WC()->cart->find_product_in_cart($product_cart_id)) {
                WC()->cart->add_to_cart($id_product, $quantity, $variation_id);
            }
        }

        $redirect = true;
    }

    if ($redirect) {
        $cart_page_id = wc_get_page_id('cart');
        $cart_page_url = $cart_page_id ? get_permalink($cart_page_id) : '';
        wp_redirect($cart_page_url, 302);
        exit;
    }
}

add_action('init', __NAMESPACE__ . '\active_demand_recover_cart');

function activedemand_save_add_to_cart()
{
    global $wpdb;

    $active_demand_cookie_cart = sanitize_text_field($_COOKIE['active_demand_cookie_cart']);

    if (!$active_demand_cookie_cart) {
        return false;
    }

    $user_id = get_current_user_id();
    $lang = get_bloginfo("language");
    $currency = get_option('woocommerce_currency');
    $id_cart = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id_cart FROM {$wpdb->prefix}cart WHERE id_customer = %d AND cookie_cart_id = %s ",
            array($user_id, $active_demand_cookie_cart)
        )
    );

    if (!$id_cart) {
        $wpdb->insert(
            $wpdb->prefix . "cart",
            array(
                'cookie_cart_id' => $active_demand_cookie_cart,
                'id_customer' => $user_id,
                'currency' => $currency,
                'language' => $lang,
                'date_add' => current_time('mysql'),
            )
        );

        $id_cart = $wpdb->insert_id;
    }

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $id_product = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        $variation_id = $cart_item['variation_id'];

        $cart_product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cp.id_cart FROM {$wpdb->prefix}cart_product cp LEFT JOIN {$wpdb->prefix}cart c ON cp.id_cart = c.id_cart WHERE cp.id_product = %d AND cp.id_product_variation = %d AND c.cookie_cart_id = %s ",
                array($id_product, $variation_id, $active_demand_cookie_cart)
            )
        );

        $current_url = home_url(sanitize_url($_SERVER['REQUEST_URI']));
		// what's this?  it was removed a long time ago, do we need it still?
	    // https://github.com/JumpDEMANDEngineering/activedemand-wordpress-plugin/commit/1f148a1e50941c6b08e00b857d63e013269b73e9
	    $cart_id = "";

        if (strpos($current_url, 'cart-key') == false) {

            if (!$cart_product_id) {
                $cart_products = array(
                    'id_cart' => isset($id_cart) ? $id_cart : $cart_id,
                    'id_product' => $id_product,
                    'quantity' => $quantity,
                    'id_product_variation' => $variation_id,
                    'date_add' => current_time('mysql'),
                );
                $wpdb->insert($wpdb->prefix . "cart_product", $cart_products);
            } else {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cart_product SET quantity = %n WHERE  id_product = %s AND id_product_variation = %n AND id_cart = %s",
	                $quantity, $id_product, (int)$variation_id, $id_cart));
            }
        }
    }
}

add_action('woocommerce_add_to_cart', __NAMESPACE__ . '\activedemand_save_add_to_cart', 10, 2);

//delete cookie
function activedemand_delete_cookie_cart($order_id)
{
    set_cookie('active_demand_cookie_cart', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}

add_action('woocommerce_thankyou', __NAMESPACE__ . '\activedemand_delete_cookie_cart');


add_action('wp_ajax_activedemand_access_rules_save', __NAMESPACE__ . '\activedemand_access_rules_save');
add_action('wp_ajax_nopriv_activedemand_access_rules_save', __NAMESPACE__ . '\activedemand_access_rules_save');

function activedemand_access_rules_save()
{

    if (!empty($_POST)) {
        global $wpdb;
        $table_access = '' . $wpdb->prefix . 'activedemand_access';
        $table_access_rule = '' . $wpdb->prefix . 'activedemand_access_rule';

        if ($_POST['method'] == "activedemand_enable_access_control") {
            if (!get_option(PREFIX . '_enable_access_control') && get_option(PREFIX . '_enable_access_control') != 0) {

                add_option(PREFIX . '_enable_access_control', sanitize_text_field($_POST['activedemand_enable_access_control']));
            } else {
                update_option(PREFIX . '_enable_access_control', sanitize_text_field($_POST['activedemand_enable_access_control']));
            }
        }

        if ($_POST['method'] == "activedemand_save_rules") {
            foreach ($_POST['custom_url_content'] as $custom_url_content) {
                if ($custom_url_content['custom_url'] != '') {

                    $existing_id_access = $wpdb->get_row(
                        $wpdb->prepare("SELECT id_access FROM $table_access WHERE object_key = %s ", array(sanitize_text_field($_POST['access_object_key'])))
                    );

                    if ($existing_id_access) {
                        $success_access = $wpdb->update(
                            $table_access,
                            array(
                                'match' => sanitize_text_field($_POST['access_match']),
                            ),
                            array('object_key' => sanitize_text_field($_POST['access_object_key']))
                        );


                        $existing_rules = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM $table_access_rule WHERE id_rule = %d ", array(sanitize_text_field($custom_url_content['id_rule'])))
                        );


                        if (!$existing_rules) {
                            $data_access_rule_1 = array(
                                'id_access' => $existing_id_access->id_access,
                                'url' => sanitize_url($custom_url_content['custom_url']),
                            );

                            $success_access_rule_1 = $wpdb->insert($table_access_rule, $data_access_rule_1);
                        }
                    } else {
                        $data = array(
                            'object_key' => sanitize_text_field($_POST['access_object_key']),
                            'match' => sanitize_text_field($_POST['access_match']),
                        );

                        $success = $wpdb->insert($table_access, $data);
                        $id_access = $wpdb->insert_id;

                        if ($id_access) {
                            $data_access_rule = array(
                                'id_access' => $id_access,
                                'url' => sanitize_url($custom_url_content['custom_url']),
                            );

                            $success_access_rule = $wpdb->insert($table_access_rule, $data_access_rule);
                            var_dump($success_access_rule);
                            exit();
                        }
                    }
                }
            }
        }


        if ($_POST['method'] == "get_url_object_key") {

            $resp = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ar.url, a.match , ar.id_rule FROM $table_access_rule ar
            	    LEFT JOIN $table_access a ON ar.id_access = a.id_access where object_key = %s ",
                    array(sanitize_text_field($_POST['valid_content']))
                )
            );

            echo json_encode($resp);
        }
    }

    wp_die();
}


add_action('wp_ajax_activedemand_delete_custom_url_content', __NAMESPACE__ . '\activedemand_delete_custom_url_content');
add_action('wp_ajax_nopriv_activedemand_delete_custom_url_content', __NAMESPACE__ . '\activedemand_delete_custom_url_content');

function activedemand_delete_custom_url_content()
{

    if (!empty($_POST)) {
        global $wpdb;
        $id_rule = sanitize_text_field($_POST['id_rule']);
        $table = '' . $wpdb->prefix . 'activedemand_access_rule';
        $wpdb->delete($table, array('id_rule' => $id_rule));
    }

    wp_die();
}

add_action('init', __NAMESPACE__ . '\activedemand_matches_redirect');

function activedemand_matches_redirect()
{

    global $wpdb;

    if (!is_admin() && !current_user_can('administrator')) {
        if (get_option(PREFIX . '_enable_access_control') == 1) {

            $table_access = '' . $wpdb->prefix . 'activedemand_access';
            $table_access_rule = '' . $wpdb->prefix . 'activedemand_access_rule';

            $activedemand_appkey = activedemand_api_key();

            $current_url_param = strtok(sanitize_url($_SERVER['REQUEST_URI']), '?');

            $get_results_match = $wpdb->get_results(
                $wpdb->prepare("SELECT ar.url, a.match , a.object_key, ar.id_rule FROM " . $table_access_rule . " ar
            	LEFT JOIN " . $table_access . " a ON ar.id_access = a.id_access WHERE a.match = 1 AND " .
                    "(
                    url = %s OR %s LIKE REPLACE(url, '.*', '%')
                )", $current_url_param, $current_url_param)
            );

            $redirect_url = null;

            $match_found = false;

            foreach ($get_results_match as $key => $result) {

                if (!$redirect_url) {
                    $match_found = true;

                    $start_at = strpos($result->object_key, '_') + 1;
                    $end_at = strlen($result->object_key) - 1;
                    $object_id = substr($result->object_key, $start_at, $end_at - $start_at);

                    $object_url = activedemand_api_url("contacts/field.json") . "?api-key=" . $activedemand_appkey . "&field_key=custom_" . $object_id . "";
                    $object_fields = activedemand_getHTML($object_url, 10);
                    $object_key = json_decode($object_fields);


                    if (!empty($object_key)) {
                        $loggin_status = get_access_login_status($object_id);

                        if (!$object_id || !$loggin_status) {
                            $redirect_url = $object_key->login_url;
                        }
                    }
                }
            }


            if (!$match_found) {
                $get_does_not_match = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ar.url, a.match , a.object_key, ar.id_rule FROM $table_access_rule ar
            	        LEFT JOIN $table_access a ON ar.id_access = a.id_access where a.match = %d",
                        array(0)
                    )
                );

                foreach ($get_does_not_match as $key => $result) {

                    if (!$redirect_url && !preg_match('#\\b' . $result->url . '\\b#', $current_url_param)) {

                        $start_at = strpos($result->object_key, '_') + 1;
                        $end_at = strlen($result->object_key) - 1;
                        $object_id = substr($result->object_key, $start_at, $end_at - $start_at);

                        $object_url = activedemand_api_url("contacts/field.json") . "?api-key=" . $activedemand_appkey . "&field_key=custom_" . $object_id . "";
                        $object_fields = activedemand_getHTML($object_url, 10);
                        $object_key = json_decode($object_fields);

                        if (!empty($object_key)) {
                            $loggin_status = get_access_login_status($object_id);

                            if (!$object_id || !$loggin_status) {
                                $redirect_url = $object_key->login_url;
                            }
                        }
                    }
                }
            }

            if ($redirect_url) {
                //wp_redirect($redirect_url);
                header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                header('Location:' . $redirect_url, true, 302);
                exit();
            }
        }
    }
}

function get_access_login_status($object_id)
{
    $activedemand_appkey = activedemand_api_key();
    $loggin_status = false;
    if (isset($_COOKIE['acf_session_' . $object_id]) && !isset($_COOKIE['acf_access_login_status_' . $object_id])) {
        $login_status_url = activedemand_api_url("contacts/login_status.xml") . "?api-key=" . $activedemand_appkey . "";
        $args = array('cookie' => sanitize_text_field($_COOKIE['acf_session_' . $object_id]), 'custom_field_type_id' => $object_id);
        $timeout = 10;
        $login_status_str = activedemand_postHTML($login_status_url, $args, $timeout);
        $login_response = simplexml_load_string($login_status_str);
        $basedomain = activedemand_get_basedomain();
        if ((isset($login_response->{'login-at'}) && !empty($login_response->{'login-at'}))) {
            $loggin_status = $login_response->{'login-at'};
            set_cookie('acf_access_login_status_' . $object_id, $loggin_status, 0, '/', $basedomain);
        }
    } elseif (isset($_COOKIE['acf_access_login_status_' . $object_id])) {
        $loggin_status = sanitize_text_field($_COOKIE['acf_session_' . $object_id]);
    }

    return $loggin_status;
}
function set_cookie($cookie_name, $cookie_value, $expiry, $path, $domain, $secure = true, $httponly = true, $samesite = 'Lax')
{

    if (PHP_VERSION_ID < 70300) {
        @setcookie($cookie_name, $cookie_value, $expiry, $path . '; samesite=' . $samesite, $domain, $secure, $httponly);
    } else {
        $cookie_options = array(
            'expires' =>  $expiry,
            'path' => $path,
            'domain' => $domain, // leading dot for compatibility or use subdomain
            'secure' => $secure, // or false
            'httponly' => $httponly, // or false
            'samesite' => $samesite // None || Lax || Strict
        );
        @setcookie($cookie_name, $cookie_value, $cookie_options);
    }
}
