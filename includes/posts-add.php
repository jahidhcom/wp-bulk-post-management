<?php
$data = process_all_post_type();
$posttype_options = '';
foreach ($data['names'] as $value) {
    $posttype_options .= "<option value='" . $value . "'>" . $value . "</option>";
}
$fields_options = '';
foreach (reset($data['fields']) as $value) {
    $fields_options .= "<option value='" . $value . "'>" . explode('/', $value)[0] . "</option>";
}
$users_options = '';
$users = get_users();
foreach ($users as $row) {
    $users_options .= "<option value='" . $row->ID . "'>" . $row->data->user_nicename . "</option>";
}
?>
<div class="wrap mt-4">
    <form action="" method="post" enctype="multipart/form-data">
        <div id="error_messages"></div>
        <div class="form-group">
            <div class="w-25 d-inline-block vertical-align-top">
                <label class="fw-bold" for="add_bulk_post_fileinput">Upload your file:</label>
            </div>
            <div class="d-inline-block">
                <input type="file" name="add_bulk_post_fileinput" id="add_bulk_post_fileinput"
                    accept=".csv, .xlsx, application/vnd.ms-excel, application/xml, text/xml">
                <br>
                <button
                    class="btn bg-primary border-0 py-2 px-3 cursor-pointer text-light generate_bulkpost_options">Generate
                    options</button>
            </div>
        </div>
        <hr>
        <div class="form-group my-3">
            <div class="w-25 d-inline-block">
                <label class="fw-bold" for="posttype_name">Post Type:</label>
            </div>
            <div class="d-inline-block">
                <select id="posttype_name" name="posttype_name" class="form-control">
                    <?php echo $posttype_options; ?>
                </select>
            </div>
        </div>
        <hr>

        <div>
            <h3>Fields</h3>
        </div>
        <div class="generated-columns-group">
        </div>

        <hr>
        <div class="form-group my-3">
            <div class="w-25 d-inline-block vertical-align-top">
                <label class="fw-bold" for="remove_rows">Skip rows:</label>
            </div>
            <div class="d-inline-block">
                <input type="text" id="remove_rows" name="remove_rows" class="form-control" value="1">
                <br>
                <p class="m-0">*Type your row numbers and for use multiple, separate them by comma. (Eg- 1, 6, 10)</p>
            </div>
        </div>
        <div class="form-group posttype_submit_changes mt-4">
            <div class="d-inline-block">
                <input type="submit" value="Update" name="insert_bulk_post_action"
                    class="btn bg-primary border-0 py-2 px-3 cursor-pointer text-light insert_bulk_post_action disabled">
            </div>
        </div>
    </form>
</div>