<?php
/*
Plugin Name: Bulk Post Management
Description: "Bulk Post Management" plugin is a powerful and advanced tool for managing your posts in bulk. It's highly optimized for manage large amount of content. You can even manage your custom post_type, extra fields. Get a comprehensive list of all available post types for effortless management. 
Version: 2.1.04
Author: Jahid H.
Author URI: https://jahidh.com/

Text Domain: bulk-post-management
License: GPL2

 Copyright 2009,2010,2011,2012  Jahid H.  (email : support@jahidh.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once "includes/handler.php";

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nc_settings_link');
function nc_settings_link($links)
{
    $url = esc_url(
        add_query_arg(
            'page',
            'bulk-post-management',
            get_admin_url() . 'admin.php'
        )
    );
    $settings_link = "<a href='$url'>" . __('Settings') . '</a>';
    array_push(
        $links,
        $settings_link
    );
    return $links;
}