<?php

global $wpdb;

if (isset($_FILES['add_bulk_post_fileinput']) && isset($_POST['posttype_name']) && isset($_POST['posttype_fields']) && isset($_POST['columns_names']) && isset($_POST['remove_rows'])) {
    $file = $_FILES['add_bulk_post_fileinput'];
    $posttype = $_POST['posttype_name'];
    $fields = $_POST['posttype_fields'];
    $columns = $_POST['columns_names'];
    $skips = $_POST['remove_rows'];
    $data = [];
    $post_categories = [];
    $post_category_column = '';
    $post_category = false;

    $sheet_number = $_POST['files_sheet_name'] ?? 0;

    if ($skips !== '') {
        $skips = explode(',', $skips);
        foreach ($skips as $key => $value) {
            $skips[$key] = ((float) $value) - 1;
        }
    }


    $get_users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users");
    $users = [];
    $current_user = get_current_user_id();
    foreach ($get_users as $row) {
        $users[$row->user_login] = $row->ID;
    }
    $get_posts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type = '$posttype'");
    $post_names = [];
    foreach ($get_posts as $row) {
        $post_names[$row->post_name] = $row->ID;
    }


    $meta_fields = [];
    $meta_fields_serialize = [];
    $meta_columns = [];
    if (count($fields) === count($columns)) {
        foreach ($fields as $i => $value) {
            if (preg_match('/\/metafields/', $value)) {
                $meta_key = explode('/metafields', $value)[0];
                $meta_fields[] = $meta_key;
                $meta_columns[] = $columns[$i];
                unset($columns[$i]);
                unset($fields[$i]);

                $get_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type = '$posttype' ORDER BY ID ASC LIMIT 1");
                if (count($get_contents) == 1) {
                    $get_contents = $get_contents[0];
                    $post_id = $get_contents->ID;
                    $get_meta_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = '$post_id'");
                    foreach ($get_meta_contents as $row) {
                        if ($row->meta_key == $meta_key) {
                            if ((@unserialize($row->meta_value) !== false)) {
                                $meta_fields_serialize[] = true;
                            } else {
                                $meta_fields_serialize[] = false;
                            }
                        }
                    }
                }

            } else if ($value == 'post_categories') {
                $post_category = true;
                $post_category_column = $columns[$i];
                unset($columns[$i]);
                unset($fields[$i]);
            } else if ($value == 'post_type') {
                unset($columns[$i]);
                unset($fields[$i]);
            }

        }
    }

    $fields = array_unique($fields);
    $meta_fields = array_unique($meta_fields);

    if (count($columns) === count($fields) && count($meta_columns) === count($meta_fields)) {


        $file_data = get_postdatadata_from_files(new CURLFile($file['tmp_name'], $file['type'], $file['name']));
        $file_data = (array) json_decode($file_data);

        $i = 0;
        foreach ($file_data as $rows) {
            if ($i == $sheet_number) {
                $data = $rows;
            }
            $i++;
        }

        $fields_values = [];
        $meta_fields_values = [];
        foreach ($data as $key => $row) {
            if (!in_array($key, $skips)) {
                $frow = [$posttype];
                $mfrow = [];
                foreach ($row as $i => $v) {
                    $v = trim($v);
                    if (in_array($i, $columns)) {
                        $frow[] = $v;
                    }
                    if (in_array($i, $meta_columns)) {
                        $mfrow[] = $v;
                    }
                    if ($i == $post_category_column) {
                        if ($v == '') {
                            $post_categories[] = ['Uncategorized'];
                        } else {
                            $cat = explode(',', $v);
                            $post_categories[] = array_unique(array_map('trim', $cat));
                        }
                    }
                }
                $fields_values[] = $frow;
                $meta_fields_values[] = $mfrow;
            }
        }

        $post_names = [];

        $fields = ['post_type', ...$fields];
        foreach ($fields as $key => $value) {
            if ($value == 'post_author') {
                foreach ($fields_values as $rkey => $row) {
                    if (isset($users[$row[$key]])) {
                        $fields_values[$rkey][$key] = $users[$row[$key]];
                    } else {
                        $fields_values[$rkey][$key] = $current_user;
                    }
                }
            } else if ($value == 'post_name') {
                foreach ($fields_values as $rkey => $row) {
                    $temp_post_name = $row[$key];
                    if ($temp_post_name == '') {
                        if (in_array('post_title', $fields)) {
                            $post_field_key = array_search('post_title', $fields);
                            $temp_post_name = $row[$post_field_key];
                        } else if (in_array('post_content', $fields)) {
                            $post_field_key = array_search('post_content', $fields);
                            $temp_post_name = substr(strip_tags($row[$post_field_key]), 0, 30);
                        }
                        if ($temp_post_name == '') {
                            $temp_post_name = 'undefined-title';
                        }
                    }
                    $temp_post_name = sanitize_title($temp_post_name);
                    $ref_temp_post_name = $temp_post_name;
                    $icx = 0;
                    while (isset($post_names[$temp_post_name])) {
                        $temp_post_name = $ref_temp_post_name . '-' . $icx++;
                    }

                    $fields_values[$rkey][$key] = $temp_post_name;
                    $post_names[$temp_post_name] = time();
                }
            } else if ($value == 'post_date' || $value == 'post_date_gmt' || $value == 'post_modified' || $value == 'post_modified_gmt') {
                foreach ($fields_values as $rkey => $row) {
                    $dval = $row[$key];
                    if (strtotime($dval) !== false) {
                        $fields_values[$rkey][$key] = date('Y-m-d H:i:s', strtotime($v));
                    } else {
                        $fields_values[$rkey][$key] = date('Y-m-d H:i:s');
                    }
                }
            } else if ($value == 'post_status') {
                foreach ($fields_values as $rkey => $row) {
                    $dval = $row[$key];
                    if ($dval == '') {
                        $fields_values[$rkey][$key] = 'draft';
                    } else if ($dval == 'published') {
                        $fields_values[$rkey][$key] = 'publish';
                    }
                }
            }
        }

        if (!in_array('post_author', $fields)) {
            $fields[] = 'post_author';
            foreach ($fields_values as $rkey => $row) {
                $fields_values[$rkey][] = $current_user;
            }
        }
        if (!in_array('post_name', $fields)) {
            $fields[] = 'post_name';
            foreach ($fields_values as $rkey => $row) {

                $temp_post_name = 'undefined-title';
                $ref_temp_post_name = $temp_post_name;

                $icx = 0;
                while (isset($post_names[$temp_post_name])) {
                    $temp_post_name = $ref_temp_post_name . '-' . $icx++;
                }

                $fields_values[$rkey][] = $temp_post_name;
                $post_names[$temp_post_name] = time();
            }


        }
        if (!in_array('post_status', $fields)) {
            $fields[] = 'post_status';
            foreach ($fields_values as $rkey => $row) {
                $fields_values[$rkey][] = 'publish';
            }
        }

        if (in_array('post_date', $fields) && !in_array('post_date_gmt', $fields)) {
            $fields[] = 'post_date_gmt';
            $post_field_key = array_search('post_date', $fields);
            foreach ($fields_values as $key => $row) {
                $fields_values[$key][] = $fields_values[$key][$post_field_key];
            }
        } else if (in_array('post_date_gmt', $fields) && !in_array('post_date', $fields)) {
            $fields[] = 'post_date';
            $post_field_key = array_search('post_date_gmt', $fields);
            foreach ($fields_values as $key => $row) {
                $fields_values[$key][] = $fields_values[$key][$post_field_key];
            }
        } else {
            $fields[] = 'post_date';
            $fields[] = 'post_date_gmt';
            foreach ($fields_values as $key => $row) {
                $fields_values[$key][] = current_time('Y-m-d H:i:s');
                $fields_values[$key][] = current_time('Y-m-d H:i:s', true);
            }
        }

        if (in_array('post_modified', $fields) && !in_array('post_modified_gmt', $fields)) {
            $fields[] = 'post_modified_gmt';
            $post_field_key = array_search('post_modified', $fields);
            foreach ($fields_values as $key => $row) {
                $fields_values[$key][] = $fields_values[$key][$post_field_key];
            }
        } else if (in_array('post_modified_gmt', $fields) && !in_array('post_modified', $fields)) {
            $fields[] = 'post_modified';
            $post_field_key = array_search('post_modified_gmt', $fields);
            foreach ($fields_values as $key => $row) {
                $fields_values[$key][] = $fields_values[$key][$post_field_key];
            }
        } else {
            $fields[] = 'post_modified';
            $fields[] = 'post_modified_gmt';
            foreach ($fields_values as $key => $row) {
                $fields_values[$key][] = current_time('Y-m-d H:i:s');
                $fields_values[$key][] = current_time('Y-m-d H:i:s', true);
            }
        }


        $limit_count = count($fields_values);
        $post_type_con = [];
        $post_status_con = [];
        $post_author_con = [];
        $post_date_con = [];
        $post_date_gmt_con = [];
        $post_modified_con = [];
        $post_modified_gmt_con = [];
        $post_type_key = array_search('post_type', $fields);
        $post_status_key = array_search('post_status', $fields);
        $post_author_key = array_search('post_author', $fields);
        $post_date_key = array_search('post_date', $fields);
        $post_date_gmt_key = array_search('post_date_gmt', $fields);
        $post_modified_key = array_search('post_modified', $fields);
        $post_modified_gmt_key = array_search('post_modified_gmt', $fields);
        foreach ($fields_values as $row) {
            $post_type_con[] = $row[$post_type_key];
            $post_status_con[] = $row[$post_status_key];
            $post_author_con[] = $row[$post_author_key];
            $post_date_con[] = $row[$post_date_key];
            $post_date_gmt_con[] = $row[$post_date_gmt_key];
            $post_modified_con[] = $row[$post_modified_key];
            $post_modified_gmt_con[] = $row[$post_modified_gmt_key];
        }

        $validation_text = "post_type IN ('" . implode("', '", $post_type_con) . "') AND post_status IN ('" . implode("', '", $post_status_con) . "') AND post_author IN ('" . implode("', '", $post_author_con) . "') AND post_date IN ('" . implode("', '", $post_date_con) . "') AND post_date_gmt IN ('" . implode("', '", $post_date_gmt_con) . "') AND post_modified IN ('" . implode("', '", $post_modified_con) . "') AND post_modified_gmt IN ('" . implode("', '", $post_modified_gmt_con) . "')";



        $str_field_values = [];
        foreach ($fields_values as $row) {
            $str_field_values[] = "('" . implode("', '", $row) . "')";
        }


        $fields = implode(', ', $fields);
        $fields_values = implode(', ', $str_field_values);

        $query = $wpdb->query("INSERT INTO {$wpdb->prefix}posts ($fields) VALUES $fields_values");

        if (!empty(array_filter($meta_fields_values)) || $post_category) {
            $get_query = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE $validation_text ORDER BY ID ASC LIMIT $limit_count");

            $insert_ids = [];
            foreach ($get_query as $row) {
                $insert_ids[] = $row->ID;
            }

            if ($post_category) {


                $cats_alt = [];
                $cats_ids = [];
                $cats_alt_ids = [];
                $cats_inserted_ids = [];

                foreach ($post_categories as $cat) {
                    if (gettype($cat) == 'string') {
                        $cats_alt[] = $cat;
                    } else if (gettype($cat) == 'array') {
                        foreach ($cat as $v) {
                            $cats_alt[] = $v;
                        }
                    }
                }
                $cats_alt = array_values(array_unique($cats_alt));
                $term_table = "{$wpdb->prefix}terms";
                $cats_insert_ids = [];
                foreach ($cats_alt as $cat) {
                    $category_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $term_table WHERE name = %s", $cat));
                    if (!$category_row) {
                        $wpdb->insert($term_table, array('name' => $cat, 'slug' => sanitize_title($cat)));
                        $new_cat_insert_id = $wpdb->insert_id;
                        $cats_alt_ids[] = 'later_add';
                        $cats_inserted_ids[] = "('$new_cat_insert_id', 'category')";
                        $cats_insert_ids[] = $new_cat_insert_id;
                    } else {
                        $cats_alt_ids[] = $category_row->term_id;
                    }
                }
                $cats_inserted_ids_alt = implode(', ', $cats_inserted_ids);
                $term_taxonomy_query = $wpdb->query("INSERT INTO {$wpdb->prefix}term_taxonomy (`term_id`, `taxonomy`) VALUES $cats_inserted_ids_alt");

                $cats_id_validation = "('" . implode("', '", $cats_insert_ids) . "')";
                $get_term_taxonomy_data = $wpdb->get_results("SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = 'category' AND term_id IN $cats_id_validation ORDER BY term_taxonomy_id ASC");

                $ttd_id = [];
                foreach ($get_term_taxonomy_data as $row) {
                    $ttd_id[] = $row->term_taxonomy_id;
                }

                $caic = 0;
                foreach ($cats_alt_ids as $key => $id) {
                    if ($id == 'later_add') {
                        $cats_alt_ids[$key] = $ttd_id[$caic];
                        $caic++;
                    }
                }

                foreach ($post_categories as $cat) {
                    $post_field_key = array_search($cat, $cats_alt);
                    if (gettype($cat) == 'string' || gettype($cat) == 'integer') {
                        $cats_ids[] = $cats_alt_ids[$post_field_key];
                    } else if (gettype($cat) == 'array') {
                        $temp_cats_id = [];
                        foreach ($cat as $v) {
                            $post_field_key = array_search($v, $cats_alt);
                            $temp_cats_id[] = $cats_alt_ids[$post_field_key];
                        }
                        $cats_ids[] = $temp_cats_id;
                    }
                }

                $new_cat_fields = [];
                foreach ($insert_ids as $key => $id) {
                    $cat = $cats_ids[$key];
                    if (gettype($cat) == 'string' || gettype($cat) == 'integer') {
                        $new_cat_fields[] = "('$id', '$cat')";
                    } else if (gettype($cat) == 'array') {
                        foreach ($cat as $v) {
                            $new_cat_fields[] = "('$id', '$v')";
                        }
                    }
                }
                $cat_fields_value = implode(', ', $new_cat_fields);
                $cat_query = $wpdb->query("INSERT INTO {$wpdb->prefix}term_relationships (`object_id`, `term_taxonomy_id`) VALUES $cat_fields_value");
            }

            if (!empty(array_filter($meta_fields_values))) {

                $new_meta_values = [];
                foreach ($meta_fields_values as $key => $row) {
                    foreach ($row as $i => $value) {
                        $initial_values = [$insert_ids[$key]];
                        $initial_values[] = $meta_fields[$i];
                        if ($meta_fields_serialize[$i]) {
                            $initial_values[] = serialize($value);
                        } else {
                            $initial_values[] = $value;
                        }
                        $new_meta_values[] = "('" . implode("', '", $initial_values) . "')";
                    }

                }

                $meta_fields_values = implode(", ", $new_meta_values);
                $meta_fields = "post_id, meta_key, meta_value";
                $meta_query = $wpdb->query("INSERT INTO {$wpdb->prefix}postmeta ($meta_fields) VALUES $meta_fields_values");
            }
        }

        echo "<h2 class='mb-0'>Total $query row inserted</h2>
            <a href='#' onclick='location.reload(); return false;'>Insert again</a>";
        die;

    } else {
        echo "After all that restriction, somehow you were able to select same post fields multiple time.";
    }
}


?>