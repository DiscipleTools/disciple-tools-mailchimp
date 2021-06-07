jQuery(function ($) {

  $(document).ready(function () {
    display_sub_sections();
  });

  $(document).on('click', '#mc_main_col_connect_mc_api_key_show', function () {
    let api_key_input_ele = $('#mc_main_col_connect_mc_api_key');
    let api_key_show_ele = $('#mc_main_col_connect_mc_api_key_show');

    if (api_key_show_ele.is(':checked')) {
      api_key_input_ele.attr('type', 'text');
    } else {
      api_key_input_ele.attr('type', 'password');
    }
  });

  $(document).on('click', '#mc_main_col_available_mc_lists_select_mc_list_add', function () {
    mc_list_add();
  });

  $(document).on('click', '.mc-main-col-support-mc-lists-table-row-remove-but', function (e) {
    mc_list_remove(e);
  });

  $(document).on('click', '#mc_main_col_support_dt_post_types_select_ele_add', function () {
    dt_type_add(
      'mc_main_col_support_dt_post_types_select_ele',
      'mc_main_col_support_dt_post_types_table',
      'mc_main_col_support_dt_post_types_form',
      'mc_main_col_support_dt_post_types_hidden'
    );
  });

  $(document).on('click', '#mc_main_col_support_dt_field_types_select_ele_add', function () {
    dt_type_add(
      'mc_main_col_support_dt_field_types_select_ele',
      'mc_main_col_support_dt_field_types_table',
      'mc_main_col_support_dt_field_types_form',
      'mc_main_col_support_dt_field_types_hidden'
    );
  });

  $(document).on('click', '.mc-main-col-support-dt-type-table-row-remove-but', function (e) {
    dt_type_remove(e);
  });

  function display_sub_sections() {

    // Sub-sections to be displayed once a valid Mailchimp api key has been detected.

    let available_table_section = $('#mc_main_col_available_table_section');
    let support_table_section = $('#mc_main_col_support_table_section');
    let support_dt_post_types_table_section = $('#mc_main_col_support_dt_post_types_table_section');
    let support_dt_field_types_table_section = $('#mc_main_col_support_dt_field_types_table_section');

    if (!$('#mc_main_col_connect_mc_api_key').val().trim()) {
      available_table_section.fadeOut('fast');
      support_table_section.fadeOut('fast');
      support_dt_post_types_table_section.fadeOut('fast');
      support_dt_field_types_table_section.fadeOut('fast');
    } else {
      available_table_section.fadeIn('fast');
      support_table_section.fadeIn('fast');
      support_dt_post_types_table_section.fadeIn('fast');
      support_dt_field_types_table_section.fadeIn('fast');
    }
  }

  function mc_list_add() {
    let selected_list_id = $('#mc_main_col_available_mc_lists_select_mc_list').val();
    let selected_list_name = $('#mc_main_col_available_mc_lists_select_mc_list option:selected').text();

    // Only proceed if we have a valid id
    if (!selected_list_id) {
      return;
    }

    // Set hidden form values and post
    $('#mc_main_col_available_selected_list_id').val(selected_list_id);
    $('#mc_main_col_available_selected_list_name').val(selected_list_name);
    $('#mc_main_col_available_form').submit();
  }

  function mc_list_remove(evt) {
    let selected_list_id = evt.currentTarget.parentNode.parentNode.querySelector('#mc_main_col_support_mc_lists_table_row_remove_hidden_id').getAttribute('value');
    let selected_list_tr_ele = evt.currentTarget.parentNode.parentNode.parentNode;

    // Remove from hidden current list array
    mc_hidden_list_remove(selected_list_id);

    // Save removal updates
    $('#mc_main_col_support_form').submit();
  }

  function mc_hidden_list_remove(id) {
    let current_list = mc_hidden_list_load();

    if (current_list) {
      delete current_list[id];
      mc_hidden_list_save(current_list);
    }
  }

  function mc_hidden_list_load() {
    return JSON.parse($('#mc_main_col_support_mc_lists_hidden_current_mc_list').val())
  }

  function mc_hidden_list_save(updated_list) {
    $('#mc_main_col_support_mc_lists_hidden_current_mc_list').val(JSON.stringify(updated_list));
  }

  function dt_type_add(dt_type_select_ele, dt_type_table, dt_type_form, dt_type_hidden) {
    let selected_type_id = $('#' + dt_type_select_ele).val();
    let selected_type_name = $('#' + dt_type_select_ele + ' option:selected').text();

    // Ignore empty values
    if (!selected_type_id) {
      return;
    }

    // Only proceed if type has not already been assigned
    if (dt_type_add_already_assigned(selected_type_id, dt_type_table)) {
      return;
    }

    // Update and save selected type
    dt_type_update(selected_type_id, selected_type_name, dt_type_table, dt_type_form, dt_type_hidden);
  }

  function dt_type_add_already_assigned(selected_type_id, dt_type_table) {
    let assigned = false;

    $('#' + dt_type_table + ' > tbody > tr').each(function (idx, tr) {
      let dt_type_id = $(tr).find('#mc_main_col_support_dt_type_id_hidden').val();
      if (dt_type_id && dt_type_id === selected_type_id) {
        assigned = true;
      }
    });

    return assigned;
  }

  function dt_type_update(selected_type_id, selected_type_name, dt_type_table, dt_type_form, dt_type_hidden) {
    let types = [];

    // Iterate and package already existing types
    $('#' + dt_type_table + ' > tbody > tr').each(function (idx, tr) {
      let dt_type_id = $(tr).find('#mc_main_col_support_dt_type_id_hidden').val();
      let dt_type_name = $(tr).find('#mc_main_col_support_dt_type_name_hidden').val();

      types.push({
        "id": dt_type_id,
        "name": dt_type_name
      });
    });

    // If available, also include recently added type
    if (selected_type_id && selected_type_name) {
      types.push({
        "id": selected_type_id,
        "name": selected_type_name
      });
    }

    // Save updated types ready for posting
    $('#' + dt_type_hidden).val(JSON.stringify(types));

    // Trigger form post..!
    $('#' + dt_type_form).submit();
  }

  function dt_type_remove(evt) {

    // Obtain handle onto deleted row
    let row = evt.currentTarget.parentNode.parentNode.parentNode;

    // Remove row from parent table
    row.parentNode.removeChild(row);

    // Obtain hidden values and persist/save changes
    let dt_type_table = $(row).find('#mc_main_col_support_dt_type_table_hidden').val();
    let dt_type_form = $(row).find('#mc_main_col_support_dt_type_form_hidden').val();
    let dt_type_hidden = $(row).find('#mc_main_col_support_dt_type_values_hidden').val();

    dt_type_update(null, null, dt_type_table, dt_type_form, dt_type_hidden);
  }
});
