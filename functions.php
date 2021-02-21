<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if (!function_exists('chld_thm_cfg_locale_css')):
    function chld_thm_cfg_locale_css($uri)
    {
        if (empty($uri) && is_rtl() && file_exists(get_template_directory() . '/rtl.css'))
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter('locale_stylesheet_uri', 'chld_thm_cfg_locale_css');

if (!function_exists('chld_thm_cfg_parent_css')):
    function chld_thm_cfg_parent_css()
    {
        wp_enqueue_style('chld_thm_cfg_parent', trailingslashit(get_template_directory_uri()) . 'style.css', array());
    }
endif;
add_action('wp_enqueue_scripts', 'chld_thm_cfg_parent_css', 10);

// END ENQUEUE PARENT ACTION

/*
 * Register brewery cpt (Fields for this cpt in ACF) and get and update data from breweries api.
 *
 */

add_action('init', 'register_brewery_cpt');

function register_brewery_cpt()
{
    register_post_type('brewery', [
        'label' => 'Breweries',
        'public' => true,
        'capability_type' => 'post'
    ]);
}

// Cron
if( !wp_next_scheduled('update_brewery_list')) {
    wp_schedule_event(time(), 'weekly', 'get_breweries_from_api');
}

add_action('wp_ajax_nopriv_get_breweries_from_api', 'get_breweries_from_api');
add_action('wp_ajax_get_breweries_from_api', 'get_breweries_from_api');
function get_breweries_from_api()
{


    $current_page = (!empty($_POST['current_page'])) ? $_POST['current_page'] : 1;
    $breweries = [];

    $results = wp_remote_retrieve_body(wp_remote_get('https://api.openbrewerydb.org/breweries?page=' . $current_page . '&per_page=50'));

    $file = get_stylesheet_directory() . '/report.txt';
    file_put_contents($file, $current_page . "\n", FILE_APPEND);

    $results = json_decode($results);

    if (!is_array($results) || empty($results)) {
        return false;
    }

    $breweries = $results;
    foreach ($breweries as $brewery) {
//        var_dump($brewery->name);

        $brewery_slug = sanitize_title($brewery->name . $brewery->id);

        $existing_brewery = get_page_by_path($brewery_slug, 'OBJECT', 'brewery');

        if ($existing_brewery === null) {
            $inserted_brewery = wp_insert_post([
                'post_name' => $brewery_slug,
                'post_title' => $brewery_slug,
                'post_type' => 'brewery',
                'post_status' => 'publish'
            ]);

            if (is_wp_error($inserted_brewery)) {
                continue;
            }

            $fillable = [
                'field_60315f443a77d' => 'name',
                'field_60315f4f3a77e' => 'brewery_type',
                'field_60315f553a77f' => 'street',
                'field_60315f633a780' => 'city',
                'field_60315f673a781' => 'state',
                'field_60315f8656060' => 'postal_code',
                'field_60315f8b56061' => 'country',
                'field_60315f9556062' => 'longitude',
                'field_60315fa056063' => 'latitude',
                'field_60315fa556064' => 'phone',
                'field_60315faa56065' => 'website',
                'field_60315fb656066' => 'updated_at',
            ];

            foreach ($fillable as $key => $name) {
                update_field($key, $brewery->$name, $inserted_brewery);
            }
        } else {
            // If brewery exist update fields (if its needed)

            $existing_brewery_id = $existing_brewery->id;
            $existing_brewery_timestamp = get_field('updated_at', $existing_brewery_id);

            if($brewery->updated_at >= $existing_brewery_timestamp) {
                // update post meta
                update_field($key, $brewery->$name, $existing_brewery_id);
            }
        }


    }

    $current_page = $current_page + 1;
    wp_remote_post(admin_url('admin-ajax.php?action=get_breweries_from_api'), [
        'blocking' => false,
        'sslverify' => false,
        'body' => [
            'current_page' => $current_page
        ]
    ]);
}
