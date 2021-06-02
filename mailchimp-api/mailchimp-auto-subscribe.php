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
    $subscribed_mc_lists = $initial_fields['dt_mailchimp_subscribed_mc_lists']['values'];
    if ( isset( $subscribed_mc_lists ) ) {

        // Iterate through list, updating Mailchimp subscription status based on current state.
        foreach ( $subscribed_mc_lists as $list ) {

            $mc_list_id = $list['value'];
            $status     = ( isset( $list['delete'] ) && $list['delete'] ) ? 'unsubscribed' : 'subscribed';

            // Next, attempt to locate corresponding mc record
            $mc_record = Disciple_Tools_Mailchimp_API::find_list_member_by_hidden_id_fields( $mc_list_id, $post_id );
            if ( ! empty( $mc_record ) ) {

                // Only update if there is a subscription mismatch!
                if ( strtolower( $mc_record->status ) !== $status ) {

                    // Prepare update payload
                    $payload = [
                        'status' => $status
                    ];

                    // Dispatch update payload
                    $updated_mc_record = Disciple_Tools_Mailchimp_API::update_list_member( $mc_list_id, $mc_record->id, $payload );

                    if ( ! empty( $updated_mc_record ) ) {
                        update_global_last_run( 'dt_mailchimp_sync_last_run_ts_dt_to_mc', time() );
                        update_list_last_run( $mc_list_id, 'dt_to_mc_last_sync_run', time() );
                        update_option( 'dt_mailchimp_subscribe_debug', '' );

                    } else {
                        update_option( 'dt_mailchimp_subscribe_debug', 'Auto-Subscribe sync failed for mc list ' . $mc_list_id . ' record [id:' . $mc_record->id . '] based on dt record [id:' . $post_id . ']' );
                    }
                }
            }
        }
    }
}
