<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Mailchimp_Menu
 */
class Disciple_Tools_Mailchimp_Menu {

    public $token = 'disciple_tools_mailchimp';

    private static $_instance = null;

    /**
     * Disciple_Tools_Mailchimp_Menu Instance
     *
     * Ensures only one instance of Disciple_Tools_Mailchimp_Menu is loaded or can be loaded.
     *
     * @return Disciple_Tools_Mailchimp_Menu instance
     * @since 0.1.0
     * @static
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()


    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {

        add_action( "admin_menu", array( $this, "register_menu" ) );

    } // End __construct()


    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        add_submenu_page( 'dt_extensions', 'Mailchimp', 'Mailchimp', 'manage_dt', $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple.Tools Theme fully loads.
     */
    public function extensions_menu() {
    }

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( ! current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple.Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        if ( isset( $_GET["tab"] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET["tab"] ) );
        } else {
            $tab = 'general';
        }

        $link = 'admin.php?page=' . $this->token . '&tab=';

        ?>
        <div class="wrap">
            <h2>Disciple.Tools : MAILCHIMP</h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'general' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'general' || ! isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">General</a>
                <a href="<?php echo esc_attr( $link ) . 'mappings' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'mappings' || ! isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">Mappings</a>
                <a href="<?php echo esc_attr( $link ) . 'logging' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'logging' || ! isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">Logging</a>
            </h2>

            <?php
            switch ( $tab ) {
                case "general":
                    $object = new Disciple_Tools_Mailchimp_Tab_General();
                    $object->content();
                    break;
                case "mappings":
                    $object = new Disciple_Tools_Mailchimp_Tab_Mappings();
                    $object->content();
                    break;
                case "logging":
                    $object = new Disciple_Tools_Mailchimp_Tab_Logging();
                    $object->content();
                    break;
                default:
                    break;
            }
            ?>

        </div><!-- End wrap -->

        <?php
    }
}

Disciple_Tools_Mailchimp_Menu::instance();

/**
 * Class Disciple_Tools_Mailchimp_Tab_General
 */
class Disciple_Tools_Mailchimp_Tab_General {
    public function content() {
        // First, handle update submissions
        $this->process_updates();

        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php /* $this->right_column() */ ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php

        // Load scripts
        $this->load_scripts();
    }

    private function load_scripts() {
        wp_enqueue_script( 'dt_mailchimp_admin_general_scripts', plugin_dir_url( __FILE__ ) . 'js/scripts-admin-general.js', [
            'jquery',
            'lodash'
        ], 1, true );
    }

    private function process_updates() {
        // Connectivity Updates
        if ( isset( $_POST['mc_main_col_connect_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_connect_nonce'] ) ), 'mc_main_col_connect_nonce' ) ) {
            update_option( 'dt_mailchimp_mc_accept_sync', isset( $_POST['mc_main_col_connect_mc_accept_sync_feed'] ) ? 1 : 0 );
            update_option( 'dt_mailchimp_dt_push_sync', isset( $_POST['mc_main_col_connect_dt_push_sync_feed'] ) ? 1 : 0 );

            // Ensure changing of api keys force a sync reset!
            if ( isset( $_POST['mc_main_col_connect_mc_api_key'] ) ) {
                if ( get_option( 'dt_mailchimp_mc_api_key' ) !== sanitize_text_field( wp_unslash( $_POST['mc_main_col_connect_mc_api_key'] ) ) ) {
                    delete_option( 'dt_mailchimp_sync_last_run_ts_dt_to_mc' );
                    delete_option( 'dt_mailchimp_sync_last_run_ts_mc_to_dt' );
                }
            }
            update_option( 'dt_mailchimp_mc_api_key', isset( $_POST['mc_main_col_connect_mc_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_connect_mc_api_key'] ) ) : '' );

            // Set new dt records assigned user
            update_option( 'dt_mailchimp_dt_new_record_assign_user_id', isset( $_POST['mc_main_col_connect_dt_new_record_assigned_user'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_connect_dt_new_record_assigned_user'] ) ) : '' );
        }

        // Available Mailchimp List Additions
        if ( isset( $_POST['mc_main_col_available_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_available_nonce'] ) ), 'mc_main_col_available_nonce' ) ) {

            $selected_list_id   = ( isset( $_POST['mc_main_col_available_selected_list_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_available_selected_list_id'] ) ) : '';
            $selected_list_name = ( isset( $_POST['mc_main_col_available_selected_list_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_available_selected_list_name'] ) ) : '';

            if ( ! empty( $selected_list_id ) && ! empty( $selected_list_name ) ) {

                // Fetch existing list of supported mc lists.
                $supported_lists = json_decode( $this->fetch_mc_supported_lists() );

                // Add/Overwrite selected list entry.
                $supported_lists->{$selected_list_id} = (object) [
                    'id'                     => $selected_list_id,
                    'name'                   => $selected_list_name,
                    'mc_to_dt_last_sync_run' => '',
                    'dt_to_mc_last_sync_run' => '',
                    'log'                    => ''
                ];

                // Save changes.
                update_option( 'dt_mailchimp_mc_supported_lists', json_encode( $supported_lists ) );

                // Automatically create Mailchimp hidden fields if required.
                if ( ! Disciple_Tools_Mailchimp_API::has_list_got_hidden_id_fields( $selected_list_id ) ) {
                    Disciple_Tools_Mailchimp_API::generate_list_hidden_id_fields( $selected_list_id );
                }
            }
        }

        // Supported Mailchimp Lists Updates
        if ( isset( $_POST['mc_main_col_support_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_support_nonce'] ) ), 'mc_main_col_support_nonce' ) ) {
            update_option( 'dt_mailchimp_mc_supported_lists', isset( $_POST['mc_main_col_support_mc_lists_hidden_current_mc_list'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_support_mc_lists_hidden_current_mc_list'] ) ) : '{}' );
        }

        // Supported DT Post Type Updates
        if ( isset( $_POST['mc_main_col_support_dt_post_types_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_support_dt_post_types_nonce'] ) ), 'mc_main_col_support_dt_post_types_nonce' ) ) {
            update_option( 'dt_mailchimp_dt_supported_post_types', isset( $_POST['mc_main_col_support_dt_post_types_hidden'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_support_dt_post_types_hidden'] ) ) : '[]' );
        }

        // Supported DT Field Type Updates
        if ( isset( $_POST['mc_main_col_support_dt_field_types_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_support_dt_field_types_nonce'] ) ), 'mc_main_col_support_dt_field_types_nonce' ) ) {
            update_option( 'dt_mailchimp_dt_supported_field_types', isset( $_POST['mc_main_col_support_dt_field_types_hidden'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_support_dt_field_types_hidden'] ) ) : '[]' );
        }
    }

    private function fetch_mc_api_key(): string {
        return get_option( 'dt_mailchimp_mc_api_key' );
    }

    private function is_accept_mc_sync_enabled(): bool {

        // Ensure the default state for first time setups, is that of TRUE!
        $value = get_option( 'dt_mailchimp_mc_accept_sync' );
        if ( isset( wp_cache_get( 'notoptions', 'options' )['dt_mailchimp_mc_accept_sync'] ) ) {
            update_option( 'dt_mailchimp_mc_accept_sync', 1 );

            return true;

        } else {
            return boolval( $value );
        }
    }

    private function is_push_dt_sync_enabled(): bool {

        // Ensure the default state for first time setups, is that of TRUE!
        $value = get_option( 'dt_mailchimp_dt_push_sync' );
        if ( isset( wp_cache_get( 'notoptions', 'options' )['dt_mailchimp_dt_push_sync'] ) ) {
            update_option( 'dt_mailchimp_dt_push_sync', 1 );

            return true;

        } else {
            return boolval( $value );
        }
    }

    private function fetch_mc_supported_lists(): string {
        $supported_lists = get_option( 'dt_mailchimp_mc_supported_lists' );

        return ! empty( $supported_lists ) ? $supported_lists : '{}';
    }

    private function fetch_dt_assigned_user_id(): int {
        return get_option( 'dt_mailchimp_dt_new_record_assign_user_id' );
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table id="mc_main_col_connect_table_section" class="widefat striped">
            <thead>
            <tr>
                <th>Connectivity</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_connectivity(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <!-- Box -->
        <table id="mc_main_col_available_table_section" class="widefat striped">
            <thead>
            <tr>
                <th>Select Mailchimp Lists To Sync</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_available_mc_lists(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <!-- Box -->
        <table id="mc_main_col_support_table_section" class="widefat striped">
            <thead>
            <tr>
                <th>Supported Mailchimp Lists</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_supported_mc_lists(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <!-- Box -->
        <table id="mc_main_col_support_dt_post_types_table_section" class="widefat striped">
            <thead>
            <tr>
                <th>[Advanced Options] Supported DT Post Types</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_supported_dt_post_types(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <!-- Box -->
        <table id="mc_main_col_support_dt_field_types_table_section" class="widefat striped">
            <thead>
            <tr>
                <th>[Advanced Options] Supported DT Field Types</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_supported_dt_field_types(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Summary</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    private function main_column_connectivity() {
        ?>
        <form method="POST">
            <input type="hidden" id="mc_main_col_connect_nonce" name="mc_main_col_connect_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_connect_nonce' ) ) ?>"/>

            <table class="widefat striped">
                <tr>
                    <td style="vertical-align: middle;">Mailchimp API Key</td>
                    <td>
                        <input type="password" style="min-width: 100%;" id="mc_main_col_connect_mc_api_key"
                               name="mc_main_col_connect_mc_api_key"
                               value="<?php echo esc_attr( $this->fetch_mc_api_key() ) ?>"/><br>
                        <input type="checkbox" id="mc_main_col_connect_mc_api_key_show">Show API Key
                    </td>
                </tr>
                <tr>
                    <td>Fetch Mailchimp Updates</td>
                    <td>
                        <input type="checkbox" id="mc_main_col_connect_mc_accept_sync_feed"
                               name="mc_main_col_connect_mc_accept_sync_feed" <?php echo esc_attr( $this->is_accept_mc_sync_enabled() ? 'checked' : '' ) ?> />
                    </td>
                </tr>
                <tr>
                    <td>Push DT Updates</td>
                    <td>
                        <input type="checkbox" id="mc_main_col_connect_dt_push_sync_feed"
                               name="mc_main_col_connect_dt_push_sync_feed" <?php echo esc_attr( $this->is_push_dt_sync_enabled() ? 'checked' : '' ) ?> />
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: middle;">New DT Records Assigned User</td>
                    <td>
                        <select style="min-width: 100%;" id="mc_main_col_connect_dt_new_record_assigned_user"
                                name="mc_main_col_connect_dt_new_record_assigned_user">
                            <option disabled selected value>-- select default assigned user --</option>

                            <?php
                            $users = Disciple_Tools_Users::get_assignable_users_compact();
                            if ( ! empty( $users ) && ! is_wp_error( $users ) ) {
                                $assigned_user = $this->fetch_dt_assigned_user_id();
                                foreach ( $users as $user ) {
                                    $selected = ( intval( $user['ID'] ) === intval( $assigned_user ) ) ? 'selected' : '';
                                    echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $user['ID'] ) . '">' . esc_attr( $user['name'] ) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <br>
            <span style="float:right;">
                <button type="submit"
                        class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></button>
            </span>
        </form>
        <?php
    }

    private function main_column_available_mc_lists() {
        ?>
        <select style="min-width: 80%;" id="mc_main_col_available_mc_lists_select_mc_list">

            <option disabled selected value>-- available mailchimp lists --</option>

            <?php
            $supported_lists          = json_decode( $this->fetch_mc_supported_lists() );
            $current_backend_mc_lists = Disciple_Tools_Mailchimp_API::get_lists();
            if ( ! empty( $current_backend_mc_lists ) ) {
                foreach ( $current_backend_mc_lists as $list ) {

                    // No need to display already supported lists
                    if ( ! isset( $supported_lists->{$list->id} ) ) {
                        echo '<option value="' . esc_attr( $list->id ) . '">' . esc_attr( $list->name ) . '</option>';
                    }
                }
            }
            ?>
        </select>

        <span style="float:right;">
            <a id="mc_main_col_available_mc_lists_select_mc_list_add"
               class="button float-right"><?php esc_html_e( "Add", 'disciple_tools' ) ?></a>
        </span>

        <form method="POST" id="mc_main_col_available_form">
            <input type="hidden" id="mc_main_col_available_nonce" name="mc_main_col_available_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_available_nonce' ) ) ?>"/>

            <input type="hidden" value="" id="mc_main_col_available_selected_list_id"
                   name="mc_main_col_available_selected_list_id"/>

            <input type="hidden" value="" id="mc_main_col_available_selected_list_name"
                   name="mc_main_col_available_selected_list_name"/>
        </form>
        <?php
    }

    private function main_column_supported_mc_lists() {
        ?>
        <table id="mc_main_col_support_mc_lists_table" class="widefat striped">
            <thead>
            <tr>
                <th style="vertical-align: middle; text-align: center;">Name</th>
                <th style="vertical-align: middle; text-align: center;">Mappings</th>
                <th style="vertical-align: middle; text-align: center;">Sync Status</th>
                <th style="vertical-align: middle; text-align: center;">MC to DT Last Update</th>
                <th style="vertical-align: middle; text-align: center;">DT to MC Last Update</th>
                <th></th>
            </tr>
            </thead>
            <?php
            $existing_mappings = ! empty( get_option( 'dt_mailchimp_mappings' ) ) ? json_decode( get_option( 'dt_mailchimp_mappings' ) ) : json_decode( '{}' );
            $supported_lists   = json_decode( $this->fetch_mc_supported_lists() );
            if ( ! empty( $supported_lists ) ) {
                foreach ( $supported_lists as $list ) {
                    echo '<tr>';
                    echo '<td style="vertical-align: middle; text-align: center;">' . esc_attr( $list->name ) . '</td>';

                    $mapping_action = ( ! isset( $existing_mappings->{$list->id} ) || ! isset( $existing_mappings->{$list->id}->mappings ) || count( $existing_mappings->{$list->id}->mappings ) === 0 ) ? 'Create Mappings' : 'View';
                    echo '<td style="vertical-align: middle; text-align: center;"><a href="admin.php?page=disciple_tools_mailchimp&tab=mappings&gen_tab_mc_list_id=' . esc_attr( $list->id ) . '">' . esc_attr( $mapping_action ) . '</a></td>';

                    echo '<td style="vertical-align: middle; text-align: center;">' . esc_attr( $this::main_column_supported_mc_lists_logging( $list->id ) ) . '</td>';

                    $mc_to_dt_last_run = ! empty( $list->mc_to_dt_last_sync_run ) ? dt_format_date( $list->mc_to_dt_last_sync_run, 'long' ) : '';
                    echo '<td style="vertical-align: middle; text-align: center;">' . esc_attr( $mc_to_dt_last_run ) . '</td>';

                    $dt_to_mc_last_run = ! empty( $list->dt_to_mc_last_sync_run ) ? dt_format_date( $list->dt_to_mc_last_sync_run, 'long' ) : '';
                    echo '<td style="vertical-align: middle; text-align: center;">' . esc_attr( $dt_to_mc_last_run ) . '</td>';

                    echo '<td style="vertical-align: middle;">';
                    echo '<span style="float:right;"><a class="button float-right mc-main-col-support-mc-lists-table-row-remove-but">Remove</a></span>';
                    echo '<input type="hidden" id="mc_main_col_support_mc_lists_table_row_remove_hidden_id" value="' . esc_attr( $list->id ) . '">';
                    echo '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </table>
        <br>

        <form method="POST" id="mc_main_col_support_form">
            <input type="hidden" id="mc_main_col_support_nonce" name="mc_main_col_support_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_support_nonce' ) ) ?>"/>

            <input type="hidden" id="mc_main_col_support_mc_lists_hidden_current_mc_list"
                   name="mc_main_col_support_mc_lists_hidden_current_mc_list"
                   value="<?php echo esc_attr( $this->fetch_mc_supported_lists() ) ?>"/>
        </form>
        <?php
    }

    private function main_column_supported_mc_lists_logging( $mc_list_id ): string {

        // Field Mappings Needed
        $existing_mappings = ! empty( get_option( 'dt_mailchimp_mappings' ) ) ? json_decode( get_option( 'dt_mailchimp_mappings' ) ) : json_decode( '{}' );
        if ( ! isset( $existing_mappings->{$mc_list_id} ) || ! isset( $existing_mappings->{$mc_list_id}->mappings ) || count( $existing_mappings->{$mc_list_id}->mappings ) === 0 ) {
            return 'Field Mappings Needed';
        }

        // Sync Scheduled
        $supported_lists = json_decode( $this->fetch_mc_supported_lists() );
        if ( isset( $supported_lists->{$mc_list_id} ) ) {
            if ( empty( $supported_lists->{$mc_list_id}->mc_to_dt_last_sync_run ) && empty( $supported_lists->{$mc_list_id}->dt_to_mc_last_sync_run ) ) {
                return 'Sync Scheduled';
            }

            // Logging Detected
            if ( ! empty( $supported_lists->{$mc_list_id}->log ) ) {
                return $supported_lists->{$mc_list_id}->log;
            }

            // Last Synced at X
            $global_ts_dt_to_mc = get_option( 'dt_mailchimp_sync_last_run_ts_dt_to_mc' );
            $global_ts_mc_to_dt = get_option( 'dt_mailchimp_sync_last_run_ts_mc_to_dt' );
            if ( ! empty( $global_ts_mc_to_dt ) && ! empty( $global_ts_dt_to_mc ) ) {
                if ( $global_ts_mc_to_dt >= $global_ts_dt_to_mc ) {
                    return 'Last Synced at ' . dt_format_date( $global_ts_mc_to_dt, 'long' );
                } else {
                    return 'Last Synced at ' . dt_format_date( $global_ts_dt_to_mc, 'long' );
                }
            }

            if ( ! empty( $global_ts_mc_to_dt ) ) {
                return 'Last Synced at ' . dt_format_date( $global_ts_mc_to_dt, 'long' );
            }

            if ( ! empty( $global_ts_dt_to_mc ) ) {
                return 'Last Synced at ' . dt_format_date( $global_ts_dt_to_mc, 'long' );
            }
        }

        return '---';
    }

    private function main_column_supported_dt_post_types() {
        ?>
        <select style="min-width: 80%;" id="mc_main_col_support_dt_post_types_select_ele">

            <option disabled selected value>-- select supported post types --</option>

            <?php
            $post_types = DT_Posts::get_post_types();
            if ( ! empty( $post_types ) ) {
                foreach ( $post_types as $post_type ) {
                    $post_type_settings = DT_Posts::get_post_settings( $post_type );
                    echo '<option value="' . esc_attr( $post_type ) . '">' . esc_attr( $post_type_settings['label_plural'] ) . '</option>';
                }
            }
            ?>
        </select>

        <span style="float:right;">
            <a id="mc_main_col_support_dt_post_types_select_ele_add"
               class="button float-right"><?php esc_html_e( "Add", 'disciple_tools' ) ?></a>
        </span>
        <br><br>

        <table id="mc_main_col_support_dt_post_types_table" class="widefat striped">
            <tbody>
            <?php $this->main_column_supported_dt_types_display_saved_types( true, 'mc_main_col_support_dt_post_types_table', 'mc_main_col_support_dt_post_types_form', 'mc_main_col_support_dt_post_types_hidden', ! empty( get_option( 'dt_mailchimp_dt_supported_post_types' ) ) ? json_decode( get_option( 'dt_mailchimp_dt_supported_post_types' ) ) : json_decode( '[]' ) ); ?>
            </tbody>
        </table>
        <br>

        <form method="POST" id="mc_main_col_support_dt_post_types_form">
            <input type="hidden" id="mc_main_col_support_dt_post_types_nonce"
                   name="mc_main_col_support_dt_post_types_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_support_dt_post_types_nonce' ) ) ?>"/>

            <input type="hidden" id="mc_main_col_support_dt_post_types_hidden"
                   name="mc_main_col_support_dt_post_types_hidden" value="[]"/>
        </form>
        <?php
    }

    private function main_column_supported_dt_field_types() {
        ?>
        <select style="min-width: 80%;" id="mc_main_col_support_dt_field_types_select_ele">

            <option disabled selected value>-- select supported field types --</option>

            <?php
            $post_types = DT_Posts::get_post_types();
            if ( ! empty( $post_types ) ) {
                $supported_dt_field_types = array();
                foreach ( $post_types as $post_type ) {
                    $post_type_settings = DT_Posts::get_post_settings( $post_type );
                    foreach ( $post_type_settings['fields'] as $field ) {
                        if ( ! in_array( $field['type'], $supported_dt_field_types ) ) {
                            $supported_dt_field_types[] = $field['type'];
                            echo '<option value="' . esc_attr( $field['type'] ) . '">' . esc_attr( $field['type'] ) . '</option>';
                        }
                    }
                }
            }
            ?>
        </select>

        <span style="float:right;">
            <a id="mc_main_col_support_dt_field_types_select_ele_add"
               class="button float-right"><?php esc_html_e( "Add", 'disciple_tools' ) ?></a>
        </span>
        <br><br>

        <table id="mc_main_col_support_dt_field_types_table" class="widefat striped">
            <tbody>
            <?php $this->main_column_supported_dt_types_display_saved_types( false, 'mc_main_col_support_dt_field_types_table', 'mc_main_col_support_dt_field_types_form', 'mc_main_col_support_dt_field_types_hidden', ! empty( get_option( 'dt_mailchimp_dt_supported_field_types' ) ) ? json_decode( get_option( 'dt_mailchimp_dt_supported_field_types' ) ) : json_decode( '[]' ) ); ?>
            </tbody>
        </table>
        <br>

        <form method="POST" id="mc_main_col_support_dt_field_types_form">
            <input type="hidden" id="mc_main_col_support_dt_field_types_nonce"
                   name="mc_main_col_support_dt_field_types_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_support_dt_field_types_nonce' ) ) ?>"/>

            <input type="hidden" id="mc_main_col_support_dt_field_types_hidden"
                   name="mc_main_col_support_dt_field_types_hidden" value="[]"/>
        </form>
        <?php
    }

    private function main_column_supported_dt_types_display_saved_types( $is_post_types, $dt_type_table, $dt_type_form, $dt_type_hidden_values, $supported_types ) {
        /*
         * Revert to and save defaults if no previous configs are detected.
         * For example, initial setups!
         */

        if ( $is_post_types && count( $supported_types ) === 0 ) {
            $supported_types = $this->default_dt_types( $is_post_types );
            update_option( 'dt_mailchimp_dt_supported_post_types', json_encode( $supported_types ) );
        }

        if ( ! $is_post_types && count( $supported_types ) === 0 ) {
            $supported_types = $this->default_dt_types( $is_post_types );
            update_option( 'dt_mailchimp_dt_supported_field_types', json_encode( $supported_types ) );
        }

        /*
         * Proceed with displaying of supported types.
         */

        foreach ( $supported_types as $type ) {
            echo '<tr>';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_table_hidden" value="' . esc_attr( $dt_type_table ) . '" />';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_form_hidden" value="' . esc_attr( $dt_type_form ) . '" />';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_values_hidden" value="' . esc_attr( $dt_type_hidden_values ) . '" />';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_id_hidden" value="' . esc_attr( $type->id ) . '" />';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_name_hidden" value="' . esc_attr( $type->name ) . '" />';
            echo '<td style="vertical-align: middle;">';
            echo esc_attr( $type->name );
            echo '</td>';
            echo '<td>';
            echo '<span style="float:right;"><a class="button float-right mc-main-col-support-dt-type-table-row-remove-but">Remove</a></span>';
            echo '</td>';
            echo '</tr>';
        }
    }

    private function default_dt_types( $is_post_types ): array {

        if ( $is_post_types ) {
            return [
                (object) [
                    'id'   => 'contacts',
                    'name' => 'Contacts'
                ]
            ];

        } else {
            return [
                (object) [
                    'id'   => 'text',
                    'name' => 'text'
                ],
                (object) [
                    'id'   => 'textarea',
                    'name' => 'textarea'
                ],
                (object) [
                    'id'   => 'boolean',
                    'name' => 'boolean'
                ],
                (object) [
                    'id'   => 'key_select',
                    'name' => 'key_select'
                ],
                (object) [
                    'id'   => 'multi_select',
                    'name' => 'multi_select'
                ],
                (object) [
                    'id'   => 'tags',
                    'name' => 'tags'
                ],
                (object) [
                    'id'   => 'communication_channel',
                    'name' => 'communication_channel'
                ],
                (object) [
                    'id'   => 'number',
                    'name' => 'number'
                ],
                (object) [
                    'id'   => 'date',
                    'name' => 'date'
                ]
            ];
        }
    }
}


/**
 * Class Disciple_Tools_Mailchimp_Tab_Mappings
 */
class Disciple_Tools_Mailchimp_Tab_Mappings {
    public function content() {
        // First, handle update submissions
        $this->process_updates();

        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php

        // Load scripts
        $this->load_scripts();
    }

    private function load_scripts() {
        wp_enqueue_script( 'dt_mailchimp_admin_mappings_scripts', plugin_dir_url( __FILE__ ) . 'js/scripts-admin-mappings.js', [
            'jquery',
            'lodash'
        ], 1, true );
    }

    private function process_updates() {
        // Handle Selected List Mappings Updates
        $this->update_selected_list_mappings();
    }

    private function fetch_mc_supported_lists(): string {
        $supported_lists = get_option( 'dt_mailchimp_mc_supported_lists' );

        return ! empty( $supported_lists ) ? $supported_lists : '{}';
    }

    private function fetch_selected_list(): string {

        /**
         * Standard tab view load
         */
        if ( isset( $_POST['mc_mappings_main_col_supported_mc_lists_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_mappings_main_col_supported_mc_lists_nonce'] ) ), 'mc_mappings_main_col_supported_mc_lists_nonce' ) ) {
            $selected_list = isset( $_POST['mc_mappings_main_col_supported_mc_lists_select_ele'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_mappings_main_col_supported_mc_lists_select_ele'] ) ) : '';

            return ! empty( $selected_list ) ? $selected_list : '';
        }

        /**
         * Also ensure mapping updates re-open selected mc list view
         */
        if ( isset( $_POST['mc_mappings_main_col_selected_mc_list_update_form_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_mappings_main_col_selected_mc_list_update_form_nonce'] ) ), 'mc_mappings_main_col_selected_mc_list_update_form_nonce' ) ) {
            if ( isset( $_POST['mc_mappings_main_col_selected_mc_list_mappings_hidden'] ) ) {
                $updated_mappings = json_decode( sanitize_text_field( wp_unslash( $_POST['mc_mappings_main_col_selected_mc_list_mappings_hidden'] ) ) );

                return $updated_mappings->mc_list_id;
            }
        }

        /**
         * Support direct GET requests from General Tab
         */

        if ( isset( $_GET['gen_tab_mc_list_id'] ) ) {
            return sanitize_text_field( wp_unslash( $_GET['gen_tab_mc_list_id'] ) );
        }

        return '';
    }

    private function update_selected_list_mappings() {

        /**
         * If a valid mapping posting is detected, then update existing
         * mappings with new updates.
         */

        if ( isset( $_POST['mc_mappings_main_col_selected_mc_list_update_form_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_mappings_main_col_selected_mc_list_update_form_nonce'] ) ), 'mc_mappings_main_col_selected_mc_list_update_form_nonce' ) ) {
            if ( isset( $_POST['mc_mappings_main_col_selected_mc_list_mappings_hidden'] ) ) {

                // Decode respective json objects
                $updated_mappings  = json_decode( sanitize_text_field( wp_unslash( $_POST['mc_mappings_main_col_selected_mc_list_mappings_hidden'] ) ) );
                $existing_mappings = ! empty( get_option( 'dt_mailchimp_mappings' ) ) ? json_decode( get_option( 'dt_mailchimp_mappings' ) ) : json_decode( '{}' );

                // Update corresponding list entry
                $existing_mappings->{$updated_mappings->mc_list_id} = $updated_mappings;

                // Save updated mappings
                update_option( 'dt_mailchimp_mappings', json_encode( $existing_mappings ) );
            }
        }
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Update Field Mappings</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_update_field_mappings(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php

        // Display list details if selected
        $selected_list = $this->fetch_selected_list();
        if ( ! empty( $selected_list ) ) {
            $this->main_column_display_selected_list( $selected_list );
        }
    }

    public function right_column() {
        ?>
        <div id="mappings_option_div" style="display: none;"></div>
        <?php

        // List available mapping option views; which will be displayed on selection
        include 'mappings-option-field-sync-direction.php';
    }

    private function main_column_update_field_mappings() {
        ?>
        <form method="POST">
            <input type="hidden" id="mc_mappings_main_col_supported_mc_lists_nonce"
                   name="mc_mappings_main_col_supported_mc_lists_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_mappings_main_col_supported_mc_lists_nonce' ) ) ?>"/>

            <select style="min-width: 80%;" id="mc_mappings_main_col_supported_mc_lists_select_ele"
                    name="mc_mappings_main_col_supported_mc_lists_select_ele">
                <option disabled selected value>-- select supported mailchimp list --</option>
                <?php
                $supported_lists = json_decode( $this->fetch_mc_supported_lists() );
                if ( ! empty( $supported_lists ) ) {
                    foreach ( $supported_lists as $list ) {
                        echo '<option value="' . esc_attr( $list->id ) . '">' . esc_attr( $list->name ) . '</option>';
                    }
                }
                ?>
            </select>

            <span style="float:right;">
                <button type="submit"
                        class="button float-right"><?php esc_html_e( "Select", 'disciple_tools' ) ?></button>
            </span>
        </form>
        <?php
    }

    private function main_column_display_selected_list( $mc_list_id ) {
        $name         = Disciple_Tools_Mailchimp_API::get_list_name( $mc_list_id );
        $mc_list_name = ( ! empty( $name ) ) ? $name : 'Selected Mailchimp List';
        ?>
        <!-- Hidden Metadata -->
        <input type="hidden" id="mc_mappings_main_col_selected_mc_list_id_hidden"
               value="<?php echo esc_attr( $mc_list_id ); ?>"/>
        <input type="hidden" id="mc_mappings_main_col_selected_mc_list_name_hidden"
               value="<?php echo esc_attr( $mc_list_name ); ?>"/>
        <!-- Hidden Metadata -->

        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php echo esc_attr( $mc_list_name ); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_display_selected_list_field_mappings( $mc_list_id, $mc_list_name ); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    private function main_column_display_selected_list_field_mappings( $mc_list_id, $mc_list_name ) {
        ?>
        Ensure post type to be assigned with Mailchimp list is selected. Please note, any future post type assignment changes will delete/reset previous field mappings.
        <br><br>

        <select style="min-width: 100%;" id="mc_mappings_main_col_selected_mc_list_assigned_post_type">
            <option disabled selected value>-- select assigned post type --</option>
            <?php

            // First, attempt to locate any previous mappings
            $existing_mappings = ! empty( get_option( 'dt_mailchimp_mappings' ) ) ? json_decode( get_option( 'dt_mailchimp_mappings' ) ) : json_decode( '{}' );

            // Revert to defaults if no mappings are detected
            if ( ! isset( $existing_mappings->{$mc_list_id} ) || ! isset( $existing_mappings->{$mc_list_id}->mappings ) || count( $existing_mappings->{$mc_list_id}->mappings ) === 0 ) {
                $existing_mappings = $this->default_mappings( $mc_list_id, $mc_list_name, $existing_mappings );
            }

            // Source assigned post type
            $assigned_post_type = '';
            if ( isset( $existing_mappings->{$mc_list_id} ) ) {
                $assigned_post_type = $existing_mappings->{$mc_list_id}->dt_post_type ?? '';
            }

            // List available post types, ensuring to pre-select an already assigned type
            $supported_post_types = ! empty( get_option( 'dt_mailchimp_dt_supported_post_types' ) ) ? json_decode( get_option( 'dt_mailchimp_dt_supported_post_types' ) ) : json_decode( '[]' );
            if ( ! empty( $supported_post_types ) ) {
                foreach ( $supported_post_types as $post_type ) {
                    $selected = ( $assigned_post_type === $post_type->id ) ? 'selected' : '';
                    echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $post_type->id ) . '">' . esc_attr( $post_type->name ) . '</option>';
                }
            }
            ?>
        </select>
        <br><br>

        Ensure Mailchimp interest category group fields are only mapped with DT multi_select types. No other type pairings will be sync'd at this time.
        <br><br>

        <span style="float:right;">
            <a id="mc_mappings_main_col_selected_mc_list_add_mapping_but"
               class="button float-right"><?php esc_html_e( "Add Mapping", 'disciple_tools' ) ?></a>
        </span>
        <br><br>

        <!-- Hidden Metadata -->
        <input type="hidden" id="mc_mappings_main_col_selected_mc_list_fields_hidden"
               value="<?php echo esc_attr( json_encode( $this->main_column_display_selected_list_field_mappings_parsed_mc_fields( $mc_list_id ) ) ); ?>"/>
        <input type="hidden" id="mc_mappings_main_col_selected_mc_list_fields_prefix_interest_categories_hidden"
               value="<?php echo esc_attr( Disciple_Tools_Mailchimp_API::get_list_interest_categories_field_prefix() ); ?>"/>
        <input type="hidden" id="mc_mappings_main_col_selected_mc_list_dt_fields_hidden"
               value="<?php echo esc_attr( json_encode( $this->main_column_display_selected_list_field_mappings_parsed_dt_fields() ) ); ?>"/>
        <!-- Hidden Metadata -->

        <table id="mc_mappings_main_col_selected_mc_list_mappings_table" class="widefat striped">
            <thead>
            <tr>
                <th style="text-align: center;">Mapping ID</th>
                <th style="text-align: center;">MC Fields</th>
                <th style="text-align: center;">DT Fields</th>
                <th style="text-align: center;">Options</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php $this->main_column_display_selected_list_field_mappings_display_saved_mappings( $mc_list_id ); ?>
            </tbody>
        </table>

        <br>
        <form method="POST" id="mc_mappings_main_col_selected_mc_list_update_form">
            <input type="hidden" id="mc_mappings_main_col_selected_mc_list_update_form_nonce"
                   name="mc_mappings_main_col_selected_mc_list_update_form_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_mappings_main_col_selected_mc_list_update_form_nonce' ) ) ?>"/>

            <!-- Hidden Metadata -->
            <input type="hidden" id="mc_mappings_main_col_selected_mc_list_mappings_hidden"
                   name="mc_mappings_main_col_selected_mc_list_mappings_hidden"
                   value="{}"/>
            <!-- Hidden Metadata -->

            <span style="float:right;">
                <a id="mc_mappings_main_col_selected_mc_list_update_but"
                   class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></a>
            </span>
        </form>
        <?php
    }

    private function main_column_display_selected_list_field_mappings_parsed_mc_fields( $mc_list_id ): array {
        $mc_list_fields_parsed = [];

        // Merge Fields
        $mc_list_fields = Disciple_Tools_Mailchimp_API::get_list_fields( $mc_list_id );
        if ( ! empty( $mc_list_fields ) ) {
            foreach ( $mc_list_fields as $field ) {
                $mc_list_fields_parsed[] = (object) [
                    "merge_id" => $field->tag,
                    "name"     => $field->name
                ];
            }
        }

        // Interest Categories
        $mc_list_interest_categories = Disciple_Tools_Mailchimp_API::get_list_interest_categories( $mc_list_id );
        if ( ! empty( $mc_list_interest_categories ) ) {
            $prefix = Disciple_Tools_Mailchimp_API::get_list_interest_categories_field_prefix();
            foreach ( $mc_list_interest_categories as $category ) {
                $mc_list_fields_parsed[] = (object) [
                    "merge_id" => $prefix . '' . $category->cat_id,
                    "name"     => $category->cat_title
                ];
            }
        }

        return $mc_list_fields_parsed;
    }

    private function main_column_display_selected_list_field_mappings_parsed_dt_fields(): array {
        $dt_post_type_fields_parsed = [];
        $supported_post_types       = $this->main_column_display_selected_list_field_mappings_supported_dt_types( 'dt_mailchimp_dt_supported_post_types' );
        $supported_field_types      = $this->main_column_display_selected_list_field_mappings_supported_dt_types( 'dt_mailchimp_dt_supported_field_types' );

        // Fetch available post types
        $post_types = DT_Posts::get_post_types();
        if ( ! empty( $post_types ) ) {
            foreach ( $post_types as $type ) {

                // Only process supported post types
                if ( in_array( $type, $supported_post_types ) ) {

                    // For each post type, fetch associated fields; filtering accordingly
                    $post_type        = DT_Posts::get_post_settings( $type );
                    $post_type_fields = [];
                    if ( ! empty( $post_type['fields'] ) ) {
                        foreach ( $post_type['fields'] as $key => $field ) {
                            if ( in_array( $field['type'], $supported_field_types ) ) {
                                $post_type_fields[] = (object) [
                                    "id"   => $key,
                                    "type" => $field['type'],
                                    "name" => $field['name']
                                ];
                            }
                        }
                    }

                    // Package into a nice, neat post type object
                    $dt_post_type_fields_parsed[] = (object) [
                        "post_type_id"     => $type,
                        "post_type_label"  => $post_type['label_plural'],
                        "post_type_fields" => $post_type_fields
                    ];
                }
            }
        }

        return $dt_post_type_fields_parsed;
    }

    private function main_column_display_selected_list_field_mappings_supported_dt_types( $dt_type ): array {
        $types           = [];
        $supported_types = ! empty( get_option( $dt_type ) ) ? json_decode( get_option( $dt_type ) ) : json_decode( '[]' );
        foreach ( $supported_types as $type ) {
            $types[] = $type->id;
        }

        return $types;
    }

    private function main_column_display_selected_list_field_mappings_display_saved_mappings( $mc_list_id ) {
        $existing_mappings = ! empty( get_option( 'dt_mailchimp_mappings' ) ) ? json_decode( get_option( 'dt_mailchimp_mappings' ) ) : json_decode( '{}' );
        if ( isset( $existing_mappings->{$mc_list_id} ) ) {
            foreach ( $existing_mappings->{$mc_list_id}->mappings as $mapping ) {
                ?>
                <tr>
                    <!-- Mapping ID -->
                    <input type="hidden" id="mc_mappings_main_col_selected_mc_list_mappings_table_row_mapping_id_hidden"
                           value="<?php echo esc_attr( $mapping->mapping_id ) ?>"/>

                    <td style="text-align: center;"><?php echo esc_attr( $mapping->mapping_id ) ?></td>

                    <!-- MC Fields -->
                    <td style="text-align: center;">
                        <select id="mc_mappings_main_col_selected_mc_list_mappings_table_col_mc_fields_select_ele"
                                style="max-width: 100px;">
                            <?php
                            // Since the introduction of mc interest category field support; ensure a distinction is made within dropdown!
                            $mc_fields = $this->main_column_display_selected_list_field_mappings_parsed_mc_fields( $mc_list_id );

                            $mc_merge_fields        = [];
                            $mc_interest_categories = [];

                            $prefix_interest_categories = Disciple_Tools_Mailchimp_API::get_list_interest_categories_field_prefix();

                            // Filter different mc field types
                            foreach ( $mc_fields as $mc_field ) {
                                if ( substr( $mc_field->merge_id, 0, strlen( $prefix_interest_categories ) ) === $prefix_interest_categories ) {
                                    $mc_interest_categories[] = $mc_field;

                                } else {
                                    $mc_merge_fields[] = $mc_field;
                                }
                            }

                            // Merge Fields
                            if ( ! empty( $mc_merge_fields ) ) {
                                echo '<option disabled value>-- Default Fields --</option>';
                                foreach ( $mc_merge_fields as $merge_field ) {
                                    $selected = ( $mapping->mc_field_id === $merge_field->merge_id ) ? 'selected' : '';
                                    echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $merge_field->merge_id ) . '">' . esc_attr( $merge_field->name ) . '</option>';
                                }
                            }

                            // Interest Categories
                            if ( ! empty( $mc_interest_categories ) ) {
                                echo '<option disabled value>-- Interest Category Groups --</option>';
                                foreach ( $mc_interest_categories as $int_cat_field ) {
                                    $selected = ( $mapping->mc_field_id === $int_cat_field->merge_id ) ? 'selected' : '';
                                    echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $int_cat_field->merge_id ) . '">' . esc_attr( $int_cat_field->name ) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </td>

                    <!-- DT Fields -->
                    <td style="text-align: center;">
                        <select id="mc_mappings_main_col_selected_mc_list_mappings_table_col_dt_fields_select_ele"
                                style="max-width: 100px;">
                            <?php
                            $dt_fields = $this->main_column_display_selected_list_field_mappings_parsed_dt_fields();
                            foreach ( $dt_fields as $dt_field ) {
                                echo '<option disabled value>-- ' . esc_attr( $dt_field->post_type_label ) . ' --</option>';
                                foreach ( $dt_field->post_type_fields as $field ) {
                                    $selected = ( $mapping->dt_field_id === $field->id ) ? 'selected' : '';
                                    echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $field->id ) . '">' . esc_attr( $field->name ) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </td>

                    <!-- Options -->
                    <td style="text-align: center;">
                        <select id="mc_mappings_main_col_selected_mc_list_mappings_table_col_options_select_ele"
                                class="mc-mappings-main-col-selected-mc-list-mappings-table-col-options-select-ele"
                                style="max-width: 100px;">
                            <option selected value="">-- select option --</option>
                            <option value="field-sync-direction">Field Sync Directions</option>
                        </select>
                        <input id="mc_mappings_main_col_selected_mc_list_mappings_table_col_options_hidden"
                               type="hidden"
                               value="<?php echo esc_attr( json_encode( $mapping->options ) ) ?>"/>
                    </td>

                    <!-- Mapping Removal Button -->
                    <td><span style="float:right;"><a
                                class="button float-right mc-mappings-main-col-selected-mc-list-remove-mapping-but">Remove</a></span>
                    </td>
                </tr>
                <?php
            }
        }
    }

    private function default_mappings( $mc_list_id, $mc_list_name, $existing_mappings ) {

        // Fetch available Mailchimp fields associated with list id
        $mc_list_fields = $this->main_column_display_selected_list_field_mappings_parsed_mc_fields( $mc_list_id );

        // Iterate list, setting default values against pre-defined fields
        $mappings = [];
        foreach ( $mc_list_fields as $field ) {

            if ( ( $field->merge_id === 'EMAIL' ) || ( $field->merge_id === 'FNAME' ) || ( $field->merge_id === 'LNAME' ) ) {

                $dt_field_id = '';
                if ( $field->merge_id === 'EMAIL' ) {
                    $dt_field_id = 'contact_email';

                } elseif ( $field->merge_id === 'FNAME' ) {
                    $dt_field_id = 'dt_mailchimp_fname';

                } elseif ( $field->merge_id === 'LNAME' ) {
                    $dt_field_id = 'dt_mailchimp_lname';

                }

                $epoch      = round( microtime( true ) * 1000 ) - rand( 0, 1000 );
                $mappings[] = (object) [
                    'mapping_id'  => $epoch,
                    'mc_field_id' => $field->merge_id,
                    'dt_field_id' => $dt_field_id,
                    'options'     => []
                ];
            }
        }

        // Save any captured field mappings
        if ( count( $mappings ) > 0 ) {

            $existing_mappings->{$mc_list_id} = (object) [
                'mc_list_id'   => $mc_list_id,
                'mc_list_name' => $mc_list_name,
                'dt_post_type' => 'contacts',
                'mappings'     => $mappings
            ];

            update_option( 'dt_mailchimp_mappings', json_encode( $existing_mappings ) );
        }

        return $existing_mappings;
    }
}


/**
 * Class Disciple_Tools_Mailchimp_Tab_Logging
 */
class Disciple_Tools_Mailchimp_Tab_Logging {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Logging</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_display_logging(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <div id="mappings_option_div" style="display: none;"></div>
        <?php
    }

    public function main_column_display_logging() {
        ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th style="vertical-align: middle; text-align: left; min-width: 150px;">Timestamp</th>
                <th style="vertical-align: middle; text-align: left;">Log</th>
            </tr>
            </thead>
            <?php
            $logs = ! empty( get_option( 'dt_mailchimp_logging' ) ) ? json_decode( get_option( 'dt_mailchimp_logging' ) ) : [];
            if ( ! empty( $logs ) ) {
                $counter = 0;
                $limit   = 500;
                for ( $x = count( $logs ) - 1; $x > 0; $x -- ) {
                    if ( ++ $counter <= $limit ) {
                        echo '<tr>';
                        echo '<td style="vertical-align: middle; text-align: left; min-width: 150px;">' . esc_attr( dt_format_date( $logs[ $x ]->timestamp, 'long' ) ) . '</td>';
                        echo '<td style="vertical-align: middle; text-align: left;">' . esc_attr( $logs[ $x ]->log ) . '</td>';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
            }
            ?>
        </table>
        <?php
    }
}
