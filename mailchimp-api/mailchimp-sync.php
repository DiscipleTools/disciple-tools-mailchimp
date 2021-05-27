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

        // Determine global last run timestamp
        $last_run = fetch_global_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc' );

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
                    foreach ( $dt_post_record['dt_mailchimp_subscribed_mc_lists'] as $subscribed_mc_list_id ) {

                        // Ensure mc list is supported and has mapping
                        if ( in_array( $subscribed_mc_list_id, $supported_mc_lists ) && isset( $supported_mappings->$subscribed_mc_list_id ) ) {

                            // Ensure mappings post type corresponds with current post type id; so as to avoid cross post type syncs
                            if ( $dt_post_type_id === $supported_mappings->$subscribed_mc_list_id->dt_post_type ) {

                                // Extract array of mapped fields; which are to be kept in sync
                                $field_mappings = $supported_mappings->$subscribed_mc_list_id->mappings;

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

                                        // Update last run timestamps, assuming we have valid updates
                                        if ( ! empty( $updated ) ) {
                                            update_global_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc', time() );
                                            update_list_last_run( $subscribed_mc_list_id, 'dt_to_mc_last_sync_run', time() );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

function sync_mc_to_dt() {
    if ( is_sync_enabled( 'dt_mailchimp_mc_accept_sync' ) ) {

        sync_debug( 'dt_mailchimp_mc_debug', '' );

        // Determine global last run timestamp
        $last_run = fetch_global_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt' );

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

                    $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();
                    $dt_post_type_id     = $supported_mappings->{$mc_record->list_id}->dt_post_type;

                    // First, attempt to fetch corresponding dt record, using the info to hand!
                    $dt_record = fetch_dt_record( $mc_record, $dt_post_type_id );

                    // If still no hit, then a new dt record will be created
                    $is_new_dt_record = false;
                    if ( empty( $dt_record ) ) {
                        $dt_record        = create_dt_record( $mc_record, $dt_post_type_id );
                        $is_new_dt_record = true;
                    }

                    // Handle dt record subscription status - Ensure it remains in sync with mc record
                    $dt_record = handle_dt_record_subscription( $dt_record, $mc_record );

                    // Only proceed if we have a handle on corresponding dt record
                    if ( ! empty( $dt_record ) && is_dt_record_sync_enabled( $dt_record ) && in_array( $mc_record->list_id, $dt_record['dt_mailchimp_subscribed_mc_lists'] ) ) {

                        // Ensure mc record has latest changes, in order to update dt
                        if ( $is_new_dt_record || ! dt_record_has_latest_changes( $dt_record, $mc_record ) ) {

                            // Extract array of mapped fields; which are to be kept in sync
                            $field_mappings = $supported_mappings->{$mc_record->list_id}->mappings;

                            // Update dt record
                            $updated = update_dt_record( $dt_record, $mc_record, $field_mappings );
                            sync_debug( 'dt_mailchimp_mc_debug', $updated );

                            // Update last run timestamps, assuming we have valid updates
                            if ( ! empty( $updated ) ) {
                                update_global_last_run( 'dt_mailchimp_sync_last_run_ts_mc_to_dt', time() );
                                update_list_last_run( $mc_record->list_id, 'mc_to_dt_last_sync_run', time() );
                            }
                        }
                    }
                }
            }
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
    return boolval( get_option( $option_name ) );
}

function fetch_supported_mappings() {
    return json_decode( get_option( 'dt_mailchimp_mappings' ) );
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

function update_list_last_run( $mc_list_id, $option_name, $timestamp ) {

    // Only update if we have an existing entry
    $supported_lists = get_option( 'dt_mailchimp_mc_supported_lists' );
    if ( ! empty( $supported_lists ) ) {

        $lists = json_decode( $supported_lists );
        if ( isset( $lists->{$mc_list_id} ) ) {
            $lists->{$mc_list_id}->{$option_name} = $timestamp;

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
    return ( isset( $dt_post['dt_mailchimp_subscribed_mc_lists'] ) && ( count( $dt_post['dt_mailchimp_subscribed_mc_lists'] ) > 0 ) );
}

function fetch_mc_record( $dt_post_record, $mc_list_id ) {

    // 1st - If present, search by all dt record's email addresses
    $mc_record = fetch_mc_record_by_email( $dt_post_record, $mc_list_id );

    // If we have a hit; ensure mc record's hidden id field is updated with dt record's post id
    if ( ! empty( $mc_record ) ) {
        $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();

        if ( $dt_post_record['ID'] !== $mc_record->merge_fields->{$hidden_id_field_tag} ) {

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

function fetch_mc_record_by_email( $dt_post_record, $mc_list_id ) {
    $emails = extract_dt_record_emails( $dt_post_record, false );
    if ( isset( $emails ) && ! empty( $emails ) ) {

        // Search Mailchimp for a corresponding record, based on loop email address
        foreach ( $emails as $email ) {
            $mc_record = Disciple_Tools_Mailchimp_API::find_list_member_by_email( $mc_list_id, $email['value'] );
            if ( ! empty( $mc_record ) ) {
                return $mc_record;
            }
        }
    }

    return null;
}

function fetch_mc_record_by_hidden_fields( $dt_post_record, $mc_list_id ) {
    return Disciple_Tools_Mailchimp_API::find_list_member_by_hidden_id_fields( $mc_list_id, $dt_post_record['ID'] );
}

function fetch_dt_record( $mc_record, $dt_post_type_id ) {

    $dt_record = null;

    // 1st - If present, search for dt record using mc record's hidden post id
    $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();
    if ( isset( $mc_record->merge_fields->{$hidden_id_field_tag} ) && ! empty( $mc_record->merge_fields->{$hidden_id_field_tag} ) ) {
        $dt_post_id = intval( $mc_record->merge_fields->{$hidden_id_field_tag} );
        $hit        = DT_Posts::get_post( $dt_post_type_id, $dt_post_id, false, false );

        if ( ! empty( $hit ) && ! is_wp_error( $hit ) ) {
            $dt_record = $hit;
        }
    }

    // 2nd - Failing 1st, search for dt record using mc record's email address
    if ( empty( $dt_record ) ) {
        $dt_record = fetch_dt_record_by_email( $dt_post_type_id, $mc_record->email_address );

        // If we have a hit; ensure mc record's hidden id field is updated with dt record's post id
        if ( ! empty( $dt_record ) ) {
            $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();

            if ( $dt_record['ID'] !== $mc_record->merge_fields->{$hidden_id_field_tag} ) {

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

    return $dt_record;
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

function update_mc_record( $mc_list_id, $dt_record, $mc_record, $mappings ) {

    // Loop over all mapped fields; updating accordingly based on specified options
    $updated_fields = [];
    foreach ( $mappings as $mapping ) {

        // Distinguish between different field shapes; e.g. arrays, strings...
        // If array, default value will be taken from the first element
        $dt_field = $dt_record[ $mapping->dt_field_id ];
        if ( ! empty( $dt_field ) ) {
            $dt_field_value = is_array( $dt_field ) ? $dt_field[0]['value'] : $dt_field;

            // Apply mapping transformation options prior to setting updated dt value
            $applied_mapping_options = apply_mapping_options( $dt_record, $mc_record, $dt_field_value, true, $mapping->options );

            // Add updated value, assuming we have the green light to do so, following filtering of mapping options
            if ( $applied_mapping_options->continue ) {

                // Safeguard against potential infinite update loops
                if ( ! matching_mc_field_value( $mc_record, $mapping->mc_field_id, $applied_mapping_options->value ) ) {
                    $updated_fields[ $mapping->mc_field_id ] = $applied_mapping_options->value;
                }
            }
        }
    }

    // Only proceed if we have something to say! ;)
    if ( count( $updated_fields ) > 0 ) {

        // Package updated values, ahead of final push
        $updated_mc_record                 = [];
        $updated_mc_record['merge_fields'] = $updated_fields;

        // Finally, post update request
        return Disciple_Tools_Mailchimp_API::update_list_member( $mc_list_id, $mc_record->id, $updated_mc_record );

    } else {
        return null;
    }
}

function update_dt_record( $dt_record, $mc_record, $mappings ) {

    // First, fetch dt post type field settings; which will be used further down stream
    $dt_fields = DT_Posts::get_post_settings( $dt_record['post_type'] )['fields'];

    // Iterate over mapped fields
    $updated_fields = [];
    foreach ( $mappings as $mapping ) {

        // Determine actual field name
        $dt_field_name = $mapping->dt_field_id;

        // Only proceed if a valid dt field name has been identified
        if ( ! empty( $dt_field_name ) ) {

            // Extract values and apply any detected mapping option functions
            // Ensure to accommodate emails; which are not specified un 'merge_fields'
            $mc_field_value = ( $mapping->mc_field_id === 'EMAIL' ) ? $mc_record->email_address : $mc_record->merge_fields->{$mapping->mc_field_id};

            if ( ! empty( $mc_field_value ) ) {

                // Apply mapping transformation options prior to setting updated mc value
                $applied_mapping_options = apply_mapping_options( $dt_record, $mc_record, $mc_field_value, false, $mapping->options );

                // Add updated value, assuming we have the green light to do so, following filtering of mapping options
                if ( $applied_mapping_options->continue ) {

                    // Safeguard against potential infinite update loops
                    if ( ! matching_dt_field_value( $dt_record, $dt_field_name, $applied_mapping_options->value ) ) {

                        // Update accordingly based on field type and it's presence!
                        $is_text_field = isset( $dt_fields[ $dt_field_name ] ) && strtolower( $dt_fields[ $dt_field_name ]['type'] ) === 'text';

                        if ( $is_text_field ) {
                            $updated_fields[ $dt_field_name ] = $applied_mapping_options->value;

                        } elseif ( isset( $dt_record[ $dt_field_name ] ) && is_array( $dt_record[ $dt_field_name ] ) ) {
                            $updated_fields[ $dt_field_name ][0] = [
                                'value' => $applied_mapping_options->value,
                                'key'   => $dt_record[ $dt_field_name ][0]['key']
                            ];

                        } elseif ( ! isset( $dt_record[ $dt_field_name ] ) ) {
                            $updated_fields[ $dt_field_name ]['values'][0] = [
                                'value' => $applied_mapping_options->value
                            ];
                        }
                    }
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

function matching_mc_field_value( $mc_record, $mc_field_id, $value ): bool {

    $mc_field_value = ( $mc_field_id === 'EMAIL' ) ? $mc_record->email_address : $mc_record->merge_fields->{$mc_field_id};

    return ( ! empty( $mc_field_value ) && ( $mc_field_value === $value ) );
}

function matching_dt_field_value( $dt_record, $dt_field_id, $value ): bool {

    $dt_field = $dt_record[ $dt_field_id ];
    if ( ! empty( $dt_field ) ) {

        $dt_field_value = is_array( $dt_field ) ? $dt_field[0]['value'] : $dt_field;

        return ( ! empty( $dt_field_value ) && ( $dt_field_value === $value ) );
    }

    return false;
}

function handle_dt_record_subscription( $dt_record, $mc_record ) {

    // Simply echo back if dt record is empty
    if ( empty( $dt_record ) ) {
        return $dt_record;
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
            $dt_fields['dt_mailchimp_subscribed_mc_lists']['values'][0] = [
                'value'  => $mc_record->list_id,
                'delete' => true
            ];
        }

        if ( count( $dt_fields ) > 0 ) {
            $updated_dt_record = DT_Posts::update_post( $dt_record['post_type'], $dt_record['ID'], $dt_fields, false, false );
            if ( ! is_wp_error( $updated_dt_record ) ) {
                return $updated_dt_record;
            }
        }
    }

    return $dt_record;
}
