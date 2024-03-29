<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Mailchimp_API
 */
class Disciple_Tools_Mailchimp_API {

    public static $schedule_cron_event_hook = 'dt_mailchimp_sync';

    public static function schedule_cron_event() {
        if ( self::has_api_key() ) {
            if ( ! wp_next_scheduled( self::$schedule_cron_event_hook ) ) {
                wp_schedule_event( time(), '5min', self::$schedule_cron_event_hook );
            }
        }
    }

    private static function has_api_key(): bool {
        return ! empty( get_option( 'dt_mailchimp_mc_api_key' ) ) && strpos( get_option( 'dt_mailchimp_mc_api_key' ), '-' ) !== false; // check for the presence of any potential datacenters
    }

    private static function get_api_key(): string {
        return get_option( 'dt_mailchimp_mc_api_key' );
    }

    private static function get_api_server(): string {
        $key = self::get_api_key();

        return substr( $key, strpos( $key, '-' ) + 1 ); // datacenter, it is the part of api key - us5, us8 etc
    }

    private static function build_api_connect_config(): array {
        return [
            'apiKey' => self::get_api_key(),
            'server' => self::get_api_server()
        ];
    }

    private static function get_mailchimp_client(): MailchimpMarketing\ApiClient {
        $mailchimp = new MailchimpMarketing\ApiClient();
        $mailchimp->setConfig( self::build_api_connect_config() );

        return $mailchimp;
    }

    public static function get_lists(): array {
        if ( ! self::has_api_key() ) {
            return [];
        }

        try {
            $response = self::get_mailchimp_client()->lists->getAllLists();

            return ( ! empty( $response ) && ! empty( $response->lists ) ) ? $response->lists : [];

        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'get_lists() - ' . $e->getMessage() );

            return [];
        }
    }

    public static function get_list_name( $list_id ): string {
        if ( ! self::has_api_key() ) {
            return '';
        }

        try {
            $response = self::get_mailchimp_client()->lists->getList( $list_id );

            return ( ! empty( $response ) && ! empty( $response->name ) ) ? $response->name : '';

        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'get_list_name() - ' . $e->getMessage() );

            return '';
        }
    }

    public static function get_list_fields( $list_id, $include_default_email = true, $include_hidden_fields = false ): array {
        if ( ! self::has_api_key() ) {
            return [];
        }

        try {
            $response = self::get_mailchimp_client()->lists->getListMergeFields( $list_id );

            // Return accordingly based on flags
            if ( ! empty( $response ) && ! empty( $response->merge_fields ) ) {

                // Filter Default Email Field
                if ( $include_default_email ) {
                    array_unshift( $response->merge_fields, (object) [
                        "merge_id" => "0",
                        "tag"      => "EMAIL",
                        "name"     => "Email",
                        "type"     => "email"
                    ] );
                }

                // Filter Hidden Fields
                if ( ! $include_hidden_fields ) {

                    $filtered_fields = [];
                    foreach ( $response->merge_fields as $field ) {
                        if ( ! self::is_list_hidden_id_field( $field->name ) ) {
                            $filtered_fields[] = $field;
                        }
                    }

                    return self::remove_unsupported_list_field_types( $filtered_fields );

                } else {
                    return self::remove_unsupported_list_field_types( $response->merge_fields );
                }
            } else {
                return [];
            }
        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'get_list_fields() - ' . $e->getMessage() );

            return [];
        }
    }

    private static function remove_unsupported_list_field_types( $fields ): array {

        // Currently, supported field types
        $supported_field_types = [ 'email', 'text', 'phone', 'dropdown' ];

        $valid_fields = [];
        foreach ( $fields as $field ) {
            if ( in_array( trim( strtolower( $field->type ) ), $supported_field_types ) ) {
                $valid_fields[] = $field;
            }
        }

        return $valid_fields;
    }

    public static function get_list_interest_categories( $list_id, $load_interests = false ): array {
        if ( ! self::has_api_key() ) {
            return [];
        }

        try {
            $categories = [];

            $interest_categories = self::get_mailchimp_client()->lists->getListInterestCategories( $list_id, null, null, 1000 );

            if ( ! empty( $interest_categories ) && ! empty( $interest_categories->categories ) ) {

                // Iterate over categories, obtaining associated interests in the process
                foreach ( $interest_categories->categories as $category ) {

                    $interest_data = [];

                    if ( $load_interests ) {

                        // Fetch associated interests
                        $interests = self::get_mailchimp_client()->lists->listInterestCategoryInterests( $list_id, $category->id, null, null, 1000 );

                        // Reshape data structure, ahead of final assignment and return!
                        if ( ! empty( $interests ) && ! empty( $interests->interests ) ) {
                            foreach ( $interests->interests as $interest ) {
                                $interest_data[] = (object) [
                                    "int_id"   => $interest->id,
                                    "int_name" => $interest->name
                                ];
                            }
                        }
                    }

                    // Capture category, ahead of final return
                    $categories[ $category->id ] = (object) [
                        "cat_id"        => $category->id,
                        "cat_title"     => $category->title,
                        "cat_interests" => $interest_data
                    ];
                }
            }

            return $categories;

        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'get_list_interest_categories() - ' . $e->getMessage() );

            return [];
        }
    }

    public static function get_list_interest_categories_field_prefix(): string {
        return '[INTCAT]_';
    }

    public static function get_list_members_since_last_changed( $list_id, $epoch_timestamp ): array {
        if ( ! self::has_api_key() ) {
            return [];
        }

        try {
            $since_last_changed = gmdate( 'Y-m-d\TH:i:s\Z', $epoch_timestamp );
            $response           = self::get_mailchimp_client()->lists->getListMembersInfo( $list_id, null, null, 1000, 0, null, null, null, null, $since_last_changed, null, null, null, null, null, null, 'last_changed', 'ASC' );

            // Return accordingly based member results
            if ( ! empty( $response ) && ! empty( $response->members ) ) {
                return $response->members;

            } else {
                return [];
            }
        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'get_list_members_since_last_changed() - ' . $e->getMessage() );

            return [];
        }
    }

    public static function has_list_got_hidden_id_fields( $list_id ): bool {
        $hidden_fields_detected = false;
        if ( ! self::has_api_key() ) {
            return $hidden_fields_detected;
        }

        /**
         * Fetch all fields associated with specified list.
         */

        try {
            $list_fields = self::get_list_fields( $list_id, true, true );
            if ( ! empty( $list_fields ) ) {

                /**
                 * Determine if returned fields contain required hidden fields?
                 */

                foreach ( $list_fields as $field ) {
                    if ( self::is_list_hidden_id_field( $field->name ) ) {
                        $hidden_fields_detected = true;
                    }
                }
            }

            return $hidden_fields_detected;

        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'has_list_got_hidden_id_fields() - ' . $e->getMessage() );

            return $hidden_fields_detected;
        }
    }

    private static function build_list_hidden_id_fields(): array {
        return array(
            [
                'name'          => 'DT_POST_ID',
                'type'          => 'text',
                'tag'           => 'DT_POST_ID',
                'required'      => false,
                'default_value' => '',
                'public'        => false
            ]
        );
    }

    public static function get_default_list_hidden_id_field( $by_tag = true ) {
        return self::build_list_hidden_id_fields()[0][ ( $by_tag ) ? 'tag' : 'name' ];
    }

    private static function is_list_hidden_id_field( $field_name ): bool {
        $hidden_field_names = [];

        foreach ( self::build_list_hidden_id_fields() as $hidden_field ) {
            $hidden_field_names[] = $hidden_field['name'];
        }

        return in_array( $field_name, $hidden_field_names );
    }

    public static function generate_list_hidden_id_fields( $list_id ) {
        if ( ! self::has_api_key() ) {
            return;
        }

        /**
         * Build hidden fields, iterate and create corresponding mailchimp merge fields.
         */

        try {
            foreach ( self::build_list_hidden_id_fields() as $hidden_field ) {
                self::get_mailchimp_client()->lists->addListMergeField( $list_id, $hidden_field );
            }
        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'generate_list_hidden_id_fields() - ' . $e->getMessage() );

            return;
        }
    }

    public static function find_list_member_by_email( $list_id, $email ) {
        if ( ! self::has_api_key() ) {
            return null;
        }

        try {
            $response = self::get_mailchimp_client()->searchMembers->search( $email, null, null, $list_id );

            if ( ! empty( $response ) && ! empty( $response->exact_matches ) && ( $response->exact_matches->total_items > 0 ) ) {
                // For now, we just return the first hit
                return $response->exact_matches->members[0];
            }

            return null;

        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'find_list_member_by_email() - ' . $e->getMessage() );

            return null;
        }
    }

    public static function find_list_member_by_hidden_id_fields( $list_id, $id ) {
        if ( ! self::has_api_key() || ! self::has_list_got_hidden_id_fields( $list_id ) ) {
            return null;
        }

        /**
         * Assuming hidden fields are present, iterate over list members in search of a match.
         * Currently, only the first 1K members are searched!
         */

        try {
            $response = self::get_mailchimp_client()->lists->getListMembersInfo( $list_id, null, null, 1000 );

            // Start traversing list members, in search of the one!
            if ( ! empty( $response ) && ! empty( $response->members ) ) {
                $default_hidden_field_tag = self::get_default_list_hidden_id_field();
                foreach ( $response->members as $member ) {
                    // Are merge fields present
                    if ( isset( $member->merge_fields ) ) {
                        // Is default hidden field present
                        if ( isset( $member->merge_fields->$default_hidden_field_tag ) ) {
                            // Do we have a match?
                            if ( $id === intval( $member->merge_fields->$default_hidden_field_tag ) ) {
                                return $member;
                            }
                        }
                    }
                }
            }

            return null;

        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'find_list_member_by_hidden_id_fields() - ' .$e->getMessage() );

            return null;
        }
    }

    public static function add_new_list_member( $list_id, $member ) {
        if ( ! self::has_api_key() ) {
            return null;
        }

        try {
            $response = self::get_mailchimp_client()->lists->addListMember( $list_id, $member );

            if ( ! empty( $response ) && ! empty( $response->id ) ) {
                return $response;

            } else {
                return null;
            }
        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'add_new_list_member() - ' . $e->getMessage() );
            dt_mailchimp_logging_add( print_r( $member, true ) );

            return null;
        }
    }

    public static function update_list_member( $list_id, $member_id, $updates ) {
        if ( ! self::has_api_key() ) {
            return null;
        }

        try {
            $response = self::get_mailchimp_client()->lists->updateListMember( $list_id, $member_id, $updates );

            if ( ! empty( $response ) && ! empty( $response->id ) ) {
                return $response;

            } else {
                return null;
            }
        } catch ( Exception $e ) {
            dt_mailchimp_logging_add( 'update_list_member() - ' . $e->getMessage() );
            dt_mailchimp_logging_add( print_r( $updates, true ) );

            return null;
        }
    }
}
