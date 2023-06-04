<?php
function enqueue_bulk_post_update_styles()
{
    wp_enqueue_style('custom-styles', plugin_dir_url(__FILE__) . '../assets/css/styles.css');
    wp_enqueue_style('daterangepicker-styles', plugin_dir_url(__FILE__) . '../assets/css/daterangepicker.css');
    wp_enqueue_script('moment-script', plugin_dir_url(__FILE__) . '../assets/js/moment.min.js', array('jquery'), '1.0.0', true);
    wp_enqueue_script('daterangepicker-script', plugin_dir_url(__FILE__) . '../assets/js/daterangepicker.min.js', array('jquery'), '1.0.0', true);
}
add_action('admin_enqueue_scripts', 'enqueue_bulk_post_update_styles');

function bulk_post_update()
{
    add_menu_page('Bulk Post Management', 'Bulk Posts', 'manage_options', 'bulk-post-management', 'add_bulk_posts_callback', 'dashicons-admin-generic', 10);
    add_submenu_page('bulk-post-management', 'Insert Bulk posts', 'Add Posts', 'manage_options', 'bulk-post-management', 'add_bulk_posts_callback');
    add_submenu_page('bulk-post-management', 'Update Bulk Posts', 'Update Posts', 'manage_options', 'update-bulk-posts', 'bulk_post_update_callback');
}


add_action('admin_menu', 'bulk_post_update');


function bulk_post_update_callback()
{
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Manage Posts</h1>';
    echo "<p class='mt-0'>Edit any post type with bulk way. You can even update custom post fields.</p>";
    do_action('bulk_post_update_content');
    echo '</div>';
}

function add_bulk_posts_callback()
{
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Add Posts</h1>';
    echo "<p class='mt-0'>Insert your CSV, XLSX, XLS or XML file and chose the column for options and then just via one click you can import all of your content to wordpress.</p>";
    do_action('bulk_posts_add');
    echo '</div>';
}

function process_all_post_type()
{
    global $wpdb;
    $data = [];
    $posttypes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts GROUP BY post_type");
    foreach ($posttypes as $row) {
        if (!isset($data['posttype_ids'])) {
            $data['posttype_ids'] = [];
            $data['names'] = [];
            $data['fields'] = [];
        }
        $ID = $row->ID;
        $data['posttype_ids'][] = $ID;
        $data['names'][$ID] = $row->post_type;
        unset($row->ID);
        $data['fields'][$row->post_type] = array_keys((array) $row);
    }

    $ids = implode(',', $data['posttype_ids']);
    $metafields = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id IN ($ids) GROUP BY meta_id");
    foreach ($metafields as $row) {
        $posttype = $data['names'][$row->post_id];
        $data['fields'][$posttype][] = $row->meta_key . '/metafields';
    }
    return $data;
}

function get_postdatadata_from_files($file)
{
    $curl = curl_init();
    $url = plugin_dir_url(plugin_basename(__FILE__)) . "../classes/requests.php";

    // if (function_exists('curl_file_create')) {
    //     $file = curl_file_create($file);
    // } else {
    //     $file = '@' . realpath($file);
    // }

    $postData = ['file' => $file];

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}


function bulk_postupdate_form_handler()
{
    include('postupdate-form.php');
}
add_action('bulk_post_update_content', 'bulk_postupdate_form_handler');


function visual_posttype_handler_form()
{
    if (isset($_SESSION['error'])) {
        $error = json_decode($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        $success = json_decode($_SESSION['success']);
    }
    include('form-visualisation.php');
}
add_action('bulk_post_update_content', 'visual_posttype_handler_form');

function posttype_textarea_content_callback($content)
{
    wp_editor(
        $content,
        'posttype_longtext_field',
        array(
            'textarea_name' => 'posttype_longtext_field',
            'media_buttons' => false,
            'editor_settings' => array(
                'tinymce' => array(
                    'height' => 500,
                ),
            ),
        )
    );
}
add_action('posttype-update-content-textarea', 'posttype_textarea_content_callback');



// PHP function to handle the AJAX request
function bulk_posts_form_handler()
{
    if (!wp_verify_nonce($_REQUEST['nonce'], "bulk_posts__nonce__form_handler")) {
        echo json_encode(["error" => "Defeneatly, you're the disappointment."]);
        exit();
    }
    include('addposts-ajax-handler.php');
}
add_action('wp_ajax_bulk_posts_form_handler', 'bulk_posts_form_handler');
add_action('wp_ajax_nopriv_bulk_posts_form_handler', 'bulk_posts_form_handler');


function bulk_posts_add_callback()
{
    include('addposts-handler.php');
    include('posts-add.php');
}
add_action('bulk_posts_add', 'bulk_posts_add_callback');


function manage_posttype_scripts()
{
    include('scripts.php');
}
add_action('bulk_post_update_content', 'manage_posttype_scripts');
function add_bulk_posts_scripts()
{
    include('posts-add-script.php');
}
add_action('bulk_posts_add', 'add_bulk_posts_scripts');