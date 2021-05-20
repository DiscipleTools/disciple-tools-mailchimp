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
     * Menu stub. Replaced when Disciple Tools Theme fully loads.
     */
    public function extensions_menu() {
    }

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( ! current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
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
            <h2>DISCIPLE TOOLS : MAILCHIMP</h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'general' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'general' || ! isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">General</a>
                <a href="<?php echo esc_attr( $link ) . 'mappings' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'mappings' || ! isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">Mappings</a>
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
        $this->tab_scripts();
    }

    private function process_updates() {
        // Connectivity Updates
        if ( isset( $_POST['mc_main_col_connect_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_connect_nonce'] ) ), 'mc_main_col_connect_nonce' ) ) {
            update_option( 'dt_mailchimp_mc_api_key', isset( $_POST['mc_main_col_connect_mc_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_connect_mc_api_key'] ) ) : '' );
            update_option( 'dt_mailchimp_mc_accept_sync', isset( $_POST['mc_main_col_connect_mc_accept_sync_feed'] ) ? 1 : 0 );
            update_option( 'dt_mailchimp_dt_push_sync', isset( $_POST['mc_main_col_connect_dt_push_sync_feed'] ) ? 1 : 0 );
        }

        // Supported Mailchimp Lists Updates
        if ( isset( $_POST['mc_main_col_support_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_support_nonce'] ) ), 'mc_main_col_support_nonce' ) ) {
            update_option( 'dt_mailchimp_mc_supported_lists', isset( $_POST['mc_main_col_support_mc_lists_hidden_current_mc_list'] ) ? sanitize_text_field( wp_unslash( $_POST['mc_main_col_support_mc_lists_hidden_current_mc_list'] ) ) : '[]' );
        }

        // Supported Mailchimp Lists With Hidden Identifier Fields
        if ( isset( $_POST['mc_main_col_hidden_mc_list_id_fields_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mc_main_col_hidden_mc_list_id_fields_nonce'] ) ), 'mc_main_col_hidden_mc_list_id_fields_nonce' ) ) {
            if ( isset( $_POST['mc_main_col_hidden_mc_list_fields_select_ele'] ) ) {
                Disciple_Tools_Mailchimp_API::generate_list_hidden_id_fields( sanitize_text_field( wp_unslash( $_POST['mc_main_col_hidden_mc_list_fields_select_ele'] ) ) );
            }
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
        return boolval( get_option( 'dt_mailchimp_mc_accept_sync' ) );
    }

    private function is_push_dt_sync_enabled(): bool {
        return boolval( get_option( 'dt_mailchimp_dt_push_sync' ) );
    }

    private function fetch_mc_supported_lists(): string {
        $supported_lists = get_option( 'dt_mailchimp_mc_supported_lists' );

        return ! empty( $supported_lists ) ? $supported_lists : '[]';
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
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
        <table class="widefat striped">
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
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Supported Mailchimp Lists With Hidden Identifier Fields</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php $this->main_column_hidden_mc_list_id_fields(); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Supported DT Post Types</th>
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
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Supported DT Field Types</th>
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
                    <td>Accept Mailchimp Sync Feeds</td>
                    <td>
                        <input type="checkbox" id="mc_main_col_connect_mc_accept_sync_feed"
                               name="mc_main_col_connect_mc_accept_sync_feed" <?php echo esc_attr( $this->is_accept_mc_sync_enabled() ? 'checked' : '' ) ?> />
                    </td>
                </tr>
                <tr>
                    <td>Push DT Sync Feeds</td>
                    <td>
                        <input type="checkbox" id="mc_main_col_connect_dt_push_sync_feed"
                               name="mc_main_col_connect_dt_push_sync_feed" <?php echo esc_attr( $this->is_push_dt_sync_enabled() ? 'checked' : '' ) ?> />
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

    private function main_column_supported_mc_lists() {
        ?>
        <select style="min-width: 80%;" id="mc_main_col_support_mc_lists_select_mc_list"
                name="mc_main_col_support_mc_lists_select_mc_list">
            <?php
            $current_backend_mc_lists = Disciple_Tools_Mailchimp_API::get_lists();
            if ( ! empty( $current_backend_mc_lists ) ) {
                foreach ( $current_backend_mc_lists as $list ) {
                    echo '<option value="' . esc_attr( $list->id ) . '">' . esc_attr( $list->name ) . '</option>';
                }
            }
            ?>
        </select>

        <span style="float:right;">
            <a id="mc_main_col_support_mc_lists_select_mc_list_add"
               class="button float-right"><?php esc_html_e( "Add", 'disciple_tools' ) ?></a>
        </span>
        <br><br>

        <table id="mc_main_col_support_mc_lists_table" class="widefat striped">
            <?php
            $supported_lists = json_decode( $this->fetch_mc_supported_lists() );
            if ( ! empty( $supported_lists ) ) {
                foreach ( $supported_lists as $list ) {
                    echo '<tr>';
                    echo '<td style="vertical-align: middle;">' . esc_attr( $list->name ) . '</td>';
                    echo '<td>';
                    echo '<span style="float:right;"><a class="button float-right mc-main-col-support-mc-lists-table-row-remove-but">Remove</a></span>';
                    echo '<input type="hidden" id="mc_main_col_support_mc_lists_table_row_remove_hidden_id" value="' . esc_attr( $list->id ) . '">';
                    echo '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </table>
        <br>

        <form method="POST">
            <input type="hidden" id="mc_main_col_support_nonce" name="mc_main_col_support_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_support_nonce' ) ) ?>"/>

            <input type="hidden" id="mc_main_col_support_mc_lists_hidden_current_mc_list"
                   name="mc_main_col_support_mc_lists_hidden_current_mc_list"
                   value="<?php echo esc_attr( $this->fetch_mc_supported_lists() ) ?>"/>

            <span style="float:right;">
                <button type="submit"
                        class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></button>
            </span>
        </form>
        <?php
    }

    private function main_column_supported_dt_post_types() {
        ?>
        <select style="min-width: 80%;" id="mc_main_col_support_dt_post_types_select_ele"
                name="mc_main_col_support_dt_post_types_select_ele">
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
            <?php $this->main_column_supported_dt_types_display_saved_types( ! empty( get_option( 'dt_mailchimp_dt_supported_post_types' ) ) ? json_decode( get_option( 'dt_mailchimp_dt_supported_post_types' ) ) : json_decode( '[]' ) ); ?>
            </tbody>
        </table>
        <br>

        <form method="POST" id="mc_main_col_support_dt_post_types_form">
            <input type="hidden" id="mc_main_col_support_dt_post_types_nonce"
                   name="mc_main_col_support_dt_post_types_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_support_dt_post_types_nonce' ) ) ?>"/>

            <input type="hidden" id="mc_main_col_support_dt_post_types_hidden"
                   name="mc_main_col_support_dt_post_types_hidden" value="[]"/>

            <span style="float:right;">
                <a id="mc_main_col_support_dt_post_types_update_but"
                   class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></a>
            </span>
        </form>
        <?php
    }

    private function main_column_supported_dt_field_types() {
        ?>
        <select style="min-width: 80%;" id="mc_main_col_support_dt_field_types_select_ele"
                name="mc_main_col_support_dt_field_types_select_ele">
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
            <?php $this->main_column_supported_dt_types_display_saved_types( ! empty( get_option( 'dt_mailchimp_dt_supported_field_types' ) ) ? json_decode( get_option( 'dt_mailchimp_dt_supported_field_types' ) ) : json_decode( '[]' ) ); ?>
            </tbody>
        </table>
        <br>

        <form method="POST" id="mc_main_col_support_dt_field_types_form">
            <input type="hidden" id="mc_main_col_support_dt_field_types_nonce"
                   name="mc_main_col_support_dt_field_types_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_support_dt_field_types_nonce' ) ) ?>"/>

            <input type="hidden" id="mc_main_col_support_dt_field_types_hidden"
                   name="mc_main_col_support_dt_field_types_hidden" value="[]"/>

            <span style="float:right;">
                <a id="mc_main_col_support_dt_field_types_update_but"
                   class="button float-right"><?php esc_html_e( "Update", 'disciple_tools' ) ?></a>
            </span>
        </form>
        <?php
    }

    private function main_column_supported_dt_types_display_saved_types( $supported_types ) {
        foreach ( $supported_types as $type ) {
            echo '<tr>';
            echo '<td style="vertical-align: middle;">';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_id_hidden" value="' . esc_attr( $type->id ) . '" />';
            echo '<input type="hidden" id="mc_main_col_support_dt_type_name_hidden" value="' . esc_attr( $type->name ) . '" />';
            echo esc_attr( $type->name );
            echo '</td>';
            echo '<td>';
            echo '<span style="float:right;"><a class="button float-right mc-main-col-support-dt-type-table-row-remove-but">Remove</a></span>';
            echo '</td>';
            echo '</tr>';
        }
    }

    private function main_column_hidden_mc_list_id_fields() {
        $supported_mc_lists_with_id_fields    = [];
        $supported_mc_lists_without_id_fields = [];

        ?>
        <form method="POST" id="mc_main_col_hidden_mc_list_id_fields_form">
            <input type="hidden" id="mc_main_col_hidden_mc_list_id_fields_nonce"
                   name="mc_main_col_hidden_mc_list_id_fields_nonce"
                   value="<?php echo esc_attr( wp_create_nonce( 'mc_main_col_hidden_mc_list_id_fields_nonce' ) ) ?>"/>

            <?php
            /**
             * Fetch supported mc lists and filter by the ones who have not yet
             * generated their hidden id fields. If all lists have hidden id fields, then
             * hide select generation option.
             */

            $supported_mc_lists = json_decode( $this->fetch_mc_supported_lists() );
            if ( ! empty( $supported_mc_lists ) ) {
                foreach ( $supported_mc_lists as $supported_mc_list ) {

                    /**
                     * Determine if list contains hidden fields or not!
                     */

                    if ( Disciple_Tools_Mailchimp_API::has_list_got_hidden_id_fields( $supported_mc_list->id ) ) {
                        $supported_mc_lists_with_id_fields[] = $supported_mc_list;
                    } else {
                        $supported_mc_lists_without_id_fields[] = $supported_mc_list;
                    }
                }
            }

            /**
             * Display supported mc lists which still require hidden id fields.
             */

            if ( count( $supported_mc_lists_without_id_fields ) > 0 ) {
                ?>

                <select style="min-width: 80%;" id="mc_main_col_hidden_mc_list_fields_select_ele"
                        name="mc_main_col_hidden_mc_list_fields_select_ele">

                    <?php
                    foreach ( $supported_mc_lists_without_id_fields as $list_without_fields ) {
                        echo '<option value="' . esc_attr( $list_without_fields->id ) . '">' . esc_attr( $list_without_fields->name ) . '</option>';
                    }
                    ?>

                </select>

                <span style="float:right;">
                <button type="submit" id="mc_main_col_hidden_mc_list_fields_select_ele_generate"
                        class="button float-right"><?php esc_html_e( "Generate", 'disciple_tools' ) ?></button>
                </span>
                <br><br>

                <?php
            }
            ?>
        </form>

        <?php

        /**
         * Display supported mc lists which already contain required hidden id fields.
         */

        if ( count( $supported_mc_lists_with_id_fields ) > 0 ) {

            ?>
            <table id="mc_main_col_hidden_mc_list_fields_table" class="widefat striped">
                <tbody>

                <?php
                foreach ( $supported_mc_lists_with_id_fields as $list_with_fields ) {
                    echo '<tr><td>' . esc_attr( $list_with_fields->name ) . '</td></tr>';
                }
                ?>

                </tbody>
            </table>
            <br>
            <?php
        }
    }

    private function tab_scripts() {
        ?>
        <script>
            jQuery(function ($) {
                $(document).on('click', '#mc_main_col_connect_mc_api_key_show', function () {
                    let api_key_input_ele = $('#mc_main_col_connect_mc_api_key');
                    let api_key_show_ele = $('#mc_main_col_connect_mc_api_key_show');

                    if (api_key_show_ele.is(':checked')) {
                        api_key_input_ele.attr('type', 'text');
                    } else {
                        api_key_input_ele.attr('type', 'password');
                    }
                });

                $(document).on('click', '#mc_main_col_support_mc_lists_select_mc_list_add', function () {
                    mc_list_add();
                });

                $(document).on('click', '.mc-main-col-support-mc-lists-table-row-remove-but', function (e) {
                    mc_list_remove(e);
                });

                $(document).on('click', '#mc_main_col_support_dt_post_types_select_ele_add', function () {
                    dt_type_add(
                        'mc_main_col_support_dt_post_types_select_ele',
                        'mc_main_col_support_dt_post_types_table'
                    );
                });

                $(document).on('click', '#mc_main_col_support_dt_field_types_select_ele_add', function () {
                    dt_type_add(
                        'mc_main_col_support_dt_field_types_select_ele',
                        'mc_main_col_support_dt_field_types_table'
                    );
                });

                $(document).on('click', '.mc-main-col-support-dt-type-table-row-remove-but', function (e) {
                    dt_type_remove(e);
                });

                $(document).on('click', '#mc_main_col_support_dt_post_types_update_but', function () {
                    dt_type_update(
                        'mc_main_col_support_dt_post_types_table',
                        'mc_main_col_support_dt_post_types_form',
                        'mc_main_col_support_dt_post_types_hidden'
                    );
                });

                $(document).on('click', '#mc_main_col_support_dt_field_types_update_but', function () {
                    dt_type_update(
                        'mc_main_col_support_dt_field_types_table',
                        'mc_main_col_support_dt_field_types_form',
                        'mc_main_col_support_dt_field_types_hidden'
                    );
                });

                function mc_list_add() {
                    let selected_list_id = $('#mc_main_col_support_mc_lists_select_mc_list').val();
                    let selected_list_name = $('#mc_main_col_support_mc_lists_select_mc_list option:selected').text();

                    // Only proceed if we have a valid id
                    if (selected_list_id.trim() === "") {
                        return;
                    }

                    // Only add if not already assigned to supported list
                    if (!mc_hidden_list_includes(selected_list_id)) {

                        // Add to hidden current list array
                        let current_list = mc_hidden_list_load();
                        let list_obj = {
                            id: selected_list_id,
                            name: selected_list_name
                        }
                        current_list.push(list_obj);

                        // Persist updates
                        mc_hidden_list_save(current_list);

                        // Update visual list of lists ;)
                        table_row_add(list_obj);
                    }
                }

                function mc_list_remove(evt) {
                    let selected_list_id = evt.currentTarget.parentNode.parentNode.querySelector('#mc_main_col_support_mc_lists_table_row_remove_hidden_id').getAttribute('value');
                    let selected_list_tr_ele = evt.currentTarget.parentNode.parentNode.parentNode;

                    // Remove from hidden current list array
                    mc_hidden_list_remove(selected_list_id);

                    // Update visual list of lists ;)
                    table_row_remove(selected_list_tr_ele);
                }

                function mc_hidden_list_load() {
                    return JSON.parse($('#mc_main_col_support_mc_lists_hidden_current_mc_list').val())
                }

                function mc_hidden_list_save(updated_list) {
                    $('#mc_main_col_support_mc_lists_hidden_current_mc_list').val(JSON.stringify(updated_list));
                }

                function mc_hidden_list_includes(id) {
                    let found = false;
                    let current_list = mc_hidden_list_load();
                    for (let i = 0; i < current_list.length; i++) {
                        if (current_list[i].id == id) {
                            found = true;
                            break;
                        }
                    }
                    return found;
                }

                function mc_hidden_list_remove(id) {
                    let current_list = mc_hidden_list_load();
                    for (let i = 0; i < current_list.length; i++) {
                        if (current_list[i].id == id) {
                            current_list.splice(i, 1);
                            mc_hidden_list_save(current_list);
                            break;
                        }
                    }
                }

                function table_row_add(list_obj) {
                    let html = '<tr>';
                    html += '<td style="vertical-align: middle;">' + window.lodash.escape(list_obj.name) + '</td>';
                    html += '<td>';
                    html += '<span style="float:right;"><a class="button float-right mc-main-col-support-mc-lists-table-row-remove-but">Remove</a></span>';
                    html += '<input type="hidden" id="mc_main_col_support_mc_lists_table_row_remove_hidden_id" value="' + window.lodash.escape(list_obj.id) + '">';
                    html += '</td>';
                    html += '</tr>';
                    $('#mc_main_col_support_mc_lists_table').append(html);
                }

                function table_row_remove(row_ele) {
                    row_ele.parentNode.removeChild(row_ele);
                }

                function dt_type_add(dt_type_select_ele, dt_type_table) {
                    let selected_type_id = $('#' + dt_type_select_ele).val();
                    let selected_type_name = $('#' + dt_type_select_ele + ' option:selected').text();

                    // Only proceed if type has not already been assigned
                    if (dt_type_add_already_assigned(selected_type_id, dt_type_table)) {
                        return;
                    }

                    // Insert new type table row
                    let html = '<tr>';
                    html += '<td style="vertical-align: middle;">';
                    html += '<input type="hidden" id="mc_main_col_support_dt_type_id_hidden" value="' + window.lodash.escape(selected_type_id) + '" />';
                    html += '<input type="hidden" id="mc_main_col_support_dt_type_name_hidden" value="' + window.lodash.escape(selected_type_name) + '" />';
                    html += window.lodash.escape(selected_type_name);
                    html += '</td>';
                    html += '<td>';
                    html += '<span style="float:right;"><a class="button float-right mc-main-col-support-dt-type-table-row-remove-but">Remove</a></span>';
                    html += '</td>';
                    html += '</tr>';
                    $('#' + dt_type_table).append(html);
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

                function dt_type_remove(evt) {
                    let row = evt.currentTarget.parentNode.parentNode.parentNode;
                    row.parentNode.removeChild(row);
                }

                function dt_type_update(dt_type_table, dt_type_form, dt_type_hidden) {
                    let types = [];

                    // Iterate and fetch specified types
                    $('#' + dt_type_table + ' > tbody > tr').each(function (idx, tr) {
                        let dt_type_id = $(tr).find('#mc_main_col_support_dt_type_id_hidden').val();
                        let dt_type_name = $(tr).find('#mc_main_col_support_dt_type_name_hidden').val();

                        types.push({
                            "id": dt_type_id,
                            "name": dt_type_name
                        });
                    });

                    // Save updated types ready for posting
                    $('#' + dt_type_hidden).val(JSON.stringify(types));

                    // Trigger form post..!
                    $('#' + dt_type_form).submit();
                }

            });
        </script>
        <?php
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
        $this->tab_scripts();
    }

    private function process_updates() {
        // Handle Selected List Mappings Updates
        $this->update_selected_list_mappings();
    }

    private function fetch_mc_supported_lists(): string {
        $supported_lists = get_option( 'dt_mailchimp_mc_supported_lists' );

        return ! empty( $supported_lists ) ? $supported_lists : '[]';
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
                    <?php $this->main_column_display_selected_list_field_mappings( $mc_list_id ); ?>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    private function main_column_display_selected_list_field_mappings( $mc_list_id ) {
        ?>
        <select style="min-width: 100%;" id="mc_mappings_main_col_selected_mc_list_assigned_post_type">
            <option disabled selected value>-- select assigned post type --</option>
            <?php
            // First, determine if there is already an assigned post type
            $assigned_post_type = '';
            $existing_mappings  = ! empty( get_option( 'dt_mailchimp_mappings' ) ) ? json_decode( get_option( 'dt_mailchimp_mappings' ) ) : json_decode( '{}' );
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

        <span style="float:right;">
            <a id="mc_mappings_main_col_selected_mc_list_add_mapping_but"
               class="button float-right"><?php esc_html_e( "Add Mapping", 'disciple_tools' ) ?></a>
        </span>
        <br><br>

        <!-- Hidden Metadata -->
        <input type="hidden" id="mc_mappings_main_col_selected_mc_list_fields_hidden"
               value="<?php echo esc_attr( json_encode( $this->main_column_display_selected_list_field_mappings_parsed_mc_fields( $mc_list_id ) ) ); ?>"/>
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
        $mc_list_fields        = Disciple_Tools_Mailchimp_API::get_list_fields( $mc_list_id );
        if ( ! empty( $mc_list_fields ) ) {
            foreach ( $mc_list_fields as $field ) {
                $mc_list_fields_parsed[] = (object) [
                    "merge_id" => $field->tag,
                    "name"     => $field->name
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
                                    "id" => $key,
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
                            $mc_fields = $this->main_column_display_selected_list_field_mappings_parsed_mc_fields( $mc_list_id );
                            foreach ( $mc_fields as $mc_field ) {
                                $selected = ( $mapping->mc_field_id === $mc_field->merge_id ) ? 'selected' : '';
                                echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $mc_field->merge_id ) . '">' . esc_attr( $mc_field->name ) . '</option>';
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

    private function tab_scripts() {
        ?>
        <script>
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
        </script>
        <?php
    }
}

