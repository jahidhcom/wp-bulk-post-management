<?php
$data = process_all_post_type();
$metafields = [];
foreach ($data['fields'] as $key => $row) {
    $metafields[] = "\"$key\": [\"" . implode('", "', $row) . "\"]";
}
$metafields = implode(',', $metafields);
?>
<script type='application/javascript'>
    (function ($) {
        $(document).ready(function () {
            let metafields = { <?php echo $metafields ?> };
            let posttype_select = $('[name="bulk_update_posttype"]');
            if (posttype_select.length > 0) {
                posttype_select.on('change', function () {
                    let val = $(this).val();
                    let generate_options = '';
                    $.each(metafields[val], function (index, value) {
                        var metafield = metafields[val][index];
                        generate_options += '<option value="' + metafield + '">' + metafield.split("/")[0] + '</option>';
                    });
                    let posttype_fields = $(".posttype_fields");
                    let prevCheckVal = posttype_fields.find("[name='posttype_fields']").val();
                    posttype_fields.find("[name='posttype_fields']").html(generate_options);
                    posttype_fields.find("[name='posttype_fields'] option[value='" + prevCheckVal + "']").prop("selected", true);

                    let currCheckVal = posttype_fields.find("[name='posttype_fields']").val();
                    if (currCheckVal == 'post_author') {
                        posttype_content_field_handler('author')
                    } else if (currCheckVal == 'post_content' || /\/metafields/.test(currCheckVal)) {
                        posttype_content_field_handler('longtext')
                    } else if (/date/i.test(currCheckVal)) {
                        posttype_content_field_handler('date')
                    } else {
                        posttype_content_field_handler('shorttext')
                    }
                    $('[name="current_posttype_fields"]').html(generate_options);
                    posttype_fields.removeClass('d-none');
                });
            }

            let selectwith = $('[name="select_with"]');

            if (selectwith.length > 0) {
                selectwith.on('change', function () {
                    let selectwith_val = $(this).val();
                    if (selectwith_val == 'date_range') {
                        $('.daterange_field').removeClass('d-none');
                        $('.daterange_field_input').removeClass('d-none');
                        $('.current_posttype_field_input').addClass('d-none');
                        $('.current_posttype_fields').addClass('d-none');
                    } else if (selectwith_val == 'field') {
                        $('.daterange_field').addClass('d-none');
                        $('.daterange_field_input').addClass('d-none');
                        $('.current_posttype_field_input').removeClass('d-none');
                        $('.current_posttype_fields').removeClass('d-none');

                        let val = $('[name="bulk_update_posttype"]').val();
                        let generate_options = '';
                        $.each(metafields[val], function (index, value) {
                            var metafield = metafields[val][index];
                            generate_options += '<option value="' + metafield + '">' + metafield.split("/")[0] + '</option>';
                        });
                        $('[name="current_posttype_fields"]').html(generate_options);

                    }
                })
            }

            $('[name="posttype_fields"]').on('change', function () {
                let val = $(this).val();
                if (val == 'post_author') {
                    posttype_content_field_handler('author')
                } else if (val == 'post_content' || /\/metafields/.test(val)) {
                    posttype_content_field_handler('longtext')
                } else if (/date/i.test(val)) {
                    posttype_content_field_handler('date')
                } else {
                    posttype_content_field_handler('shorttext')
                }
            });
            $('[name="change_default_content_input"]').on('change', function () {
                let val = $(this).val();
                posttype_content_field_handler(val);
            });
            function posttype_content_field_handler(nameValue) {
                let all_fields = { author: 'posttype_author_field', date: 'posttype_date_field', shorttext: 'posttype_shorttext_field', longtext: 'posttype_longtext_field' };
                $.each(all_fields, function (key, name) {
                    if (nameValue == key) {
                        $('.' + name).removeClass('d-none');
                    } else {
                        $('.' + name).addClass('d-none');
                    }
                    if (nameValue == 'author' || nameValue == 'date') {
                        $('.update_input_type').addClass('d-none');
                    } else {
                        $('.update_input_type').removeClass('d-none');
                    }
                });
                $('[name="change_default_content_input"]').val(nameValue);
            }

            $('[name="change_default_content_input_check"]').on('change', function () {
                if ($(this).is(':checked')) {
                    $('[name="change_default_content_input"]').removeClass('opacity-none');
                } else {
                    $('[name="change_default_content_input"]').addClass('opacity-none');
                }
            });
            $('.date-range').daterangepicker({
                opens: 'center'
            }, function (start, end, label) {
                console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
            });
        });
    })(jQuery);
</script>