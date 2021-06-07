<div id="mappings_option_field_sync_direction" style="display: none;">
    <table class="widefat striped">
        <thead>
        <tr>
            <th id="mappings_option_field_sync_direction_title"></th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <span id="mappings_option_field_sync_direction_mapping_id" style="float:right;"></span>
                <input id="mappings_option_field_sync_direction_option_id_hidden" type="hidden" value=""/>
                <br><br>

                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Enabled</td>
                        <td>
                            <input id="mappings_option_field_sync_direction_enabled"
                                   name="mappings_option_field_sync_direction_enabled"
                                   type="checkbox"/>
                        </td>
                    </tr>
                    <tr>
                        <td>Execution Priority</td>
                        <td>
                            <select id="mappings_option_field_sync_direction_exec_priority"
                                    name="mappings_option_field_sync_direction_exec_priority">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Accept MC Sync Feeds</td>
                        <td>
                            <input id="mappings_option_field_sync_direction_pull_mc"
                                   name="mappings_option_field_sync_direction_pull_mc"
                                   type="checkbox"/>
                        </td>
                    </tr>
                    <tr>
                        <td>Push DT Sync Feeds</td>
                        <td>
                            <input id="mappings_option_field_sync_direction_push_dt"
                                   name="mappings_option_field_sync_direction_push_dt"
                                   type="checkbox"/>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <br><br>
                <span style="float:right;">
                    <a id="mappings_option_field_sync_direction_remove_but"
                       class="button float-right"><?php esc_html_e( "Remove", 'disciple_tools' ) ?></a>
                    <a id="mappings_option_field_sync_direction_commit_but"
                       class="button float-right"><?php esc_html_e( "Commit", 'disciple_tools' ) ?></a>
                </span>
            </td>
        </tr>
        </tbody>
    </table>
</div>
