<?php
$data = process_all_post_type();
$posttype_options = '';
foreach ($data['names'] as $value) {
    if (isset($_POST['bulk_update_posttype']) && $_POST['bulk_update_posttype'] == $value) {
        $posttype_selected = 'selected';
    } else {
        $posttype_selected = '';
    }
    $posttype_options .= "<option value='$value' $posttype_selected>$value</option>";
}
$fields_options = '';

if (isset($_POST['bulk_update_posttype'])) {
    $posttype = $_POST['bulk_update_posttype'];
    if (isset($data['fields'][$posttype])) {
        foreach ($data['fields'][$posttype] as $value) {
            if (isset($_POST['posttype_fields']) && $_POST['posttype_fields'] == $value) {
                $posttyepe_fields_selected = 'selected';
            } else {
                $posttyepe_fields_selected = '';
            }
            $fields_options .= "<option value='$value' $posttyepe_fields_selected>" . explode('/', $value)[0] . "</option>";
        }
    } else {
        $error[] = 'Something went wrong. Reload the page and try again.';
    }
} else {
    foreach (reset($data['fields']) as $value) {
        $fields_options .= "<option value='$value'>" . explode('/', $value)[0] . "</option>";
    }

}

$fields_options2 = '';
foreach (reset($data['fields']) as $value) {
    if (isset($_POST['current_posttype_fields']) && $_POST['current_posttype_fields'] == $value) {
        $posttyepe_fields_selected = 'selected';
    } else {
        $posttyepe_fields_selected = '';
    }
    $fields_options2 .= "<option value='$value' $posttyepe_fields_selected>" . explode('/', $value)[0] . "</option>";
}
$users_options = '';
$users = get_users();
foreach ($users as $row) {
    if (isset($_POST['posttype_author_field']) && $_POST['posttype_author_field'] == $row->ID) {
        $user_selected = 'selected';
    } else {
        $user_selected = '';
    }
    $users_options .= "<option value='$row->ID' $user_selected>" . $row->data->user_nicename . "</option>";
}
$select_with = $_POST['select_with'] ?? 'date_range';
$daterange_field = $_POST['daterange_field'] ?? '';
$current_posttype_fields = $_POST['current_posttype_fields'] ?? '';
$daterange_field_input = $_POST['daterange_field_input'] ?? '';
$current_posttype_field_input = $_POST['current_posttype_field_input'] ?? '';
$update_input_type = $_POST['update_input_type'] ?? '';
$posttype_author_field = $_POST['posttype_author_field'] ?? '';
$posttype_date_field = $_POST['posttype_date_field'] ?? '';
$posttype_shorttext_field = $_POST['posttype_shorttext_field'] ?? '';
$posttype_longtext_field = $_POST['posttype_longtext_field'] ?? '';
$change_default_content_input = $_POST['change_default_content_input'] ?? 'author';


?>

<div class="wrap mt-3">

    <?php if (isset($error) && count($error) > 0) {
        foreach ($error as $value) { ?>
            <p class="text-danger m-0">
                <?php echo $value; ?>
            </p>
        <?php }
    } ?>
    <?php if (isset($success) && count($success) > 0) {
        foreach ($success as $value) { ?>
            <p class="text-success my-2">
                <?php echo $value; ?>
            </p>
        <?php }
    } ?>
    <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST">
        <div class="form-group">
            <div class="w-25 d-inline-block">
                <label class="fw-bold" for="bulk_update_posttype">Post Type:</label>
            </div>
            <div class="d-inline-block">
                <select id="bulk_update_posttype" name="bulk_update_posttype" class="form-control" required>
                    <?php echo $posttype_options; ?>
                </select>
            </div>
        </div>
        <div class="form-group posttype_fields mt-2">
            <div class="w-25 d-inline-block">
                <label class="fw-bold" for="posttype_fields">Post Fields:</label>
            </div>
            <div class="d-inline-block">
                <select id="posttype_fields" name="posttype_fields" class="form-control" required>
                    <?php echo $fields_options; ?>
                </select>
            </div>
        </div>
        <div class="form-group select_with mt-4">
            <div class="w-25 d-inline-block vertical-align-top">
                <label class="fw-bold" for="select_with">Validate:</label>
            </div>
            <div class="d-inline-block w-70">
                <select id="select_with" name="select_with" class="mb-1">
                    <option <?php if ($select_with == 'date_range')
                        echo 'selected'; ?> value="date_range">Date range
                    </option>
                    <option <?php if ($select_with == 'field')
                        echo 'selected'; ?> value="field">Field</option>
                </select>
                <div class="d-inline-block daterange_field <?php if ($select_with !== 'date_range')
                    echo 'd-none'; ?>">
                    <select id="daterange_field" name="daterange_field" class="mb-1">
                        <option <?php if ($daterange_field == 'post_date')
                            echo 'selected'; ?> value="post_date">Creating
                            date</option>
                        <option <?php if ($daterange_field == 'post_modified')
                            echo 'selected'; ?> value="post_modified">
                            Modify date
                        </option>
                    </select>
                </div>
                <div class="d-inline-block current_posttype_fields <?php if ($select_with !== 'field')
                    echo 'd-none'; ?>">
                    <select id="current_posttype_fields" name="current_posttype_fields" class="mb-1">
                        <?php echo $fields_options2; ?>
                    </select>
                </div>
                <div class="mt-1 daterange_field_input <?php if ($select_with !== 'date_range')
                    echo 'd-none'; ?>">
                    <input id="daterange_field_input" class="date-range form-controll" type="text"
                        name="daterange_field_input" value="<?php echo $daterange_field_input; ?>">
                </div>
                <div class="mt-1 current_posttype_field_input <?php if ($select_with !== 'field')
                    echo 'd-none'; ?>">
                    <input class="form-control" id="current_posttype_field_input" type="text"
                        name="current_posttype_field_input" placeholder="Field equal..."
                        value="<?php echo $current_posttype_field_input; ?>">
                    <br>
                    <small>* You can use more than one, separated by commas. and for post author use their
                        usernames.</small>
                </div>
            </div>
        </div>
        <div class="form-group update_input_type mt-4">
            <div class="w-25 d-inline-block">
                <label class="fw-bold" for="update_input_type">Update type:</label>
            </div>
            <div class="d-inline-block">
                <select id="update_input_type" name="update_input_type" class="form-control">
                    <option <?php if ($update_input_type == 'append')
                        echo 'selected'; ?> value="append">Add to the next
                    </option>
                    <option <?php if ($update_input_type == 'replace')
                        echo 'selected'; ?> value="replace">Replace field
                    </option>
                </select>
            </div>
        </div>
        <div class="form-group posttype_author_field mt-2 <?php if ($change_default_content_input !== 'author')
            echo 'd-none' ?>">
                <div class="w-25 d-inline-block">
                    <label class="fw-bold" for="posttype_author_field">Choose author:</label>
                </div>
                <div class="d-inline-block">
                    <select id="posttype_author_field" name="posttype_author_field" class="form-control">
                    <?php echo $users_options; ?>
                </select>
            </div>
        </div>
        <div class="form-group posttype_date_field mt-2 <?php if ($change_default_content_input !== 'date')
            echo 'd-none' ?>">
                <div class="w-25 d-inline-block">
                    <label class="fw-bold" for="posttype_date_field">Date:</label>
                </div>
                <div class="d-inline-block">
                    <input type="datetime-local" id="posttype_date_field" name="posttype_date_field"
                        value="<?php echo $posttype_date_field; ?>">
            </div>
        </div>
        <div class="form-group posttype_shorttext_field mt-2 <?php if ($change_default_content_input !== 'shorttext')
            echo 'd-none' ?>">
                <div class="w-25 d-inline-block">
                    <label class="fw-bold" for="posttype_shorttext_field">Content:</label>
                </div>
                <div class="d-inline-block">
                    <input type="text" name="posttype_shorttext_field" id="posttype_shorttext_field" class="form-control"
                        placeholder="Type your short text" value="<?php echo $posttype_shorttext_field; ?>">
            </div>
        </div>
        <div class="form-group posttype_longtext_field <?php if ($change_default_content_input !== 'longtext')
            echo 'd-none' ?>">
            <?php do_action("posttype-update-content-textarea", $posttype_longtext_field); ?>
            <small class="m-0">* If it's an array data, then type key name then equal and if you have
                multiple then separate them via comma. (key = name, key2 = name2)</small>
        </div>

        <div class="form-group change_default_content_input mt-2">
            <div class="d-inline-block">
                <input type="checkbox" name="change_default_content_input_check" id="change_default_content_input_check"
                    class="me-1 vertical-align-middle" value="show">
                <label class="" for="change_default_content_input_check">Change input filed type</label>

                <select id="change_default_content_input" name="change_default_content_input" class="ms-1 opacity-none">
                    <option <?php if ($change_default_content_input == 'author')
                        echo 'selected' ?> value="author">
                            Author</option>
                        <option <?php if ($change_default_content_input == 'date')
                        echo 'selected' ?> value="date">Date
                        </option>
                        <option <?php if ($change_default_content_input == 'shorttext')
                        echo 'selected' ?> value="shorttext">
                            Short text</option>
                        <option <?php if ($change_default_content_input == 'longtext')
                        echo 'selected' ?> value="longtext">
                            Long text</option>
                    </select>
                </div>
            </div>
            <div class="form-group posttype_submit_changes mt-4">
                <div class="d-inline-block">
                    <button class="btn bg-primary border-0 py-2 px-3 cursor-pointer text-light"
                        type="submit">Update</button>
                </div>
            </div>
        </form>
    </div>