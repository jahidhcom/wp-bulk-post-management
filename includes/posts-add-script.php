<?php
$data = process_all_post_type();
$metafields = [];
foreach ($data['fields'] as $key => $row) {
    $metafields[] = "\"$key\": [\"" . implode('", "', $row) . "\"]";
}
$metafields = implode(',', $metafields);
?>
<script>
    (function ($) {
        $(document).ready(function () {
            let metafields = { <?php echo $metafields ?> };
            let prevSelectedValues = [];

            $(document).on('change', '[name="posttype_name"]', function () {
                let val = $('[name="posttype_name"]').val();
                let generate_options = '<option value="">--Select</option>';
                $.each(metafields[val], function (index, value) {
                    var metafield = metafields[val][index];
                    generate_options += '<option value="' + metafield + '">' + metafield.split("/")[0] + '</option>';
                });
                generate_options += '<option value="post_categories">post_categories</option>';
                $('.generated-columns-group .posttype_fields').each(function (key, elm) {
                    var value = $(this).val();
                    $(this).html(generate_options);
                    if (metafields[val].includes(value)) {
                        $(this).val(value);
                    }
                });
                let returnLastSelectedRow = posttype_field_change_handler([]);
                prevSelectedValues = returnLastSelectedRow;
            });
            $(document).on('click', '.delete-bulkpost-column', function (e) {
                e.preventDefault();
                if (confirm('Are you sure you want to remove this column?')) {
                    $(this).closest('.form-group').remove();
                }
            });
            let files_data;
            $(document).on('click', '.generate_bulkpost_options', function (e) {
                e.preventDefault();
                let getfile = $('[name="add_bulk_post_fileinput"]').prop('files')[0];
                let getposttype = $('[name="posttype_name"]').val();
                if (!getfile || getfile == '') {
                    $('#error_messages').html('<p class="text-danger m-0">Select a file first.</p>');
                } else if (!getposttype || getposttype == '') {
                    $('#error_messages').html('<p class="text-danger m-0">Select a post type.</p>');
                } else {
                    $('#error_messages').html('');
                    let nonce = "<?php echo wp_create_nonce("bulk_posts__nonce__form_handler"); ?>";

                    const formData = new FormData();

                    formData.append("action", "bulk_posts_form_handler");
                    formData.append("getfile", getfile);
                    formData.append("getposttype", getposttype);
                    formData.append("nonce", nonce);

                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        dataType: "json",
                        contentType: false,
                        processData: false,
                        data: formData,
                        success: function (resp) {
                            if (resp.error) {
                                $('#error_messages').html("<p class='text-danger m-0'>" + resp.error + "</p>");
                            } else if (resp.html) {
                                $('.generated-columns-group').html(resp.html);
                            }
                            if (resp.data) {
                                files_data = resp.data;
                                $('.insert_bulk_post_action').removeClass('disabled');
                                let returnLastSelectedRow = posttype_field_change_handler([]);
                                prevSelectedValues = returnLastSelectedRow;
                            }
                        },
                        error: function (xhr, status, error) {
                            console.log('AJAX error:', error);
                            $('#error_messages').text('An error occurred. Please try again.');
                        }
                    });
                }
            });
            $(document).on('change', '[name="files_sheet_name"]', function (e) {
                let val = $('[name="files_sheet_name"]').val();
                let new_data = files_data[val];
                let new_options = '';
                for (let i = 0; i < new_data.length; i++) {
                    new_options += "<option value=" + i + ">Column " + (i + 1) + "</option>";
                }
                $('select[name="columns_names[]"]').each(function (key, elm) {
                    $(this).html(new_options);
                });
            });
            $(document).on('click', 'button.add_new_custom_item', function (e) {
                e.preventDefault();
                let posttype_field = $('.generated-field select[name="posttype_fields[]"]').html();

                let selectedValues = $('select[name="posttype_fields[]"]').map(function () {
                    if ($(this).val() !== '') return $(this).val();
                }).get();
                let val = $('[name="posttype_name"]').val();
                let generate_options = '<option value="">--Select</option>';
                $.each(metafields[val], function (index, value) {
                    var metafield = metafields[val][index];
                    if ($.inArray(metafield, selectedValues) !== -1) {
                        generate_options += '<option value="' + metafield + '" disabled>' + metafield.split("/")[0] + '</option>';
                    } else {
                        generate_options += '<option value="' + metafield + '">' + metafield.split("/")[0] + '</option>';
                    }
                });
                generate_options += '<option value="post_categories">post_categories</option>';

                let generate_html = `<div class="form-group my-3 generated-field">
                    <div class="w-45 d-inline-block vertical-align-top">
                        <select name="posttype_fields[]" class="form-control posttype_fields" required="">
                            ${generate_options}
                        </select>
                    </div>
                    <div class="d-inline-block">
                        <input type="text" name="columns_names[]" class="form-control columns_names" placeholder="Type your content">
                        <br>
                        <button class="btn bg-danger border-0 py-2 px-3 cursor-pointer text-light mt-1 delete-bulkpost-column">Delete</button>
                    </div>
                </div>`;
                $(generate_html).insertBefore('.extra-action-box');

            });
            $(document).on('click', 'button.add_new_column_item', function (e) {
                e.preventDefault();
                let posttype_field = $('.generated-field select[name="posttype_fields[]"]').html();

                let selectedValues = $('select[name="posttype_fields[]"]').map(function () {
                    if ($(this).val() !== '') return $(this).val();
                }).get();
                let val = $('[name="posttype_name"]').val();
                let generate_options = '<option value="">--Select</option>';
                $.each(metafields[val], function (index, value) {
                    var metafield = metafields[val][index];
                    if ($.inArray(metafield, selectedValues) !== -1) {
                        generate_options += '<option value="' + metafield + '" disabled>' + metafield.split("/")[0] + '</option>';
                    } else {
                        generate_options += '<option value="' + metafield + '">' + metafield.split("/")[0] + '</option>';
                    }
                });
                generate_options += '<option value="post_categories">post_categories</option>';


                let column_field = $('.generated-field select[name="columns_names[]"]').html();

                let generate_html = `<div class="form-group my-3 custom-generated-field">
                <div class="w-45 d-inline-block vertical-align-top">
                    <select name="posttype_fields[]" class="form-control posttype_fields" required="">
                        ${generate_options}
                    </select>
                    </div>
                    <div class="d-inline-block">
                        <select name="columns_names[]" class="form-control columns_names">
                            ${column_field}
                        </select>
                        <br>
                        <button class="btn bg-danger border-0 py-2 px-3 cursor-pointer text-light mt-1 delete-bulkpost-column">Delete</button>
                    </div>
                </div>`;
                $(generate_html).insertBefore('.extra-action-box');
            });

            $(document).on('change', 'select[name="posttype_fields[]"]', function () {
                let returnLastSelectedRow = posttype_field_change_handler(prevSelectedValues);
                prevSelectedValues = returnLastSelectedRow;
            });

            function posttype_field_change_handler(prevSelectedValues) {
                let selectedValues = $('select[name="posttype_fields[]"]').map(function () {
                    if ($(this).val() !== '') return $(this).val();
                }).get();

                let diffValueOld = $(prevSelectedValues).not(selectedValues).get();
                let diffValueNew = $(selectedValues).not(prevSelectedValues).get();



                if (diffValueNew.length > 0) {
                    for (var i = 0; i < diffValueNew.length; i++) {
                        $('select[name="posttype_fields[]"]').each(function () {
                            let elmVal = $(this).val();
                            if (diffValueNew[i] !== elmVal) {
                                $(this).children('option[value="' + diffValueNew[i] + '"]').attr('disabled', '');
                            }
                        });
                    }
                    console.log('Hello :0');
                }
                if (diffValueOld.length > 0) {
                    for (var i = 0; i < diffValueOld.length; i++) {
                        $('select[name="posttype_fields[]"] option[value="' + diffValueOld[i] + '"]').removeAttr('disabled');
                    }
                }
                // prevSelectedValues = selectedValues;
                return selectedValues;
            }
            $(document).on('change', '[name="add_bulk_post_fileinput"]', function () {
                $('.generated-columns-group').html('');
                $('.insert_bulk_post_action').addClass('disabled');
            });

        });
    })(jQuery);
</script>