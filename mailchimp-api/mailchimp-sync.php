<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Extend schedule options.
 */

add_filter( 'cron_schedules', 'cron_schedules_callback', 10, 1 );
function cron_schedules_callback( $schedules ) {
    $arr = array();

    $arr['minute'] = array(
        'interval' => 1 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every Minute' )
    );

    $arr['5_minutes'] = array(
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 5 Minutes' )
    );

    return $arr;
}

/**
 * Register cron module.
 */

if ( ! wp_next_scheduled( 'dt_mailchimp_sync' ) ) {
    wp_schedule_event( time(), 'minute', 'dt_mailchimp_sync' );
}

/**
 * Core synchronisation logic.
 */

add_action( 'dt_mailchimp_sync', 'dt_mailchimp_sync_run' );
function dt_mailchimp_sync_run() {

    // DT -> MC Sync
    sync_dt_to_mc();

    // DT <- MC Sync
    sync_mc_to_dt();
}

function sync_dt_to_mc() {
    if ( is_sync_enabled( 'dt_mailchimp_dt_push_sync' ) ) {

        sync_debug( 'dt_mailchimp_dt_debug', '' );

        // Determine last run timestamp
        $last_run = fetch_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc' );

        // Adjust run start sliding-window, so as to capture any stragglers, since last run
        $last_run_start_window = adjust_last_run_start_window( $last_run, 1 ); // 1Hr prior to last run

        // Fetch supported mc lists
        $supported_mc_lists = fetch_supported_array( 'dt_mailchimp_mc_supported_lists' );

        // Fetch field mappings
        $supported_mappings = fetch_supported_mappings();

        // Loop over supported dt post types
        $supported_dt_post_types = fetch_supported_array( 'dt_mailchimp_dt_supported_post_types' );
        foreach ( $supported_dt_post_types as $dt_post_type_id ) {

            // Query dt for changed/new records
            $latest_dt_records = fetch_latest_dt_records( $dt_post_type_id, $last_run_start_window );

            // Loop over latest dt post ids
            foreach ( $latest_dt_records as $dt_post_id ) {

                // Fetch corresponding post record
                $dt_post_record = DT_Posts::get_post( $dt_post_type_id, $dt_post_id->ID, false, false );

                // Ensure dt record is to be kept in sync
                if ( is_dt_record_sync_enabled( $dt_post_record ) ) {

                    // Iterate over subscribed mc lists and find associated mapping; assuming list is available and supported
                    foreach ( $dt_post_record['disciple_tools_mailchimp_supported_lists_multiselect'] as $subscribed_mc_list_id ) {

                        // Ensure mc list is supported and has mapping
                        if ( in_array( $subscribed_mc_list_id, $supported_mc_lists ) && isset( $supported_mappings->$subscribed_mc_list_id ) ) {

                            // Ensure field mappings correspond with current post type id; so as to avoid cross post type syncs
                            if ( has_matching_post_type_field_mappings( $dt_post_type_id, $supported_mappings->$subscribed_mc_list_id->mappings ) ) {

                                // Extract array of mapped fields; which are to be kept in sync
                                $field_mappings = extract_field_mappings( $dt_post_type_id, $supported_mappings->$subscribed_mc_list_id->mappings );

                                // First, attempt to fetch corresponding mc record, using the info to hand!
                                $mc_record = fetch_mc_record( $dt_post_record, $subscribed_mc_list_id );

                                // If still no hit, then a new mc record will be created
                                $is_new_mc_record = false;
                                if ( empty( $mc_record ) ) {
                                    $mc_record        = create_mc_record( $dt_post_record, $subscribed_mc_list_id );
                                    $is_new_mc_record = true;
                                }

                                // Only proceed if we have a handle on corresponding mc record
                                if ( ! empty( $mc_record ) ) {

                                    // Apart from a newly created mc record; which will default to current mapped fields
                                    // ensure dt has the most recent modifications of the two records, in order to update
                                    if ( $is_new_mc_record || dt_record_has_latest_changes( $dt_post_record, $mc_record ) ) {

                                        $updated = update_mc_record( $subscribed_mc_list_id, $dt_post_record, $mc_record, $field_mappings );
                                        sync_debug( 'dt_mailchimp_dt_debug', $updated );

                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Update last sync run timestamp
        update_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc', time() );
    }
}

function sync_mc_to_dt() {
    if ( is_sync_enabled( 'dt_mailchimp_mc_accept_sync' ) ) {

        sync_debug( 'dt_mailchimp_mc_debug', '' );

        // Determine last run timestamp
        $last_run = fetch_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt' );

        // Adjust run start sliding-window, so as to capture any stragglers, since last run
        $last_run_start_window = adjust_last_run_start_window( $last_run, 1 ); // 1Hr prior to last run

        // Fetch supported mc lists
        $supported_mc_lists = fetch_supported_array( 'dt_mailchimp_mc_supported_lists' );

        // Fetch field mappings
        $supported_mappings = fetch_supported_mappings();

        // Loop over supported mc lists
        foreach ( $supported_mc_lists as $mc_list_id ) {

            // Query mc for changed/new member records
            $latest_mc_records = fetch_latest_mc_records( $mc_list_id, $last_run_start_window );

            // Loop over latest mc member records
            foreach ( $latest_mc_records as $mc_record ) {

                // Ensure record's assigned mc list is supported and has mapping
                if ( in_array( $mc_record->list_id, $supported_mc_lists ) && isset( $supported_mappings->{$mc_record->list_id} ) ) {

                    // Identify mappings post types - There should only be a single hit, so as to avoid cross post type mapping updates!
                    $mapping_post_types = identify_mapping_post_types( $supported_mappings->{$mc_record->list_id}->mappings );
                    if ( count( $mapping_post_types ) === 1 ) {

                        // Ensure mc record has hidden field containing dt post id
                        if ( isset( $mc_record->merge_fields->{Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field()} ) ) {

                            $dt_post_type_id = $mapping_post_types[0];
                            $dt_post_id      = $mc_record->merge_fields->{Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field()};

                            // Fetch corresponding dt post record and ensure it is to be kept in sync and it's subscribed
                            $dt_record = DT_Posts::get_post( $dt_post_type_id, $dt_post_id, false, false );
                            if ( ! empty( $dt_record ) && is_dt_record_sync_enabled( $dt_record ) && in_array( $mc_record->list_id, $dt_record['disciple_tools_mailchimp_supported_lists_multiselect'] ) ) {

                                // Ensure mc record has latest changes, in order to update dt
                                if ( ! dt_record_has_latest_changes( $dt_record, $mc_record ) ) {

                                    // Extract array of mapped fields; which are to be kept in sync
                                    $field_mappings = extract_field_mappings( $dt_post_type_id, $supported_mappings->{$mc_record->list_id}->mappings );

                                    // Update dt record
                                    $updated = update_dt_record( $dt_record, $mc_record, $field_mappings );
                                    sync_debug( 'dt_mailchimp_mc_debug', $updated );
                                }
                            }
                        }
                    }
                }
            }
        }

        // Update last sync run timestamp
        update_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt', time() );
    }
}

function sync_debug( $option_name, $msg ) {
    update_option( $option_name, $msg );
}

function adjust_last_run_start_window( $last_run, $hrs ): int {
    return $last_run - ( 3600 * $hrs );
}

function is_sync_enabled( $option_name ): bool {
    return boolval( get_option( $option_name ) );
}

function fetch_supported_mappings() {
    return json_decode( get_option( 'dt_mailchimp_mappings' ) );
}

function update_last_run( $option_name, $timestamp ) {
    update_option( $option_name, $timestamp );
}

function fetch_last_run( $option_name ): int {
    $last_run = get_option( $option_name );

    return ! empty( $last_run ) ? intval( $last_run ) : time();
}

function fetch_supported_array( $option_name, $ids_only = true ): array {
    $supported_array = get_option( $option_name );

    $array = ! empty( $supported_array ) ? json_decode( $supported_array ) : [];

    if ( $ids_only ) {
        $ids = array();
        foreach ( $array as $item ) {
            $ids[] = $item->id;
        }

        return $ids;

    } else {
        return $array;
    }
}

function fetch_latest_dt_records( $post_type, $timestamp ): array {
    global $wpdb;

    return $wpdb->get_results( $wpdb->prepare( "
SELECT post.ID
FROM $wpdb->posts post
LEFT JOIN $wpdb->postmeta meta ON (post.ID = meta.post_id)
WHERE (post.post_type = %s)
  AND ((meta.meta_key = 'last_modified') AND (meta.meta_value > %d))
GROUP BY post.ID", $post_type, $timestamp ) );
}

function fetch_latest_mc_records( $list, $timestamp ): array {
    return Disciple_Tools_Mailchimp_API::get_list_members_since_last_changed( $list, $timestamp );
}

function is_dt_record_sync_enabled( $dt_post ): bool {
    return ( isset( $dt_post['disciple_tools_mailchimp_supported_lists_multiselect'] ) && ( count( $dt_post['disciple_tools_mailchimp_supported_lists_multiselect'] ) > 0 ) );
}

function has_matching_post_type_field_mappings( $post_type_id, $mappings ): bool {
    $all_match = true;
    foreach ( $mappings as $mapping ) {
        if ( substr( $mapping->dt_field_id, 0, strlen( $post_type_id . '_' ) ) !== $post_type_id . '_' ) {
            $all_match = false;
        }
    }

    return $all_match;
}

function identify_mapping_post_types( $mappings ): array {
    $post_types = [];
    foreach ( $mappings as $mapping ) {
        $post_types[] = explode( '_', $mapping->dt_field_id, 2 )[0];
    }

    return array_unique( $post_types );
}

function extract_field_mappings( $post_type_id, $mappings ): array {
    $field_mappings = array();
    foreach ( $mappings as $mapping ) {
        $field_mappings[] = (object) [
            'mc_field_id' => $mapping->mc_field_id,
            'dt_field_id' => substr( $mapping->dt_field_id, strlen( $post_type_id . '_' ) ),
            'options'     => $mapping->options
        ];
    }

    return $field_mappings;
}

function fetch_mc_record( $dt_post_record, $mc_list_id ) {
    // 1st - If present, search by email address
    $mc_record = fetch_mc_record_by_email( $dt_post_record, $mc_list_id );

    // 2nd - Failing 1st; search for dt record id within hidden mc fields
    if ( empty( $mc_record ) ) {
        $mc_record = fetch_mc_record_by_hidden_fields( $dt_post_record, $mc_list_id );
    }

    return $mc_record;
}

function fetch_mc_record_by_email( $dt_post_record, $mc_list_id ) {
    $email = extract_dt_record_email( $dt_post_record );
    if ( isset( $email ) ) {
        // Search Mailchimp for a corresponding record, based on email address
        return Disciple_Tools_Mailchimp_API::find_list_member_by_email( $mc_list_id, $email );
    }

    return null;
}

function fetch_mc_record_by_hidden_fields( $dt_post_record, $mc_list_id ) {
    return Disciple_Tools_Mailchimp_API::find_list_member_by_hidden_id_fields( $mc_list_id, $dt_post_record['ID'] );
}

function create_mc_record( $dt_post_record, $mc_list_id ) {
    // At the very least, an email address is required in order to create a new mc record
    $email = extract_dt_record_email( $dt_post_record );
    if ( isset( $email ) ) {
        $new_mc_record = [
            'email_address' => $email,
            'status'        => 'subscribed'
        ];

        // If target mc list already contains hidden fields, then also include post id.
        if ( Disciple_Tools_Mailchimp_API::has_list_got_hidden_id_fields( $mc_list_id ) ) {
            $new_mc_record['merge_fields'] = [
                Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field() => $dt_post_record['ID']
            ];
        }

        // Finally, create new list member
        return Disciple_Tools_Mailchimp_API::add_new_list_member( $mc_list_id, $new_mc_record );
    }

    return null;
}

function extract_dt_record_email( $dt_post_record ) {
    // First, determine dt record's post type settings
    $post_type_settings = DT_Posts::get_post_settings( $dt_post_record['post_type'] );

    // Next, determine if current record contains an email field and subsequent address
    $email_field_name = strtolower( $post_type_settings['label_singular'] . '_email' );
    if ( isset( $dt_post_record[ $email_field_name ] ) ) {
        // Value found at idx 0 to be treated as primary email
        return $dt_post_record[ $email_field_name ][0]['value'];
    }

    return null;
}

function dt_record_has_latest_changes( $dt_record, $mc_record ): bool {
    return intval( $dt_record['last_modified']['timestamp'] ) > strtotime( $mc_record->last_changed );
}

function update_mc_record( $mc_list_id, $dt_record, $mc_record, $mappings ) {
    // First, determine dt record's post type settings
    $post_type_settings = DT_Posts::get_post_settings( $dt_record['post_type'] );

    // Next, loop over all mapped fields; updating accordingly based on specified options
    $updated_fields = [];
    foreach ( $mappings as $mapping ) {

        // Determine dt field name - Ensure post type prefix is assigned accordingly
        $dt_field_name_no_prefix   = strtolower( $mapping->dt_field_id );
        $dt_field_name_with_prefix = strtolower( $post_type_settings['label_singular'] . '_' . $mapping->dt_field_id );
        $dt_field                  = $dt_record[ $dt_field_name_no_prefix ] ?? $dt_record[ $dt_field_name_with_prefix ];

        // Distinguish between different field shapes; e.g. arrays, strings...
        // If array, default value will be taken from the first element
        if ( ! empty( $dt_field ) ) {
            $dt_field_value = is_array( $dt_field ) ? $dt_field[0]['value'] : $dt_field;

            // todo: apply mapping transformation options prior to setting updated dt value

            // Add updated value
            $updated_fields[ $mapping->mc_field_id ] = $dt_field_value;
        }
    }

    // Package updated values, ahead of final push
    $updated_mc_record                 = [];
    $updated_mc_record['merge_fields'] = $updated_fields;

    // Finally, post update request
    return Disciple_Tools_Mailchimp_API::update_list_member( $mc_list_id, $mc_record->id, $updated_mc_record );
}

function update_dt_record( $dt_record, $mc_record, $mappings ) {
    // First, determine dt record's post type settings
    $post_type_settings = DT_Posts::get_post_settings( $dt_record['post_type'] );

    // Next, iterate over mapped fields
    $updated_fields = [];
    foreach ( $mappings as $mapping ) {

        // Now ensure dt record also contains corresponding mapped field
        $dt_field_name_no_prefix   = strtolower( $mapping->dt_field_id );
        $dt_field_name_with_prefix = strtolower( $post_type_settings['label_singular'] . '_' . $mapping->dt_field_id );
        $dt_field_name             = '';

        // Determine actual field name
        if ( isset( $dt_record[ $dt_field_name_no_prefix ] ) ) {
            $dt_field_name = $dt_field_name_no_prefix;
        } elseif ( isset( $dt_record[ $dt_field_name_with_prefix ] ) ) {
            $dt_field_name = $dt_field_name_with_prefix;
        }

        // todo: handle cases when field is yet to be created within dt record - need to determine when prefix should be added - question for @corsac

        // Only proceed if a valid dt field name has been identified
        if ( ! empty( $dt_field_name ) ) {

            // Extract values and apply any detected mapping option functions
            // Ensure to accommodate emails; which are not specified un 'merge_fields'
            $mc_field_value = ( $mapping->mc_field_id === 'EMAIL' ) ? $mc_record->email_address : $mc_record->merge_fields->{$mapping->mc_field_id};

            if ( ! empty( $mc_field_value ) ) {

                // todo: apply mapping transformation options prior to setting updated mc value

                // Add updated value
                if ( is_array( $dt_record[ $dt_field_name ] ) ) {
                    $updated_fields[ $dt_field_name ][0] = [
                        'value' => $mc_field_value,
                        'key'   => $dt_record[ $dt_field_name ][0]['key']
                    ];

                } else {
                    $updated_fields[ $dt_field_name ] = $mc_field_value;
                }
            }
        }
    }

    // Update dt record accordingly; assuming we have valid mapped field updates
    if ( count( $updated_fields ) > 0 ) {
        return DT_Posts::update_post( $dt_record['post_type'], $dt_record['ID'], $updated_fields, false, false );
    } else {
        return null;
    }
}
