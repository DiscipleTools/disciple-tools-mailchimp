<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Core synchronisation logic.
 */

add_action( Disciple_Tools_Mailchimp_API::$schedule_cron_event_hook, 'dt_mailchimp_sync_run' );
function dt_mailchimp_sync_run() {

    // DT -> MC Sync
    sync_dt_to_mc();

    // DT <- MC Sync
    sync_mc_to_dt();

    // Age Stale Logs
    dt_mailchimp_logging_aged();
}

function sync_dt_to_mc() {
    if ( is_sync_enabled( 'dt_mailchimp_dt_push_sync' ) ) {

        // Get started...!
        dt_mailchimp_logging_add( '[STARTED] - DT -> MC' );

        // Determine global last run timestamp
        $last_run = fetch_global_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc' );

        // Adjust run start sliding-window, to capture any stragglers, since last run
        $last_run_start_window = adjust_last_run_start_window( $last_run, 1 ); // 1Hr prior to last run
        dt_mailchimp_logging_add( 'DT record search starting point: ' . dt_format_date( $last_run_start_window, 'long' ) );

        // Fetch supported mc lists
        $supported_mc_lists = fetch_supported_array( 'dt_mailchimp_mc_supported_lists' );
        dt_mailchimp_logging_add( 'Supported MC list count: ' . count( $supported_mc_lists ) );

        // Fetch mailchimp list interest category groups
        $mc_list_interest_categories = fetch_mc_list_interest_categories( $supported_mc_lists );

        // Fetch field mappings
        $supported_mappings = fetch_supported_mappings();

        // Loop over supported dt post types
        $supported_dt_post_types = fetch_supported_array( 'dt_mailchimp_dt_supported_post_types' );
        foreach ( $supported_dt_post_types as $dt_post_type_id ) {

            // Query dt for changed/new records
            $latest_dt_records = fetch_latest_dt_records( $dt_post_type_id, $last_run_start_window );
            dt_mailchimp_logging_add( 'Latest DT records count: ' . count( $latest_dt_records ) );

            // Loop over latest dt post ids
            $latest_dt_records_counter = 0;
            foreach ( $latest_dt_records as $dt_post_id ) {

                try {
                    dt_mailchimp_logging_add( 'Processing DT record: ' . $dt_post_id->ID . ' [' . $dt_post_id->last_modified . '] from post type: ' . $dt_post_type_id . ' - [' . ++ $latest_dt_records_counter . ' of ' . count( $latest_dt_records ) . ']' );

                    // Introduce sliding-window concept and adjust global sync run timestamp based on current post's last modified date
                    update_global_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc', ( $dt_post_id->last_modified + 3600 ) );

                    // Fetch corresponding post record
                    $dt_post_record = DT_Posts::get_post( $dt_post_type_id, $dt_post_id->ID, false, false );

                    // Ensure dt record is to be kept in sync
                    if ( is_dt_record_sync_enabled( $dt_post_record ) ) {

                        // Iterate over subscribed mc lists and find associated mapping; assuming list is available and supported
                        foreach ( $dt_post_record['dt_mailchimp_subscribed_mc_lists'] as $subscribed_mc_list_id ) {

                            dt_mailchimp_logging_add( 'Processing subscribed MC list id: ' . $subscribed_mc_list_id );

                            // Ensure mc list is supported and has mapping
                            if ( in_array( $subscribed_mc_list_id, $supported_mc_lists ) && isset( $supported_mappings->$subscribed_mc_list_id ) ) {

                                // Ensure mappings post type corresponds with current post type id; so as to avoid cross post type syncs
                                if ( $dt_post_type_id === $supported_mappings->$subscribed_mc_list_id->dt_post_type ) {

                                    // Extract array of mapped fields; which are to be kept in sync
                                    $field_mappings = $supported_mappings->$subscribed_mc_list_id->mappings;
                                    dt_mailchimp_logging_add( 'Field mappings count: ' . count( $field_mappings ) );

                                    // First, attempt to fetch corresponding mc record, using the info to hand!
                                    $mc_record = fetch_mc_record( $dt_post_record, $subscribed_mc_list_id );

                                    // If still no hit, then a new mc record will be created
                                    $is_new_mc_record = false;
                                    if ( empty( $mc_record ) ) {
                                        $mc_record        = create_mc_record( $dt_post_record, $subscribed_mc_list_id );
                                        $is_new_mc_record = true;
                                    }

                                    dt_mailchimp_logging_add( 'New MC record: ' . ( ( $is_new_mc_record === true ) ? 'YES' : 'NO' ) );
                                    dt_mailchimp_logging_add( 'MC record still null: ' . ( ( empty( $mc_record ) === true ) ? 'YES' : 'NO' ) );

                                    // Only proceed if we have a handle on corresponding mc record
                                    if ( ! empty( $mc_record ) ) {

                                        dt_mailchimp_logging_add( 'Linked with MC record: ' . $mc_record->email_address );

                                        // Only proceed with mc record update, if it's in a subscribed state!
                                        if ( isset( $mc_record->status ) && strtolower( $mc_record->status ) === 'subscribed' ) {

                                            // Apart from a newly created mc record; which will default to current mapped fields
                                            // ensure dt has the most recent modifications of the two records, in order to update
                                            if ( $is_new_mc_record || dt_record_has_latest_changes( $dt_post_record, $mc_record ) ) {

                                                // Update mc record
                                                dt_mailchimp_logging_add( 'Attempting to update MC record: ' . $mc_record->email_address );
                                                $logs    = dt_mailchimp_logging_load();
                                                $updated = update_mc_record( $subscribed_mc_list_id, $dt_post_record, $mc_record, $field_mappings, $mc_list_interest_categories, $logs );
                                                dt_mailchimp_logging_update( $logs );

                                                // Update last run timestamps, assuming we have valid updates
                                                if ( ! empty( $updated ) ) {
                                                    update_list_option_value( $subscribed_mc_list_id, 'dt_to_mc_last_sync_run', time() );
                                                    update_list_option_value( $subscribed_mc_list_id, 'log', '' );
                                                    sync_debug( 'dt_mailchimp_dt_debug', '' );
                                                    dt_mailchimp_logging_add( 'Updated MC record: ' . $updated->email_address );

                                                } else {
                                                    dt_mailchimp_logging_add( 'MC record [' . $mc_record->email_address . '] not updated!' );
                                                }
                                            } else {
                                                dt_mailchimp_logging_add( 'DT record does not have latest changes! No update to be performed!' );
                                            }
                                        } else {
                                            dt_mailchimp_logging_add( 'MC record [' . $mc_record->email_address . '] not updated, as not in a subscribed state! Current status is - ' . $mc_record->status );
                                        }
                                    } else {
                                        sync_debug( 'dt_mailchimp_dt_debug', 'Unable to locate/create a valid mc list ' . $subscribed_mc_list_id . ' record, based on ' . $dt_post_type_id . ' dt record [id:' . $dt_post_record['ID'] . '].' );
                                        update_list_option_value( $subscribed_mc_list_id, 'log', 'Unable to locate/create a valid mc record, based on ' . $dt_post_type_id . ' dt record [id:' . $dt_post_record['ID'] . '].' );
                                        dt_mailchimp_logging_add( 'Unable to locate/create a valid mc list ' . $subscribed_mc_list_id . ' record, based on ' . $dt_post_type_id . ' dt record [id:' . $dt_post_record['ID'] . '].' );
                                    }
                                } else {
                                    dt_mailchimp_logging_add( 'DT post type [' . $dt_post_type_id . '] and mappings post type [' . $supported_mappings->$subscribed_mc_list_id->dt_post_type . '] mismatch!' );
                                }
                            } else {
                                dt_mailchimp_logging_add( 'Subscribed MC list [' . $subscribed_mc_list_id . '] not supported and no mappings detected!' );
                            }
                        }
                    } else {
                        dt_mailchimp_logging_add( 'DT record sync flag disabled!' );
                    }
                } catch ( Exception $exception ) {
                    dt_mailchimp_logging_add( 'Exception: ' . $exception->getMessage() );
                }
            }
        }

        // Update global sync run timestamp and logs
        update_global_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc', time() );
        dt_mailchimp_logging_add( '[FINISHED] - DT -> MC' );
    }
}

function sync_mc_to_dt() {
    if ( is_sync_enabled( 'dt_mailchimp_mc_accept_sync' ) ) {

        // Get started...!
        dt_mailchimp_logging_add( '[STARTED] - MC -> DT' );

        // Determine global last run timestamp
        $last_run = fetch_global_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt' );

        // Adjust run start sliding-window, so as to capture any stragglers, since last run
        $last_run_start_window = adjust_last_run_start_window( $last_run, 1 ); // 1Hr prior to last run
        dt_mailchimp_logging_add( 'MC record search starting point: ' . dt_format_date( $last_run_start_window, 'long' ) );

        // Fetch supported mc lists
        $supported_mc_lists = fetch_supported_array( 'dt_mailchimp_mc_supported_lists' );
        dt_mailchimp_logging_add( 'Supported MC list count: ' . count( $supported_mc_lists ) );

        // Fetch mailchimp list interest category groups
        $mc_list_interest_categories = fetch_mc_list_interest_categories( $supported_mc_lists );

        // Fetch field mappings
        $supported_mappings = fetch_supported_mappings();

        // Loop over supported mc lists
        foreach ( $supported_mc_lists as $mc_list_id ) {

            // Query mc for changed/new member records
            $latest_mc_records = fetch_latest_mc_records( $mc_list_id, $last_run_start_window );
            dt_mailchimp_logging_add( 'Latest MC records count: ' . count( $latest_mc_records ) );

            // Loop over latest mc member records
            $latest_mc_records_counter = 0;
            foreach ( $latest_mc_records as $mc_record ) {

                try {
                    dt_mailchimp_logging_add( 'Processing MC record: ' . $mc_record->email_address . ' [' . strtotime( $mc_record->last_changed ) . '] from list: ' . $mc_record->list_id . ' - [ ' . ++ $latest_mc_records_counter . ' of ' . count( $latest_mc_records ) . ' ]' );

                    // Introduce sliding-window concept and adjust global sync run timestamp based on current record's last changed date
                    update_global_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt', ( strtotime( $mc_record->last_changed ) + 3600 ) );

                    // Ensure record's assigned mc list is supported and has mapping
                    if ( in_array( $mc_record->list_id, $supported_mc_lists ) && isset( $supported_mappings->{$mc_record->list_id} ) ) {

                        $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();
                        dt_mailchimp_logging_add( ( isset( $mc_record->merge_fields->{$hidden_id_field_tag} ) && ! empty( $mc_record->merge_fields->{$hidden_id_field_tag} ) ) ? 'Detected Hidden ID: ' . $mc_record->merge_fields->{$hidden_id_field_tag} : 'Detected Hidden ID: ---' );

                        $dt_post_type_id = $supported_mappings->{$mc_record->list_id}->dt_post_type;
                        dt_mailchimp_logging_add( 'Mapped DT post type: ' . $dt_post_type_id );

                        // First, attempt to fetch corresponding dt record, using the info to hand!
                        $fetch_results  = fetch_dt_record( $mc_record, $dt_post_type_id );
                        $dt_record      = $fetch_results['record'];
                        $already_linked = $fetch_results['linked'];

                        // If still no hit, then a new dt record will be created
                        $is_new_dt_record = false;
                        if ( empty( $dt_record ) ) {
                            $dt_record        = create_dt_record( $mc_record, $dt_post_type_id );
                            $is_new_dt_record = true;
                        }

                        dt_mailchimp_logging_add( 'New DT record: ' . ( ( $is_new_dt_record === true ) ? 'YES' : 'NO' ) );
                        dt_mailchimp_logging_add( 'Already linked: ' . ( ( $already_linked === true ) ? 'YES' : 'NO' ) );
                        dt_mailchimp_logging_add( 'DT record still null: ' . ( ( empty( $dt_record ) === true ) ? 'YES' : 'NO' ) );

                        // Handle dt record subscription status - Ensure it remains in sync with mc record
                        $logs                     = dt_mailchimp_logging_load();
                        $subscribe_update_results = handle_dt_record_subscription( $dt_record, $mc_record, $logs );
                        dt_mailchimp_logging_update( $logs );
                        $dt_record = $subscribe_update_results['dt_record'];

                        // Only proceed if we have a handle on corresponding dt record
                        if ( ! empty( $dt_record ) && ( ! $already_linked || ( is_dt_record_sync_enabled( $dt_record ) && in_array( $mc_record->list_id, $dt_record['dt_mailchimp_subscribed_mc_lists'] ) ) ) ) {

                            dt_mailchimp_logging_add( 'Linked with DT record: ' . $dt_record['ID'] );

                            // Ensure mc record has the latest changes, in order to update dt
                            if ( $is_new_dt_record || ! $already_linked || ! dt_record_has_latest_changes( $dt_record, $mc_record ) ) {

                                // Extract array of mapped fields; which are to be kept in sync
                                $field_mappings = $supported_mappings->{$mc_record->list_id}->mappings;
                                dt_mailchimp_logging_add( 'Field mappings count: ' . count( $field_mappings ) );

                                // Update dt record
                                dt_mailchimp_logging_add( 'Attempting to update DT record: ' . $dt_record['ID'] );
                                $logs    = dt_mailchimp_logging_load();
                                $updated = update_dt_record( $dt_record, $mc_record, $field_mappings, $mc_list_interest_categories, $logs );
                                dt_mailchimp_logging_update( $logs );

                                // Update last run timestamps, assuming we have valid updates
                                if ( ! empty( $updated ) && ! is_wp_error( $updated ) ) {
                                    update_list_option_value( $mc_record->list_id, 'mc_to_dt_last_sync_run', time() );
                                    update_list_option_value( $mc_record->list_id, 'log', '' );
                                    sync_debug( 'dt_mailchimp_mc_debug', '' );
                                    dt_mailchimp_logging_add( 'Updated DT record: ' . $updated['ID'] );

                                } else {
                                    dt_mailchimp_logging_add( 'DT record [' . $dt_record['ID'] . '] not updated!' );
                                }
                            } else {
                                dt_mailchimp_logging_add( 'MC record does not have latest changes! No update to be performed!' );
                            }
                        } elseif ( empty( $dt_record ) ) {
                            sync_debug( 'dt_mailchimp_mc_debug', 'Unable to locate/create a valid ' . $dt_post_type_id . ' dt record for mc list ' . $mc_record->list_id . ' record [' . $mc_record->email_address . ']' );
                            update_list_option_value( $mc_record->list_id, 'log', 'Unable to locate/create a valid ' . $dt_post_type_id . ' dt record for mc record [' . $mc_record->email_address . ']' );
                            dt_mailchimp_logging_add( 'Unable to locate/create a valid ' . $dt_post_type_id . ' dt record for mc list ' . $mc_record->list_id . ' record [' . $mc_record->email_address . ']' );

                        } elseif ( $subscribe_update_results['status_changed'] ) {
                            update_list_option_value( $mc_record->list_id, 'mc_to_dt_last_sync_run', time() );
                            update_list_option_value( $mc_record->list_id, 'log', '' );
                            sync_debug( 'dt_mailchimp_mc_debug', '' );
                            dt_mailchimp_logging_add( 'Updated DT record: ' . $dt_record['ID'] );

                        } else {
                            dt_mailchimp_logging_add( 'DT record [' . $dt_record['ID'] . '] not updated!' );
                        }
                    } else {
                        dt_mailchimp_logging_add( 'MC list id [' . $mc_record->list_id . '] not supported and no mappings detected!' );
                    }
                } catch ( Exception $exception ) {
                    dt_mailchimp_logging_add( 'Exception: ' . $exception->getMessage() );
                }
            }
        }

        // Update global sync run timestamp and logs
        update_global_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt', time() );
        dt_mailchimp_logging_add( '[FINISHED] - MC -> DT' );
    }
}

function dt_mailchimp_logging_load(): array {
    return ! empty( get_option( 'dt_mailchimp_logging' ) ) ? json_decode( get_option( 'dt_mailchimp_logging' ) ) : [];
}

function dt_mailchimp_logging_create( $msg ) {
    return (object) [
        'timestamp' => time(),
        'log'       => $msg
    ];
}

function dt_mailchimp_logging_update( $logs ) {
    update_option( 'dt_mailchimp_logging', json_encode( $logs ) );
}

function dt_mailchimp_logging_add( $log ) {
    $logs   = ! empty( get_option( 'dt_mailchimp_logging' ) ) ? json_decode( get_option( 'dt_mailchimp_logging' ) ) : [];
    $logs[] = dt_mailchimp_logging_create( $log );
    update_option( 'dt_mailchimp_logging', json_encode( $logs ) );
}

function dt_mailchimp_logging_aged() {
    // Remove entries older than specified aged period!
    $logs = dt_mailchimp_logging_load();
    if ( ! empty( $logs ) ) {
        $cut_off_point_ts  = time() - ( 3600 * 1 ); // 1 hr ago!
        $cut_off_point_idx = 0;

        $count = count( $logs );
        for ( $x = 0; $x < $count; $x ++ ) {

            // Stale logs will typically be found at the start! Therefore, capture transition point!
            if ( $logs[ $x ]->timestamp > $cut_off_point_ts ) {
                $cut_off_point_idx = $x;
                $x                 = $count;
            }
        }

        // Age off any stale logs
        if ( $cut_off_point_idx > 0 ) {
            $stale_logs = array_splice( $logs, 0, $cut_off_point_idx );
            dt_mailchimp_logging_update( $logs );
        }
    }
}

function sync_debug( $option_name, $msg ) {
    update_option( $option_name, $msg );
}

function adjust_last_run_start_window( $last_run, $hrs ): int {
    return $last_run - ( 3600 * $hrs );
}

function is_sync_enabled( $option_name ): bool {
    $in_debug_mode = defined( WP_DEBUG ) && WP_DEBUG === true;
    return !$in_debug_mode && boolval( get_option( $option_name ) );
}

function fetch_supported_mappings() {
    return json_decode( get_option( 'dt_mailchimp_mappings' ) );
}

function fetch_assigned_user_id() {
    return get_option( 'dt_mailchimp_dt_new_record_assign_user_id' );
}

function update_global_last_run( $option_name, $timestamp ) {
    update_option( $option_name, $timestamp );
}

function fetch_global_last_run( $option_name ): int {
    $last_run = get_option( $option_name );

    // Cater for first run states; which should force the initial linking of records across both platforms;
    // ...From a really long time ago...! ;)
    return ! empty( $last_run ) ? intval( $last_run ) : 946684800; // Start: 1 January 2000 00:00:00
}

function update_list_option_value( $mc_list_id, $option_name, $value ) {

    // Only update if we have an existing entry
    $supported_lists = get_option( 'dt_mailchimp_mc_supported_lists' );
    if ( ! empty( $supported_lists ) ) {

        $lists = json_decode( $supported_lists );
        if ( isset( $lists->{$mc_list_id} ) ) {
            $lists->{$mc_list_id}->{$option_name} = $value;

            // Save updated list last run
            update_option( 'dt_mailchimp_mc_supported_lists', json_encode( $lists ) );
        }
    }
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

function fetch_mc_list_interest_categories( $supported_mc_lists ): array {
    $mc_list_categories = [];
    foreach ( $supported_mc_lists as $mc_list_id ) {
        $interest_categories = Disciple_Tools_Mailchimp_API::get_list_interest_categories( $mc_list_id, true );
        if ( ! empty( $interest_categories ) ) {
            $mc_list_categories[ $mc_list_id ] = $interest_categories;
        }
    }

    return $mc_list_categories;
}

function fetch_latest_dt_records( $post_type, $timestamp ): array {
    global $wpdb;

    return $wpdb->get_results( $wpdb->prepare( "
SELECT post.ID, meta.meta_value last_modified
FROM $wpdb->posts post
LEFT JOIN $wpdb->postmeta meta ON (post.ID = meta.post_id)
WHERE (post.post_type = %s)
  AND ((meta.meta_key = 'last_modified') AND (meta.meta_value > %d))
GROUP BY post.ID, meta.meta_value
ORDER BY meta.meta_value ASC", $post_type, $timestamp ) );
}

function fetch_latest_mc_records( $list, $timestamp ): array {
    return Disciple_Tools_Mailchimp_API::get_list_members_since_last_changed( $list, $timestamp );
}

function is_dt_record_sync_enabled( $dt_post ): bool {
    return ( isset( $dt_post['dt_mailchimp_subscribed_mc_lists'] ) && ( count( $dt_post['dt_mailchimp_subscribed_mc_lists'] ) > 0 ) );
}

function fetch_mc_record( $dt_post_record, $mc_list_id ) {

    // 1st - If present, search by all dt record's email addresses
    $mc_record = fetch_mc_record_by_email( $dt_post_record, $mc_list_id );

    // If we have a hit; ensure mc record's hidden id field is updated with dt record's post id
    if ( ! empty( $mc_record ) ) {
        $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();

        if ( strval( $dt_post_record['ID'] ) !== strval( $mc_record->merge_fields->{$hidden_id_field_tag} ) ) {

            $mc_fields = [];
            if ( Disciple_Tools_Mailchimp_API::has_list_got_hidden_id_fields( $mc_list_id ) ) {
                $mc_fields['merge_fields'] = [
                    $hidden_id_field_tag => $dt_post_record['ID']
                ];

                $mc_record->merge_fields->{$hidden_id_field_tag} = $dt_post_record['ID'];
                Disciple_Tools_Mailchimp_API::update_list_member( $mc_list_id, $mc_record->id, $mc_fields );
            }
        }
    }

    // 2nd - Failing 1st; search for dt record id within hidden mc fields
    if ( empty( $mc_record ) ) {
        $mc_record = fetch_mc_record_by_hidden_fields( $dt_post_record, $mc_list_id );
    }

    return $mc_record;
}

function fetch_mc_record_by_email( $dt_post_record, $mc_list_id, $first_record_only = true ) {
    $emails = extract_dt_record_emails( $dt_post_record, false );
    if ( isset( $emails ) && ! empty( $emails ) ) {

        $mc_records = [];
        // Search Mailchimp for a corresponding record, based on loop email address
        foreach ( $emails as $email ) {
            $mc_record = Disciple_Tools_Mailchimp_API::find_list_member_by_email( $mc_list_id, $email['value'] );
            if ( ! empty( $mc_record ) ) {
                if ( $first_record_only ) {
                    return $mc_record;
                } else {
                    $mc_records[] = $mc_record;
                }
            }
        }

        if ( count( $mc_records ) > 0 && ! $first_record_only ) {
            return $mc_records;
        }
    }

    return null;
}

function fetch_mc_record_by_hidden_fields( $dt_post_record, $mc_list_id ) {
    return Disciple_Tools_Mailchimp_API::find_list_member_by_hidden_id_fields( $mc_list_id, $dt_post_record['ID'] );
}

function fetch_dt_record( $mc_record, $dt_post_type_id ): array {

    $dt_record      = null;
    $already_linked = false;

    // 1st - If present, search for dt record using mc record's hidden post id
    $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();
    if ( isset( $mc_record->merge_fields->{$hidden_id_field_tag} ) && ! empty( $mc_record->merge_fields->{$hidden_id_field_tag} ) ) {
        $dt_post_id = intval( $mc_record->merge_fields->{$hidden_id_field_tag} );
        $hit        = DT_Posts::get_post( $dt_post_type_id, $dt_post_id, false, false );

        if ( ! empty( $hit ) && ! is_wp_error( $hit ) ) {
            $dt_record      = $hit;
            $already_linked = true;
        }
    }

    // 2nd - Failing 1st, search for dt record using mc record's email address
    if ( empty( $dt_record ) ) {
        $dt_record = fetch_dt_record_by_email( $dt_post_type_id, $mc_record->email_address );

        // If we have a hit; ensure mc record's hidden id field is updated with dt record's post id
        if ( ! empty( $dt_record ) ) {
            $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();

            if ( strval( $dt_record['ID'] ) !== strval( $mc_record->merge_fields->{$hidden_id_field_tag} ) ) {

                $mc_fields = [];
                if ( Disciple_Tools_Mailchimp_API::has_list_got_hidden_id_fields( $mc_record->list_id ) ) {
                    $mc_fields['merge_fields'] = [
                        $hidden_id_field_tag => $dt_record['ID']
                    ];

                    $mc_record->merge_fields->{$hidden_id_field_tag} = $dt_record['ID'];
                    Disciple_Tools_Mailchimp_API::update_list_member( $mc_record->list_id, $mc_record->id, $mc_fields );
                }
            }
        }
    }

    return [
        'record' => $dt_record,
        'linked' => $already_linked
    ];
}

function fetch_dt_record_by_email( $dt_post_type_id, $email ) {
    global $wpdb;

    $dt_post_ids = $wpdb->get_results( $wpdb->prepare( "
    SELECT post_id
    FROM $wpdb->postmeta
    WHERE (meta_key LIKE %s) AND (meta_value = %s)
    GROUP BY post_id", 'contact_email%', $email ) );

    if ( ! empty( $dt_post_ids ) && count( $dt_post_ids ) > 0 ) {

        $dt_posts = [];
        foreach ( $dt_post_ids as $dt_post_id ) {
            $hit = DT_Posts::get_post( $dt_post_type_id, $dt_post_id->post_id, false, false );

            if ( ! empty( $hit ) && ! is_wp_error( $hit ) && ( strtolower( $hit['type']['key'] ) === 'access' ) ) {
                $dt_posts[] = $hit;
            }
        }

        // Assuming we have some hits, sort and return the most recently updated record
        if ( count( $dt_posts ) > 0 ) {
            usort( $dt_posts, function ( $a, $b ) {
                return intval( $a['last_modified']['timestamp'] ) - intval( $b['last_modified']['timestamp'] );
            } );

            // Once sorted, return most recent..!
            return $dt_posts[0];
        }
    }

    return null;
}

function create_mc_record( $dt_post_record, $mc_list_id ) {
    // At the very least, an email address is required in order to create a new mc record
    $email = extract_dt_record_emails( $dt_post_record, true );
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

function create_dt_record( $mc_record, $dt_post_type ) {
    // At the very least, both mc record's full name and list id will be needed in order to setup new dt record
    $mc_record_full_name = $mc_record->full_name;
    $mc_record_list_id   = $mc_record->list_id;

    if ( ! empty( $mc_record_full_name ) && ! empty( $mc_record_list_id ) ) {

        // Prepare initial dt fields
        $dt_fields = [];

        $dt_fields['type']                = 'access';
        $dt_fields['sources']['values'][] = [
            'value' => 'mailchimp'
        ];

        $dt_fields['name']                                          = $mc_record_full_name;
        $dt_fields['dt_mailchimp_subscribed_mc_lists']['values'][0] = [
            'value' => $mc_record_list_id
        ];

        // If available, assign new dt record to specified user
        $assigned_user_id = fetch_assigned_user_id();
        if ( ! empty( $assigned_user_id ) ) {
            $dt_fields['assigned_to'] = $assigned_user_id;
        }

        // Create new dt post
        $dt_record = DT_Posts::create_post( $dt_post_type, $dt_fields, false, false );
        sync_debug( 'dt_mailchimp_mc_debug', $dt_record );
        if ( ! empty( $dt_record ) && isset( $dt_record['ID'] ) ) {

            // Update parent mc record's hidden post id field
            $mc_fields = [];
            if ( Disciple_Tools_Mailchimp_API::has_list_got_hidden_id_fields( $mc_record_list_id ) ) {
                $mc_fields['merge_fields'] = [
                    Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field() => $dt_record['ID']
                ];

                // Return dt record state accordingly
                $updated_mc_record = Disciple_Tools_Mailchimp_API::update_list_member( $mc_record_list_id, $mc_record->id, $mc_fields );

                return ! empty( $updated_mc_record ) ? $dt_record : null;
            }
        }
    }

    return null;
}

function extract_dt_record_emails( $dt_post_record, $first_email_only ) {

    if ( isset( $dt_post_record['contact_email'] ) && ! empty( $dt_post_record['contact_email'] ) ) {
        if ( $first_email_only ) {
            return $dt_post_record['contact_email'][0]['value'];
        } else {
            return $dt_post_record['contact_email'];
        }
    }

    return null;
}

function dt_record_has_latest_changes( $dt_record, $mc_record ): bool {
    return intval( $dt_record['last_modified']['timestamp'] ) > strtotime( $mc_record->last_changed );
}

function apply_mapping_options( $dt_record, $mc_record, $value, $is_dt_to_mc_sync, $options ) {

    // Package value into an applied option value object; which can accommodate additional metadata
    $applied_option_value = (object) [
        'continue' => true,
        'value'    => $value
    ];

    // Just return and continue if no options are detected
    if ( ! ( count( $options ) > 0 ) ) {
        return $applied_option_value;
    }

    // Assuming options have been specified, only work with enabled options
    $enabled_options = array_filter( $options, function ( $option ) {
        return boolval( $option->enabled );
    } );

    // Now, sort by option priority; which will also double up as order of mapping option filter execution
    usort( $enabled_options, function ( $a, $b ) {
        return intval( $a->priority ) - intval( $b->priority );
    } );

    // Loop over sorted options, filtering accordingly
    foreach ( $enabled_options as $option ) {
        $applied_option_value = apply_filters( $option->id, $dt_record, $mc_record, $applied_option_value, $is_dt_to_mc_sync, $option );
    }

    // Return value following application of mapping option filters
    return $applied_option_value;
}

add_filter( 'field-sync-direction', 'mapping_option_field_sync_direction_callback', 10, 5 );
function mapping_option_field_sync_direction_callback( $dt_record, $mc_record, $value, $is_dt_to_mc_sync, $option ) {

    // If disabled or no longer allowed to continue, just echo value back...
    if ( ( ! boolval( $option->enabled ) ) || ( ! $value->continue ) ) {
        return $value;
    }

    // Proceed with evaluation...
    if ( $is_dt_to_mc_sync && boolval( $option->dt_sync_feeds ) ) {
        $value->continue = true;

    } elseif ( ! $is_dt_to_mc_sync && boolval( $option->mc_sync_feeds ) ) {
        $value->continue = true;

    } else {
        $value->continue = false;
    }

    return $value;
}

function fetch_mc_list_category_interests( $mc_list_id, $cat_id, $mc_list_interest_categories ): array {
    $interests = [];
    if ( isset( $mc_list_interest_categories[ $mc_list_id ] ) ) {
        foreach ( $mc_list_interest_categories[ $mc_list_id ] as $category ) {
            if ( $category->cat_id === $cat_id && isset( $category->cat_interests ) && ! empty( $category->cat_interests ) ) {
                foreach ( $category->cat_interests as $interest ) {
                    $interests[] = (object) [
                        "int_id"       => $interest->int_id,
                        "int_name"     => $interest->int_name,
                        "int_selected" => false // Default state!
                    ];
                }
            }
        }
    }

    return $interests;
}

function update_mc_record( $mc_list_id, $dt_record, $mc_record, $mappings, $mc_list_interest_categories, &$logs ) {

    $updated_merge_fields       = [];
    $updated_interests          = [];
    $prefix_interest_categories = Disciple_Tools_Mailchimp_API::get_list_interest_categories_field_prefix();
    $dt_fields                  = DT_Posts::get_post_settings( $dt_record['post_type'] )['fields'];

    // Loop over all mapped fields; updating accordingly based on specified options
    foreach ( $mappings as $mapping ) {

        // Distinguish between different field shapes; e.g. arrays, strings...
        // Historically, ff array, default value will be taken from the first element.
        // However, since the introduction of mc list interest category support; multi_select fields are expected!
        $dt_field = $dt_record[ $mapping->dt_field_id ] ?? null;
        if ( ! empty( $dt_field ) ) {

            // INTEREST CATEGORY GROUP UPDATES
            if ( substr( $mapping->mc_field_id, 0, strlen( $prefix_interest_categories ) ) === $prefix_interest_categories ) {

                // Is the corresponding dt field of type multi_select?
                if ( isset( $dt_fields[ $mapping->dt_field_id ] ) && strtolower( $dt_fields[ $mapping->dt_field_id ]['type'] ) === 'multi_select' ) {

                    // Fetch all associated category interests; which are set to a false selected state by default!
                    $category_id = substr( $mapping->mc_field_id, strlen( $prefix_interest_categories ) );
                    $interests   = fetch_mc_list_category_interests( $mc_list_id, $category_id, $mc_list_interest_categories );

                    // Assuming we have interests, build list of selected labels.
                    if ( ! empty( $interests ) ) {

                        // First, obtain list of selected labels.
                        $selected_labels = [];
                        if ( is_array( $dt_field ) ) {
                            foreach ( $dt_field as $label_key ) {
                                if ( isset( $dt_fields[ $mapping->dt_field_id ]['default'][ $label_key ] ) ) {
                                    $selected_labels[] = $dt_fields[ $mapping->dt_field_id ]['default'][ $label_key ]['label'];
                                }
                            }
                        }

                        // Enable/Select corresponding interests based on selected labels.
                        foreach ( $interests as &$interest ) {
                            $interest->int_selected = in_array( $interest->int_name, $selected_labels );
                        }

                        // Highly unlikely for this mapping type, but still provide provision for any transformation options.
                        $applied_mapping_options = apply_mapping_options( $dt_record, $mc_record, $interests, true, $mapping->options );

                        // Assuming we still have a 'go'; then re-package interests ahead of final update.
                        if ( $applied_mapping_options->continue && is_array( $applied_mapping_options->value ) ) {

                            // Safeguard against potential infinite update loops
                            if ( ! matching_mc_field_category_interests( $mc_record, $applied_mapping_options->value ) ) {
                                foreach ( $applied_mapping_options->value as $value ) { // Should be an untouched category interest
                                    $updated_interests[ $value->int_id ] = $value->int_selected;
                                }
                            } else {
                                $logs[] = dt_mailchimp_logging_create( 'No mapped MC field [' . $mapping->mc_field_id . '] category interests value changes detected!' );
                            }
                        } else {
                            $logs[] = dt_mailchimp_logging_create( 'Mapped MC field [' . $mapping->mc_field_id . '] sync update canceled, following options call!' );
                        }
                    }
                }
            } else {

                // MERGE FIELD UPDATES

                // Extract value accordingly based on field shape
                $dt_field_value = null;
                if ( is_array( $dt_field ) ) {
                    $dt_field_value = $dt_field[0]['value'] ?? $dt_field['key'];

                } else {
                    $dt_field_value = $dt_field;
                }

                // Apply mapping transformation options prior to setting updated dt value
                $applied_mapping_options = apply_mapping_options( $dt_record, $mc_record, $dt_field_value, true, $mapping->options );

                // Add updated value, assuming we have the green light to do so, following filtering of mapping options
                if ( $applied_mapping_options->continue ) {

                    // If required, switch back to label; which is what MC seems to operate in; especially when dealing with field types such as dropdowns!
                    $applied_mapping_options->value = fetch_dt_array_default_field_label( $dt_fields, $mapping->dt_field_id, $applied_mapping_options->value );

                    // Safeguard against potential infinite update loops
                    if ( ! matching_mc_field_value( $dt_record, $mc_record, $mapping->mc_field_id, $applied_mapping_options->value ) ) {
                        // If required, switch back to label; which is what MC seems to operate in; especially when dealing with field types such as dropdowns!
                        $updated_merge_fields[ $mapping->mc_field_id ] = $applied_mapping_options->value;
                    } else {
                        $logs[] = dt_mailchimp_logging_create( 'No mapped MC field [' . $mapping->mc_field_id . '] value changes detected!' );
                    }
                } else {
                    $logs[] = dt_mailchimp_logging_create( 'Mapped MC field [' . $mapping->mc_field_id . '] sync update canceled, following options call!' );
                }
            }
        }
    }

    // Only proceed if we have something to say! ;)
    if ( ! empty( $updated_merge_fields ) || ! empty( $updated_interests ) ) {

        // Package updated values, ahead of final push
        $updated_mc_record = [];

        $updated_fields_count = 0;
        if ( ! empty( $updated_merge_fields ) ) {
            $updated_mc_record['merge_fields'] = $updated_merge_fields;
            $updated_fields_count              += count( $updated_merge_fields );
        }
        if ( ! empty( $updated_interests ) ) {
            $updated_mc_record['interests'] = $updated_interests;
            $updated_fields_count           += count( $updated_interests );
        }

        // Finally, post update request
        $logs[] = dt_mailchimp_logging_create( 'Mapped MC fields to be updated count: ' . $updated_fields_count );

        return Disciple_Tools_Mailchimp_API::update_list_member( $mc_list_id, $mc_record->id, $updated_mc_record );

    } else {
        return null;
    }
}

function update_dt_record( $dt_record, $mc_record, $mappings, $mc_list_interest_categories, &$logs ) {

    $prefix_interest_categories = Disciple_Tools_Mailchimp_API::get_list_interest_categories_field_prefix();

    // First, fetch dt post type field settings; which will be used further down stream
    $dt_fields = DT_Posts::get_post_settings( $dt_record['post_type'] )['fields'];

    // Iterate over mapped fields
    $updated_fields = [];
    foreach ( $mappings as $mapping ) {

        // Determine actual field name
        $dt_field_name = $mapping->dt_field_id;

        // Only proceed if a valid dt field name has been identified
        if ( ! empty( $dt_field_name ) ) {

            // INTEREST CATEGORY GROUP UPDATES
            if ( substr( $mapping->mc_field_id, 0, strlen( $prefix_interest_categories ) ) === $prefix_interest_categories ) {

                // Is the corresponding dt field of type multi_select?
                if ( isset( $dt_fields[ $dt_field_name ] ) && strtolower( $dt_fields[ $dt_field_name ]['type'] ) === 'multi_select' ) {

                    // Does the current mc record have any interests worth exploring?!
                    if ( isset( $mc_record->interests ) && ! empty( $mc_record->interests ) ) {

                        // Fetch all associated category interests; which are set to a false selected state by default!
                        $category_id        = substr( $mapping->mc_field_id, strlen( $prefix_interest_categories ) );
                        $category_interests = fetch_mc_list_category_interests( $mc_record->list_id, $category_id, $mc_list_interest_categories );

                        // Assuming we have valid category interests, loop through and enable relevant interests.
                        if ( ! empty( $category_interests ) ) {
                            foreach ( $category_interests as &$interest ) {
                                $interest->int_selected = isset( $mc_record->interests->{$interest->int_id} ) && ! empty( $mc_record->interests->{$interest->int_id} ) ? $mc_record->interests->{$interest->int_id} : false;
                            }

                            // Highly unlikely for this mapping type, but still provide provision for any transformation options.
                            $applied_mapping_options = apply_mapping_options( $dt_record, $mc_record, $category_interests, false, $mapping->options );

                            // Assuming we still have a 'go'; then re-package interests ahead of final update.
                            if ( $applied_mapping_options->continue && is_array( $applied_mapping_options->value ) ) {

                                // Safeguard against potential infinite update loops
                                if ( ! matching_dt_field_category_interests( $dt_record, $dt_field_name, $dt_fields[ $dt_field_name ]['default'], $applied_mapping_options->value ) ) {
                                    foreach ( $applied_mapping_options->value as $value ) { // Should be an untouched category interest

                                        // As we link on labels, need to parse defaults for a match.
                                        foreach ( $dt_fields[ $dt_field_name ]['default'] as $key => $field ) {

                                            // On match, add update based on interest selected state.
                                            if ( $value->int_name === $field['label'] ) {
                                                $updated_fields[ $dt_field_name ]['values'][] = [
                                                    'value'  => $key,
                                                    'delete' => ! $value->int_selected
                                                ];
                                            }
                                        }
                                    }
                                } else {
                                    $logs[] = dt_mailchimp_logging_create( 'No mapped DT field [' . $dt_field_name . '] category interests value changes detected!' );
                                }
                            } else {
                                $logs[] = dt_mailchimp_logging_create( 'Mapped DT field [' . $dt_field_name . '] sync update canceled, following options call!' );
                            }
                        }
                    }
                }
            } else {

                // MERGE FIELD UPDATES

                // Extract values and apply any detected mapping option functions
                // Ensure to accommodate emails; which are not specified un 'merge_fields'
                $mc_field_value = ( $mapping->mc_field_id === 'EMAIL' ) ? $mc_record->email_address : $mc_record->merge_fields->{$mapping->mc_field_id};

                if ( ! empty( $mc_field_value ) ) {

                    // Apply mapping transformation options prior to setting updated mc value
                    $applied_mapping_options = apply_mapping_options( $dt_record, $mc_record, $mc_field_value, false, $mapping->options );

                    // Add updated value, assuming we have the green light to do so, following filtering of mapping options
                    if ( $applied_mapping_options->continue ) {

                        // If required, switch to internal ids, especially when dealing with key_select types
                        $applied_mapping_options->value = fetch_dt_array_default_field_id( $dt_fields, $dt_field_name, $applied_mapping_options->value );

                        // Safeguard against potential infinite update loops
                        if ( ! matching_dt_field_value( $dt_record, $dt_field_name, $applied_mapping_options->value ) ) {

                            // Update accordingly based on field type and it's presence!
                            if ( in_array( trim( strtolower( $dt_fields[ $dt_field_name ]['type'] ) ), [
                                'text',
                                'key_select'
                            ] ) ) {
                                $updated_fields[ $dt_field_name ] = $applied_mapping_options->value;

                            } elseif ( isset( $dt_record[ $dt_field_name ] ) && is_array( $dt_record[ $dt_field_name ] ) && isset( $dt_record[ $dt_field_name ][0]['key'] ) ) {
                                $updated_fields[ $dt_field_name ][0] = [
                                    'value' => $applied_mapping_options->value,
                                    'key'   => $dt_record[ $dt_field_name ][0]['key']
                                ];

                            } elseif ( ! isset( $dt_record[ $dt_field_name ] ) ) {
                                $updated_fields[ $dt_field_name ]['values'][0] = [
                                    'value' => $applied_mapping_options->value
                                ];

                            }
                        } else {
                            $logs[] = dt_mailchimp_logging_create( 'No mapped DT field [' . $dt_field_name . '] value changes detected!' );
                        }
                    } else {
                        $logs[] = dt_mailchimp_logging_create( 'Mapped DT field [' . $dt_field_name . '] sync update canceled, following options call!' );
                    }
                }
            }
        }
    }

    // Update dt record accordingly; assuming we have valid mapped field updates
    $logs[] = dt_mailchimp_logging_create( 'Mapped DT fields to be updated count: ' . count( $updated_fields ) );
    if ( count( $updated_fields ) > 0 ) {
        $updated = DT_Posts::update_post( $dt_record['post_type'], $dt_record['ID'], $updated_fields, false, false );

        if ( is_wp_error( $updated ) ) {
            $logs[] = dt_mailchimp_logging_create( 'DT Post Update Error: ' . $updated->get_error_message() );

        } else {
            return $updated;
        }
    }

    return null;
}

function matching_mc_field_value( $dt_record, $mc_record, $mc_field_id, $value ): bool {

    // Ensure email checks also validate against all linked mc records
    if ( $mc_field_id === 'EMAIL' ) {
        $mc_records = fetch_mc_record_by_email( $dt_record, $mc_record->list_id, false );

        if ( isset( $mc_records ) && ! empty( $mc_records ) ) {
            foreach ( $mc_records as $linked_mc_record ) {
                if ( $linked_mc_record->email_address === $value ) {
                    return true;
                }
            }
        }
    } else {
        $mc_field_value = $mc_record->merge_fields->{$mc_field_id};

        return ( ! empty( $mc_field_value ) && ( $mc_field_value === $value ) );
    }

    return false;
}

function matching_dt_field_value( $dt_record, $dt_field_id, $value ): bool {

    $dt_field = $dt_record[ $dt_field_id ] ?? null;
    if ( ! empty( $dt_field ) ) {

        // If field is array, check value against all elements
        if ( is_array( $dt_field ) ) {
            foreach ( $dt_field as $key => $item ) {
                if ( isset( $item['value'] ) && ! empty( $item['value'] ) && ( $item['value'] === $value ) ) {
                    return true;

                } elseif ( trim( strtolower( $key ) ) == 'key' && trim( strtolower( $item ) === trim( strtolower( $value ) ) ) ) {
                    return true;
                }
            }
        } else {
            return ( $dt_field === $value );
        }
    }

    return false;
}

function matching_mc_field_category_interests( $mc_record, $interests ): bool {
    $matching = true;
    foreach ( $interests as $interest ) {
        if ( ! isset( $mc_record->interests->{$interest->int_id} ) ) {
            $matching = false;
        }
        if ( $interest->int_selected !== $mc_record->interests->{$interest->int_id} ) {
            $matching = false;
        }
    }

    return $matching;
}

function matching_dt_field_category_interests( $dt_record, $dt_field_id, $dt_field_defaults, $interests ): bool {
    $matching = true;
    foreach ( $interests as $interest ) {
        foreach ( $dt_field_defaults as $key => $default ) {
            if ( $interest->int_name === $default['label'] ) {
                if ( isset( $dt_record[ $dt_field_id ] ) ) {
                    if ( $interest->int_selected !== in_array( $key, $dt_record[ $dt_field_id ] ) ) {
                        $matching = false;
                    }
                } elseif ( ! isset( $dt_record[ $dt_field_id ] ) && $interest->int_selected === true ) {
                    $matching = false;
                }
            }
        }
    }

    return $matching;
}

function handle_dt_record_subscription( $dt_record, $mc_record, &$logs ): array {

    $results                   = [];
    $results['dt_record']      = $dt_record;
    $results['status_changed'] = false;

    // Simply echo back if dt record is empty
    if ( empty( $dt_record ) ) {
        return $results;
    }

    // Determine current subscription status for both records
    $is_mc_record_subscribed = strtolower( $mc_record->status ) === 'subscribed';
    $is_dt_record_subscribed = is_dt_record_sync_enabled( $dt_record ) && in_array( $mc_record->list_id, $dt_record['dt_mailchimp_subscribed_mc_lists'] );
    $subscription_mismatch   = $is_mc_record_subscribed !== $is_dt_record_subscribed;

    // Ensure dt record's subscription status is kept in sync with mc record.
    // See 'mailchimp-auto-subscribe.php' with regards to keeping mc record subscription state in sync with dt records.
    if ( $subscription_mismatch ) {
        $dt_fields = [];

        if ( $is_mc_record_subscribed && ! $is_dt_record_subscribed ) {
            $dt_fields['dt_mailchimp_subscribed_mc_lists']['values'][0] = [
                'value' => $mc_record->list_id
            ];

        } elseif ( ! $is_mc_record_subscribed && $is_dt_record_subscribed ) {

            // Carry out additional checks so as to ensure dt record is not linked with multiple mc records!
            // If so, then ensure all linked mc records are in an unsubscribed state; in order for dt record
            // to be unsubscribed!
            $logs[] = dt_mailchimp_logging_create( 'Checking unsubscribed status of linked mc records' );
            if ( handle_dt_record_subscription_all_linked_mc_records_unsubscribed( $dt_record, $mc_record, $logs ) ) {
                $logs[]                                                     = dt_mailchimp_logging_create( 'Unsubscribing ' . $dt_record['post_type'] . ' DT record [' . $dt_record['ID'] . ']' );
                $dt_fields['dt_mailchimp_subscribed_mc_lists']['values'][0] = [
                    'value'  => $mc_record->list_id,
                    'delete' => true
                ];
            } else {
                $logs[] = dt_mailchimp_logging_create( $dt_record['post_type'] . ' DT record [' . $dt_record['ID'] . '] to remain in a subscribed state!' );
            }
        }

        if ( count( $dt_fields ) > 0 ) {
            $updated_dt_record = DT_Posts::update_post( $dt_record['post_type'], $dt_record['ID'], $dt_fields, false, false );
            if ( ! is_wp_error( $updated_dt_record ) ) {
                $results['dt_record']      = $updated_dt_record;
                $results['status_changed'] = true;

            } else {
                $logs[] = dt_mailchimp_logging_create( 'DT Post Update Error: ' . $updated_dt_record->get_error_message() );

            }
        }
    }

    return $results;
}

function handle_dt_record_subscription_all_linked_mc_records_unsubscribed( $dt_record, $mc_record, &$logs ) {
    $all_unsubscribed = true;
    $mc_records       = fetch_mc_record_by_email( $dt_record, $mc_record->list_id, false );

    if ( isset( $mc_records ) && ! empty( $mc_records ) ) {
        $logs[] = dt_mailchimp_logging_create( 'Linked mc records count: ' . count( $mc_records ) );

        foreach ( $mc_records as $linked_mc_record ) {

            // For now, do not enforce dt hidden id check; so as to also capture
            // linked mc records; which might not have a set hidden id field!
            if ( isset( $linked_mc_record->status ) && strtolower( $linked_mc_record->status ) === 'subscribed' ) {
                $all_unsubscribed = false;
                $logs[]           = dt_mailchimp_logging_create( 'Linked mc record[' . $linked_mc_record->email_address . '] is still subscribed!' );

            }
        }
    }

    return $all_unsubscribed;
}

function fetch_dt_array_default_field_id( $dt_fields, $dt_field_name, $label ) {
    if ( ! empty( $dt_fields[ $dt_field_name ]['default'] ) ) {
        foreach ( $dt_fields[ $dt_field_name ]['default'] as $key => $value ) {
            if ( trim( strtolower( $value['label'] ) ) === trim( strtolower( $label ) ) ) {
                return $key;
            }
        }
    }

    return $label;
}

function fetch_dt_array_default_field_label( $dt_fields, $dt_field_name, $id ) {
    if ( ! empty( $dt_fields[ $dt_field_name ]['default'] ) ) {
        foreach ( $dt_fields[ $dt_field_name ]['default'] as $key => $value ) {
            if ( $key === $id ) {
                return $value['label'];
            }
        }
    }

    return $id;
}
