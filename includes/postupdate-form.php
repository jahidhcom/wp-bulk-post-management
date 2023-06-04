<?php
if (isset($_POST['bulk_update_posttype']) && isset($_POST['posttype_fields']) && isset($_POST['select_with']) && isset($_POST['update_input_type']) && isset($_POST['change_default_content_input']) && $_POST['bulk_update_posttype'] !== '' && $_POST['posttype_fields'] !== '' && $_POST['select_with'] !== '' && $_POST['update_input_type'] !== '' && $_POST['change_default_content_input'] !== '') {
    global $wpdb;
    $error = [];
    $success = [];

    $posttype = $_POST['bulk_update_posttype'];
    $field = $_POST['posttype_fields'];
    $validation = $_POST['select_with'];
    $updatetype = $_POST['update_input_type'];
    $inputtype = $_POST['change_default_content_input'];
    $content = '';
    $gmt_time = '';
    $meta_field = false;

    $get_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type = '$posttype' AND post_status IN ('draft', 'publish', 'inherit') LIMIT 1");
    if (count($get_contents) > 0) {
        $get_contents = $get_contents[0];

        if (preg_match('/\/metafields/', $field)) {
            $field = explode('/metafields', $field)[0];
            $meta_field = true;

            $post_id = $get_contents->ID;
            $get_meta_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = '$post_id'");
            foreach ($get_meta_contents as $row) {
                $meta_key = $row->meta_key;
                if ((@unserialize($row->meta_value) !== false)) {
                    $get_contents->$meta_key = unserialize($row->meta_value);
                } else {
                    $get_contents->$meta_key = $row->meta_value;
                }
            }
        }

        if ($inputtype == 'author') {
            if (!isset($_POST['posttype_author_field'])) {
                $error[] = 'Author is required';
            } else if ($_POST['posttype_author_field'] == '') {
                $error[] = 'Author field must not empty';
            } else {
                $author = $_POST['posttype_author_field'];
                $author_q = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE ID = '$author'");
                if (count($author_q) > 0) {
                    $content = $author;
                } else {
                    $error[] = 'hey!!! don\'t change the value from inspect. I got trouble to fix it.';
                }
            }
        } else if ($inputtype == 'date') {
            if (!isset($_POST['posttype_date_field'])) {
                $error[] = 'Date field required, It should not happen. contact with your developer.';
            } else {
                if ($_POST['posttype_date_field'] == '') {
                    $content = date('Y-m-d H:i:s', time());
                    $gmt_time = date('Y-m-d H:i:s', strtotime(gmdate('Y-m-d H:i:s')));
                } else {
                    $content = date('Y-m-d H:i:s', strtotime($_POST['posttype_date_field']));
                    $gmt_time = gmdate('Y-m-d H:i:s', strtotime(get_gmt_from_date($_POST['posttype_date_field'])));
                }
            }
        } else if ($inputtype == 'shorttext') {
            if (!isset($_POST['posttype_shorttext_field'])) {
                $error[] = 'There must be a short text field. you missed it.';
            } else {
                $content = $_POST['posttype_shorttext_field'];
            }
        } else if ($inputtype == 'longtext') {
            if (!isset($_POST['posttype_longtext_field'])) {
                $error[] = 'I see... your long text field not there. But It\'s required for submit this form.';
            } else {
                $content = $_POST['posttype_longtext_field'];
            }
        } else {
            $error[] = 'Someone changed your form. contact with your developer.';
        }

        if ($validation == 'date_range') {
            if (!isset($_POST['daterange_field']) || $_POST['daterange_field'] == '') {
                $error[] = 'Ahh, It\'s a shame.  you forgot to choose \'field\' name that you want to update with.';
            } else if (!isset($_POST['daterange_field_input'])) {
                $error[] = 'Ok... I\'m thinking. why there don\'t have a field for choose date time?';
            } else {
            }
        } else if ($validation == 'field') {
            if (!isset($_POST['current_posttype_fields']) || $_POST['current_posttype_fields'] == '') {
                $error[] = 'You msut need to choose what field do you want to validate with.';
            } else if (!isset($_POST['current_posttype_field_input'])) {
                $error[] = 'If you don\'t write with what do you want to validate then, How could I update? I\'m not magician!';
            } else {
            }
        }

        if ($field == 'post_date') {
            $data = "`post_date` = '$content', `post_date_gmt` = '$gmt_time'";
        } else if ($field == 'post_modified') {
            $data = "`post_modified` = '$content', `post_modified_gmt` = '$gmt_time'";
        } else {
            if ($updatetype == 'append') {
                if (gettype($get_contents->$field) == 'array') {
                    $data = "dynamic";
                } else {
                    $data = "`$field` = CONCAT($field, '$content')";
                }
            } else if ($updatetype == 'replace') {
                if (gettype($get_contents->$field) == 'array') {
                    $data = "dynamic";
                } else {
                    $data = "`$field` = '$content'";
                }
            } else {
                $error[] = 'Do you really think that you\'re smart? I don\'t think so.';
            }
        }

        if ($validation == 'date_range') {
            if (isset($_POST['daterange_field']) && $_POST['daterange_field'] !== '' && isset($_POST['daterange_field_input']) && $_POST['daterange_field_input'] !== '') {
                $validation_field = $_POST['daterange_field'];
                $validation_date = $_POST['daterange_field_input'];
                $datetime = explode('-', $validation_date);
                $start_date = date("Y-m-d", strtotime(trim($datetime[0]))) . " 00:00:00";
                $end_date = date("Y-m-d", strtotime(trim($datetime[1]))) . " 23:59:59";
                $validate_text = "`$validation_field` BETWEEN '$start_date' AND '$end_date'";
            } else {
                $error[] = "It seems like... You missed to choose the date range";
            }
        } else if ($validation == 'field') {
            if (isset($_POST['current_posttype_fields']) && $_POST['current_posttype_fields'] !== '' && isset($_POST['current_posttype_field_input']) && $_POST['current_posttype_field_input'] !== '') {
                $validation_field = $_POST['current_posttype_fields'];
                $validation_text = $_POST['current_posttype_field_input'];
                $vtext = explode(',', $validation_text);
                $vtext = "'" . implode("', '", array_map('trim', $vtext)) . "'";
                if ($validation_field == 'post_author') {
                    $check_users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE user_login IN ($vtext)");
                    if (count($check_users) > 0) {
                        $user_ids = [];
                        foreach ($check_users as $user) {
                            $user_ids[] = $user->ID;
                        }
                        $vtext = "'" . implode("', '", array_map('trim', $user_ids)) . "'";
                    } else {
                        $error[] = "Shame to you. You don't even know your users and their usernames.";
                    }
                }
                $validate_text = "`$validation_field` IN ($vtext)";

            } else {
                $error[] = "It seems like... You missed to write validation text";
            }
        } else {
            $error[] = 'Okay!! So it\'s a technical problem. Now you need a developer.';
        }

        // echo $validate_text . '<br>';s

        if ($meta_field) {
            $table_name = "{$wpdb->prefix}postmeta";
            $post_ids = [];
            $get_post_ids = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE `post_type` = '$posttype' AND $validate_text");
            if (count($get_post_ids) > 0) {
                foreach ($get_post_ids as $row) {
                    $post_ids[] = $row->ID;
                }
                $post_ids = "'" . implode("', '", $post_ids) . "'";
                $validate_text = "`post_id` IN ($post_ids)  AND `meta_key` = '$field'";
            } else {
                $error[] = 'Unfortunetly, No post found.';
            }
            // $validate_text = "`meta_key` = '$field' AND " . $validate_text;
        } else {
            $validate_text = "`post_type` = '$posttype' AND post_status IN ('draft', 'publish', 'inherit') AND" . $validate_text;
            $table_name = "{$wpdb->prefix}posts";
        }

        if (count($error) > 0) {
            $_SESSION['error'] = json_encode($error);
        } else {
            if ($data == 'dynamic') {
                if ($updatetype == 'append') {
                    $update_dynamecially = $wpdb->get_results("SELECT * FROM $table_name WHERE $validate_text");
                    $count_update = 0;
                    foreach ($update_dynamecially as $row) {
                        if ((@unserialize($row->meta_value) !== false)) {
                            $meta_value = unserialize($row->meta_value);
                        } else {
                            $meta_value = [$row->meta_value];
                        }
                        if ($content !== '') {
                            if (preg_match('/\=/', $content)) {
                                $new_arr = explode(',', $content);
                                foreach ($new_arr as $value) {
                                    $new_arr2 = explode('=', $value, 2);
                                    if (isset($new_arr2[1]) || $new_arr2[1] !== '') {
                                        $keyname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $new_arr2[0]));
                                        $value = $new_arr2[1];
                                    } else {
                                        $value = $new_arr2[0];
                                        if (strlen($new_arr2[0]) > 10) {
                                            $new_arr2[0] = substr($new_arr2[0], 0, 10);
                                        }
                                        $keyname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $new_arr2[0]));
                                    }

                                    $old_key = $content;
                                    $i = 1;
                                    while (isset($meta_value[$keyname])) {
                                        $keyname = $old_key . '_' . $i;
                                        $i++;
                                    }
                                    $meta_value[$keyname] = $value;
                                }
                            } else {
                                $keyname = $content;
                                if (strlen($keyname) > 10) {
                                    $keyname = substr($keyname, 0, 10);
                                }
                                $keyname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $keyname));
                                $old_key = $keyname;
                                $i = 1;
                                while (isset($meta_value[$keyname])) {
                                    $keyname = $old_key . '_' . $i;
                                    $i++;
                                }
                                $meta_value[$keyname] = $content;
                            }
                        }
                        $meta_value = serialize($meta_value);
                        $meta_id = $row->meta_id;
                        $query = "UPDATE $table_name SET meta_value = '$meta_value' WHERE meta_id = '$meta_id'";
                        $wpdb->query($query);
                        $count_update++;
                    }
                    $success[] = $count_update . " rows affected.";
                } else {
                    $new_meta_field = [];
                    $new_arr = explode(',', $content);
                    foreach ($new_arr as $value) {
                        $new_arr2 = explode('=', $value, 2);
                        if (isset($new_arr2[1]) && $new_arr2[1] !== '') {
                            $keyname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $new_arr2[0]));
                            $value = $new_arr2[1];
                        } else {
                            $value = $new_arr2[0];
                            if (strlen($new_arr2[0]) > 10) {
                                $new_arr2[0] = substr($new_arr2[0], 0, 10);
                            }
                            $keyname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $new_arr2[0]));
                        }

                        $old_key = $content;
                        $i = 1;
                        while (isset($new_meta_field[$keyname])) {
                            $keyname = $old_key . '_' . $i;
                            $i++;
                        }
                        $new_meta_field[$keyname] = $value;
                    }
                    $meta_value = serialize($new_meta_field);
                    $query = "UPDATE $table_name SET meta_value = '$meta_value' WHERE $validate_text";
                    $wpdb->query($query);
                    $count_update = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $validate_text");
                    $success[] = $count_update . " rows affected.";
                }
            } else {
                if ($meta_field) {
                    $query = "UPDATE $table_name SET $data WHERE $validate_text";
                } else {
                    $current_time = current_time('timestamp');
                    $current_time_gmt = current_time('timestamp', true);
                    $data .= ", post_modified = '$current_time', post_modified_gmt = '$current_time_gmt'";
                    $query = "UPDATE $table_name SET $data WHERE $validate_text";
                }
                $wpdb->query($query);
                $count_update = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $validate_text");
                $success[] = $count_update . " rows affected.";
            }
        }
        if (count($success) > 0) {
            $_SESSION['success'] = json_encode($success);
        }

    }
}
?>