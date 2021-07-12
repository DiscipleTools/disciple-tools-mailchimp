<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Core subscription management logic -> DT to MC
 */

add_action( 'dt_post_updated', 'dt_mailchimp_auto_subscribe', 10, 5 );
function dt_mailchimp_auto_subscribe( $post_type, $post_id, $initial_fields, $existing_post, $post ) {

    // Only concerned with mailchimp list selections
    if ( ! wp_doing_cron() ) {
        if ( isset( $initial_fields['dt_mailchimp_subscribed_mc_lists']['values'] ) ) {
            $subscribed_mc_lists = $initial_fields['dt_mailchimp_subscribed_mc_lists']['values'];
            dt_mailchimp_logging_add( 'Executing DT -> MC subscription update request' );

            // Iterate through list, updating Mailchimp subscription status based on current state.
            foreach ( $subscribed_mc_lists as $list ) {

                try {
                    $mc_list_id = $list['value'];
                    $status     = ( isset( $list['delete'] ) && $list['delete'] ) ? 'unsubscribed' : 'subscribed';

                    // As a single dt record could be linked to multiple mc records, ensure to fetch all mc records!
                    $mc_records = fetch_mc_record_by_email( $existing_post, $mc_list_id, false );

                    if ( empty( $mc_records ) ) {

                        // If no link records are found, then attempt to find by hidden id field
                        $mc_record = Disciple_Tools_Mailchimp_API::find_list_member_by_hidden_id_fields( $mc_list_id, $post_id );
                        if ( ! empty( $mc_record ) ) {
                            $mc_records = array( $mc_record );
                        } else {
                            $mc_records = array();
                        }
                    }

                    $hidden_id_field_tag = Disciple_Tools_Mailchimp_API::get_default_list_hidden_id_field();

                    // Iterate through each mc record
                    foreach ( $mc_records as $mc_record ) {
                        if ( ! empty( $mc_record ) ) {

                            // Ensure the linked mc record's hidden id field matches dt record id
                            if ( isset( $mc_record->merge_fields->{$hidden_id_field_tag} ) && strval( $mc_record->merge_fields->{$hidden_id_field_tag} ) === strval( $post_id ) ) {

                                // Only update if there is a subscription mismatch!
                                if ( strtolower( $mc_record->status ) !== $status ) {

                                    // Prepare update payload
                                    $payload = [
                                        'status' => $status
                                    ];

                                    // Dispatch update payload
                                    $updated_mc_record = Disciple_Tools_Mailchimp_API::update_list_member( $mc_list_id, $mc_record->id, $payload );

                                    if ( ! empty( $updated_mc_record ) ) {
                                        update_list_option_value( $mc_list_id, 'dt_to_mc_last_sync_run', time() );
                                        update_list_option_value( $mc_list_id, 'log', '' );
                                        update_option( 'dt_mailchimp_subscribe_debug', '' );
                                        dt_mailchimp_logging_add( 'Updated MC record [' . $updated_mc_record->email_address . '] subscription status, based on dt record [id:' . $post_id . ', post type: ' . $post_type . ']' );

                                    } else {
                                        update_option( 'dt_mailchimp_subscribe_debug', 'Auto-Subscribe sync failed for mc list ' . $mc_list_id . ' record [id:' . $mc_record->id . '] based on dt record [id:' . $post_id . ', post type: ' . $post_type . ']' );
                                        update_list_option_value( $mc_list_id, 'log', 'Auto-Subscribe sync failed for mc record [id:' . $mc_record->id . '] based on dt record [id:' . $post_id . ', post type: ' . $post_type . ']' );
                                        dt_mailchimp_logging_add( 'Auto-Subscribe sync failed for mc list ' . $mc_list_id . ' record [' . $mc_record->email_address . '] based on dt record [id:' . $post_id . ', post type: ' . $post_type . ']' );
                                    }
                                }
                            } else {
                                dt_mailchimp_logging_add( 'Linked mc record [' . $mc_record->email_address . '] does not contain a hidden ' . $post_type . ' dt post id of -> ' . $post_id );
                            }
                        }
                    }
                } catch ( Exception $exception ) {
                    dt_mailchimp_logging_add( 'Exception: ' . $exception->getMessage() );
                }
            }
        }
    }
}
