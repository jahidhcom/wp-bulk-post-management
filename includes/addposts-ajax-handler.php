<?php

if (isset($_POST['getposttype']) && isset($_FILES['getfile'])) {
    global $wpdb;
    $file = $_FILES['getfile'];
    $getposttype = $_POST['getposttype'];
    $data = get_postdatadata_from_files(new CURLFile($file['tmp_name'], $file['type'], $file['name']));


    $posttype_data = process_all_post_type();
    if (isset($posttype_data['fields'][$getposttype])) {
        $get_fields = $posttype_data['fields'][$getposttype];
    } else {
        $get_fields = [];
    }
    $get_fields[] = 'post_categories';

    $data = (array) json_decode($data);
    $new_data = [];
    $sheets = [];
    $i = 0;
    foreach ($data as $key => $rows) {
        $rows = (array) $rows;
        if (count($rows) > 0) {
            $sheets[] = $key;
            $new_data[$i] = (array) $rows[0];
        }
        $i++;
    }

    $generated_html = "";
    if (count($sheets) > 1) {
        $options = '';
        foreach ($sheets as $key => $name) {
            $options .= "<option value='$key'>$name</option>";
        }
        $generated_html .= '<div class="form-group my-3">
        <div class="w-25 d-inline-block">
            <label class="fw-bold" for="files_sheet_name">Sheets:</label>
        </div>
        <div class="d-inline-block">
            <select id="files_sheet_name" name="files_sheet_name" class="form-control">
            ' . $options . '
            </select>
        </div>
    </div>';
    }
    if (count($get_fields) > 0) {
        if (count($new_data) > 0) {
            $fdata = $new_data[0];
            foreach ($fdata as $i => $value) {
                $generated_html .= '<div class="form-group my-3 generated-field">
                <div class="w-45 d-inline-block vertical-align-top">
                    <select name="posttype_fields[]" class="form-control posttype_fields" required>
                        <option value="">--Select</option>';

                foreach ($get_fields as $key => $val) {
                    if ($value == $val) {
                        $generated_html .= "<option value='$val' selected>" . explode('/', $val)[0] . "</option>";
                    } else {
                        $generated_html .= "<option value='$val'>" . explode('/', $val)[0] . "</option>";
                    }
                }
                // $generated_html .= '<option value="post_categories">post_categories</option>';
                $generated_html .= '</select>
                    </div>
                    <div class="d-inline-block">
                        <select name="columns_names[]" class="form-control columns_names">';

                foreach ($fdata as $key => $val) {
                    if (gettype($key) == 'integer') {
                        $alt_key = "Column " . ($key + 1);
                    } else {
                        $alt_key = $key;
                    }
                    if ($i == $key) {
                        $generated_html .= "<option value='$key' selected>$alt_key</option>";
                    } else {
                        $generated_html .= "<option value='$key'>$alt_key</option>";
                    }
                }
                $generated_html .= '</select>
                        <br>
                        <button
                            class="btn bg-danger border-0 py-2 px-3 cursor-pointer text-light mt-1 delete-bulkpost-column">Delete</button>
                    </div>
                </div>';
            }
            $generated_html .= '
                <div class="extra-action-box my-3">
                    <button class="btn bg-primary border-0 py-2 px-3 me-2 cursor-pointer text-light add_new_custom_item">Add custom
                        field</button>
                    <button class="btn bg-primary border-0 py-2 px-3 me-2 cursor-pointer text-light add_new_column_item">Add columns
                        field</button>
                </div>';
        }
    }
    echo json_encode(['data' => $new_data, 'html' => $generated_html, 'test_data' => $posttype_data['fields']['attachment']]);
    die;
}






?>