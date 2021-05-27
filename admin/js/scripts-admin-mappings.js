jQuery(function ($) {
  $(document).on('click', '#mc_mappings_main_col_selected_mc_list_add_mapping_but', function () {
    mc_list_mapping_add();
  });

  $(document).on('click', '.mc-mappings-main-col-selected-mc-list-remove-mapping-but', function (e) {
    mc_list_mapping_remove(e);
  });

  $(document).on('click', '#mc_mappings_main_col_selected_mc_list_update_but', function () {
    mc_list_update();
  });

  $(document).on('change', '.mc-mappings-main-col-selected-mc-list-mappings-table-col-options-select-ele', function (e) {
    mapping_option_display_selected(e.currentTarget);
  });

  $(document).on('click', '#mappings_option_field_sync_direction_remove_but', function () {
    mapping_option_field_sync_direction_remove();
  });

  $(document).on('click', '#mappings_option_field_sync_direction_commit_but', function () {
    mapping_option_field_sync_direction_commit();
  });

  $(document).on('change', '#mc_mappings_main_col_selected_mc_list_assigned_post_type', function () {
    switch_assigned_post_types($('#mc_mappings_main_col_selected_mc_list_assigned_post_type').val());
  });

  function mc_list_mapping_add() {
    table_row_add();
  }

  function mc_list_mapping_remove(evt) {
    table_row_remove(evt.currentTarget.parentNode.parentNode.parentNode);
  }

  function table_row_add() {
    let mapping_id = Date.now();
    let html = '<tr>';
    html += '<input type="hidden" id="mc_mappings_main_col_selected_mc_list_mappings_table_row_mapping_id_hidden" value="' + mapping_id + '" />';
    html += '<td style="text-align: center;">' + mapping_id + '</td>';
    html += '<td style="text-align: center;">' + table_row_add_col_mc_fields() + '</td>';
    html += '<td style="text-align: center;">' + table_row_add_col_dt_fields() + '</td>';
    html += '<td style="text-align: center;">' + table_row_add_col_options() + '</td>';
    html += '<td><span style="float:right;"><a class="button float-right mc-mappings-main-col-selected-mc-list-remove-mapping-but">Remove</a></span></td>';
    html += '</tr>';
    $('#mc_mappings_main_col_selected_mc_list_mappings_table').append(html);
  }

  function table_row_add_col_mc_fields() {
    let mc_fields = JSON.parse($('#mc_mappings_main_col_selected_mc_list_fields_hidden').val());

    let html = '<select id ="mc_mappings_main_col_selected_mc_list_mappings_table_col_mc_fields_select_ele" style="max-width: 100px;">';
    for (let i = 0; i < mc_fields.length; i++) {
      html += '<option value="' + window.lodash.escape(mc_fields[i].merge_id) + '">' + window.lodash.escape(mc_fields[i].name) + '</option>';
    }
    html += '</select>';

    return html;
  }

  function table_row_add_col_dt_fields() {
    let dt_fields = JSON.parse($('#mc_mappings_main_col_selected_mc_list_dt_fields_hidden').val());

    let html = '<select id ="mc_mappings_main_col_selected_mc_list_mappings_table_col_dt_fields_select_ele" style="max-width: 100px;">';
    dt_fields.forEach(post_type => {
      // Post type section heading
      html += '<option disabled selected value>-- ' + window.lodash.escape(post_type.post_type_label) + ' --</option>';

      // Post type fields
      post_type.post_type_fields.forEach(field => {
        html += '<option value="' + window.lodash.escape(field.id) + '">' + window.lodash.escape(field.name) + '</option>';
      });
    });
    html += '</select>';

    return html;
  }

  function table_row_add_col_options() {
    let html = '<select id="mc_mappings_main_col_selected_mc_list_mappings_table_col_options_select_ele" class="mc-mappings-main-col-selected-mc-list-mappings-table-col-options-select-ele" style="max-width: 100px;">';
    html += '<option selected value="">-- select option --</option>';
    html += '<option value="field-sync-direction">Field Sync Directions</option>';
    html += '</select>';
    html += '<input id="mc_mappings_main_col_selected_mc_list_mappings_table_col_options_hidden" type="hidden" value="[]" />'

    return html;
  }

  function table_row_remove(row_ele) {
    row_ele.parentNode.removeChild(row_ele);
  }

  function mc_list_update() {
    let mappings = [];

    // Iterate over existing table mapping rows
    $('#mc_mappings_main_col_selected_mc_list_mappings_table > tbody > tr').each(function (idx, tr) {

      // Source current row values
      let mapping_id = $(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_row_mapping_id_hidden').val();
      let mc_field_id = $(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_col_mc_fields_select_ele').val();
      let dt_field_id = $(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_col_dt_fields_select_ele').val();
      let options = JSON.parse($(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_col_options_hidden').val());

      // Ensure key values present needed to form mapping
      if (mc_list_update_validate_values(mapping_id, mc_field_id, dt_field_id, options)) {

        // Create new mapping object and add to master mappings
        mappings.push({
          "mapping_id": mapping_id,
          "mc_field_id": mc_field_id,
          "dt_field_id": dt_field_id,
          "options": options
        });

      } else {
        console.log("Invalid values detected at index: " + idx);
      }
    });

    // Package within a mappings object
    let mappings_obj = {
      "mc_list_id": $('#mc_mappings_main_col_selected_mc_list_id_hidden').val(),
      "mc_list_name": $('#mc_mappings_main_col_selected_mc_list_name_hidden').val(),
      "dt_post_type": $('#mc_mappings_main_col_selected_mc_list_assigned_post_type').val(),
      "mappings": mappings
    }

    // Save updated mappings object
    $('#mc_mappings_main_col_selected_mc_list_mappings_hidden').val(JSON.stringify(mappings_obj));

    // Trigger form post..!
    $('#mc_mappings_main_col_selected_mc_list_update_form').submit();

  }

  function mc_list_update_validate_values(mapping_id, mc_field_id, dt_field_id, options) {
    return ((mapping_id && mapping_id !== "") &&
      (mc_field_id && mc_field_id !== "") &&
      (dt_field_id && dt_field_id !== ""));
  }

  function switch_assigned_post_types(selected_post_type) {
    $('#mc_mappings_main_col_selected_mc_list_mappings_table > tbody').empty();
  }

  function mapping_option_load_option(selected, option_id) {
    let option_found = null;
    let options = JSON.parse(selected.parentNode.querySelector('#mc_mappings_main_col_selected_mc_list_mappings_table_col_options_hidden').value);

    // Loop over options in search of specific option
    options.forEach(option => {
      if ((option) && (option.id === option_id)) {
        option_found = option;
      }
    });

    return option_found;
  }

  function mapping_option_update_option(mapping_id, option_id, option, remove_only, callback) {
    $('#mc_mappings_main_col_selected_mc_list_mappings_table > tbody > tr').each(function (idx, tr) {
      if ($(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_row_mapping_id_hidden').val() === mapping_id) {
        let options = JSON.parse($(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_col_options_hidden').val());

        // Loop over options in search of previous option settings, to be removed
        options.forEach((option, opt_idx) => {
          if ((option) && (option.id === option_id)) {
            options.splice(opt_idx, 1);
          }
        });

        // Add/Commit latest option settings and save back to hidden field, assuming it's a full update request
        if (!remove_only) {
          options.push(option);
        }
        $(tr).find('#mc_mappings_main_col_selected_mc_list_mappings_table_col_options_hidden').val(JSON.stringify(options));
      }
    });

    callback();
  }

  function mapping_option_display_selected(selected) {
    // Hide whatever is currently displayed, prior to showing recently selected
    $('#mappings_option_div').fadeOut('slow', function () {
      if (selected.value) {
        switch (selected.value) {
          case 'field-sync-direction':
            mapping_option_field_sync_direction(selected);
            break;
          default:
            break;
        }
      }
    });
  }

  function mapping_option_field_sync_direction(selected) {

    // Fetch values
    let mappings_option_div = $('#mappings_option_div');
    let option_value = selected.value;
    let option_text = selected.options[selected.selectedIndex].text;
    let mapping_id = selected.parentNode.parentNode.querySelector('#mc_mappings_main_col_selected_mc_list_mappings_table_row_mapping_id_hidden').value;

    // Update main mapping options area with selected view display shape
    mappings_option_div.html($('#mappings_option_field_sync_direction').html());

    // Set key header fields
    $('#mappings_option_field_sync_direction_title').html(option_text);
    $('#mappings_option_field_sync_direction_mapping_id').html(mapping_id);
    $('#mappings_option_field_sync_direction_option_id_hidden').val(option_value);

    // Attempt to Load any saved options or revert to defaults
    let option = mapping_option_load_option(selected, option_value);

    let enabled = (option) ? option.enabled : true;
    let priority = (option) ? option.priority : 1;
    let mc_sync_feeds = (option) ? option.mc_sync_feeds : true;
    let dt_sync_feeds = (option) ? option.dt_sync_feeds : true;

    // Set visuals accordingly
    $('#mappings_option_field_sync_direction_enabled').prop('checked', enabled);
    $('#mappings_option_field_sync_direction_exec_priority').val(priority);
    $('#mappings_option_field_sync_direction_pull_mc').prop('checked', mc_sync_feeds);
    $('#mappings_option_field_sync_direction_push_dt').prop('checked', dt_sync_feeds);

    // Display selected mapping options view
    mappings_option_div.fadeIn('fast');
  }

  function mapping_option_field_sync_direction_commit() {

    // Capture current values
    let option_id = $('#mappings_option_field_sync_direction_option_id_hidden').val();
    let mapping_id = $('#mappings_option_field_sync_direction_mapping_id').html();
    let enabled = $('#mappings_option_field_sync_direction_enabled').prop('checked');
    let priority = $('#mappings_option_field_sync_direction_exec_priority').val();
    let mc_sync_feeds = $('#mappings_option_field_sync_direction_pull_mc').prop('checked');
    let dt_sync_feeds = $('#mappings_option_field_sync_direction_push_dt').prop('checked');

    mapping_option_update_option(mapping_id, option_id, {
      'id': option_id,
      'mapping_id': mapping_id,
      'enabled': enabled,
      'priority': priority,
      'mc_sync_feeds': mc_sync_feeds,
      'dt_sync_feeds': dt_sync_feeds

    }, false, function () {
      $('#mappings_option_div').fadeOut('fast', function () {
        $('#mappings_option_div').fadeIn('fast');
      });
    })
  }

  function mapping_option_field_sync_direction_remove() {

    // Capture current values
    let option_id = $('#mappings_option_field_sync_direction_option_id_hidden').val();
    let mapping_id = $('#mappings_option_field_sync_direction_mapping_id').html();

    mapping_option_update_option(mapping_id, option_id, null, true, function () {
      $('#mappings_option_div').fadeOut('slow');
    });
  }
});
