<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Plugin_Starter_Template_Tile
{
    private static $_instance = null;
    public static function instance(){
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct(){
        add_filter( 'dt_details_additional_tiles', [ $this, "dt_details_additional_tiles" ], 10, 2 );
        add_filter( "dt_custom_fields_settings", [ $this, "dt_custom_fields" ], 1, 2 );
        add_action( "dt_details_additional_section", [ $this, "dt_add_section" ], 30, 2 );
    }

    /**
     * This function registers a new tile to a specific post type
     *
     * @todo Set the post-type to the target post-type (i.e. contacts, groups, trainings, etc.)
     * @todo Change the tile key and tile label
     *
     * @param $tiles
     * @param string $post_type
     * @return mixed
     */
    public function dt_details_additional_tiles( $tiles, $post_type = "" ) {
        if ( $post_type === "contacts" ){
            $tiles["disciple_tools_plugin_starter_template"] = [ "label" => __( "Plugin Starter Template", 'disciple_tools' ) ];
        }
        return $tiles;
    }

    /**
     * @param array $fields
     * @param string $post_type
     * @return array
     */
    public function dt_custom_fields( array $fields, string $post_type = "" ) {
        /**
         * @todo set the post type
         */
        if ( $post_type === "contacts" ){
            /**
             * @todo Add the fields that you want to include in your tile.
             *
             * Examples for creating the $fields array
             * Contacts
             * @link https://github.com/DiscipleTools/disciple-tools-theme/blob/256c9d8510998e77694a824accb75522c9b6ed06/dt-contacts/base-setup.php#L108
             *
             * Groups
             * @link https://github.com/DiscipleTools/disciple-tools-theme/blob/256c9d8510998e77694a824accb75522c9b6ed06/dt-groups/base-setup.php#L83
             */

            /**
             * This is an example of a text field
             */
            $fields['disciple_tools_plugin_starter_template_text'] = [
                'name'        => __( 'Text', 'disciple_tools' ),
                'description' => _x( 'Text', 'Optional Documentation', 'disciple_tools' ),
                'type'        => 'text',
                'default'     => '',
                'tile' => 'disciple_tools_plugin_starter_template',
                'icon' => get_template_directory_uri() . '/dt-assets/images/edit.svg',
            ];
            /**
             * This is an example of a multiselect field
             */
            $fields["disciple_tools_plugin_starter_template_multiselect"] = [
                "name" => __( 'Multiselect', 'disciple_tools' ),
                "default" => [
                    "one" => [ "label" => __( "One", 'disciple_tools' ) ],
                    "two" => [ "label" => __( "Two", 'disciple_tools' ) ],
                    "three" => [ "label" => __( "Three", 'disciple_tools' ) ],
                    "four" => [ "label" => __( "Four", 'disciple_tools' ) ],
                ],
                "tile" => "disciple_tools_plugin_starter_template",
                "type" => "multi_select",
                "hidden" => false,
                'icon' => get_template_directory_uri() . '/dt-assets/images/edit.svg',
            ];
            /**
             * This is an example of a key select field
             */
            $fields["disciple_tools_plugin_starter_template_keyselect"] = [
                'name' => "Key Select",
                'type' => 'key_select',
                "tile" => "disciple_tools_plugin_starter_template",
                'default' => [
                    'new'   => [
                        "label" => _x( 'New', 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "New training added to the system", "Training Status field description", 'disciple_tools' ),
                        'color' => "#ff9800"
                    ],
                    'proposed'   => [
                        "label" => _x( 'Proposed', 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "This training has been proposed and is in initial conversations", "Training Status field description", 'disciple_tools' ),
                        'color' => "#ff9800"
                    ],
                    'scheduled' => [
                        "label" => _x( 'Scheduled', 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "This training is confirmed, on the calendar.", "Training Status field description", 'disciple_tools' ),
                        'color' => "#4CAF50"
                    ],
                    'in_progress' => [
                        "label" => _x( 'In Progress', 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "This training is confirmed, on the calendar, or currently active.", "Training Status field description", 'disciple_tools' ),
                        'color' => "#4CAF50"
                    ],
                    'complete'     => [
                        "label" => _x( "Complete", 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "This training has successfully completed", "Training Status field description", 'disciple_tools' ),
                        'color' => "#4CAF50"
                    ],
                    'paused'       => [
                        "label" => _x( 'Paused', 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "This contact is currently on hold. It has potential of getting scheduled in the future.", "Training Status field description", 'disciple_tools' ),
                        'color' => "#ff9800"
                    ],
                    'closed'       => [
                        "label" => _x( 'Closed', 'Training Status label', 'disciple_tools' ),
                        "description" => _x( "This training is no longer going to happen.", "Training Status field description", 'disciple_tools' ),
                        "color" => "#366184",
                    ],
                ],
                'icon' => get_template_directory_uri() . '/dt-assets/images/edit.svg',
                "default_color" => "#366184",
            ];
        }
        return $fields;
    }

    public function dt_add_section( $section, $post_type ) {
        /**
         * @todo set the post type and the section key that you created in the dt_details_additional_tiles() function
         */
        if ( $post_type === "contacts" && $section === "disciple_tools_plugin_starter_template" ){
            /**
             * These are two sets of key data:
             * $this_post is the details for this specific post
             * $post_type_fields is the list of the default fields for the post type
             *
             * You can pull any query data into this section and display it.
             */
            $this_post = DT_Posts::get_post( $post_type, get_the_ID() );
            $post_type_fields = DT_Posts::get_post_field_settings( $post_type );
            ?>

            <!--
            @todo you can add HTML content to this section.
            -->

            <div class="cell small-12 medium-4">
                <!-- @todo remove this notes section-->
                <strong>You can do a number of customizations here.</strong><br><br>
                All the post-type fields: ( <?php echo '<code>' . esc_html( implode( ', ', array_keys( $post_type_fields ) ) ) . '</code>' ?> )<br><br>
                All the fields for this post: ( <?php echo '<code>' . esc_html( implode( ', ', array_keys( $this_post ) ) ) . '</code>' ?> )<br><br>
            </div>

        <?php }
    }
}
Disciple_Tools_Plugin_Starter_Template_Tile::instance();
