<?php

$activity_reports->add_report( __( 'User Comments', 'reports' ), 'user-comments', __( 'Displays comment activity for a user', 'reports' ) );

if ( isset( $_GET['report'] ) && 'user-comments' == $_GET['report'] )
	add_action( 'view_report','report_user_comments_ouput' );

function report_user_comments_ouput() {
	global $wpdb, $current_site;

	$action = isset( $_GET[ 'report-action' ] ) ? $_GET[ 'report-action' ] : '';
	switch( $action ) {
		//---------------------------------------------------//
		default:
			?>
			<form name="report" method="post" action="?page=reports&action=view-report&report=user-comments&report-action=view">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Username', 'reports' ) ?></th>
						<td>
							<input type="text" name="user_login" id="user_login" style="width: 95%" tabindex='1' maxlength="200" value="" />
							<br />
							<?php _e( 'Case Sensitive', 'reports' ) ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Period', 'reports' ) ?></th>
						<td>
							<select name="period" id="period">
								<option value="15" ><?php _e( '15 Days', 'reports' ); ?></option>
								<option value="30" ><?php _e( '30 Days', 'reports' ); ?></option>
								<option value="45" ><?php _e( '45 Days', 'reports' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="<?php _e( 'View', 'reports' ) ?>" />
				</p>
			</form>
			<?php
		break;
		//---------------------------------------------------//
		case 'view':
			$user_login = sanitize_user( $_POST['user_login'] );	
			$period = absint( $_POST['period'] );
			$user = get_user_by( 'login', $user_login );
			if ( ! $user ) {
				?>
                <p><?php _e( 'User not found.', 'reports' ); ?></p>
                <?php
			} else {
				$user_ID = $user->ID;
				?>
                <p>
                    <ul>
                        <li><strong><?php _e( 'Username', 'reports' ); ?></strong>: <?php echo $user_login; ?></li>
                        <li><strong><?php _e( 'Period', 'reports' ); ?></strong>: <?php printf( __( '%d Days', 'reports' ), $period ); ?></li>
                    </ul>
                </p>
                <?php
				//=======================================//
				$report_data = array();
				$days = 0;
				$total_days = $period;
				$total_days_safe = $period + 3;
				$date_format = get_option('date_format');

				$table = $wpdb->base_prefix . "reports_comment_activity";
				$date_time = reports_days_ago( $total_days_safe, 'Y-m-d' );
				$query_date_format = '%Y-%m-%d';

				$query = $wpdb->prepare(
					"SELECT DATE_FORMAT( date_time, '%s' ) as formatted_date 
					FROM $table 
					WHERE user_ID = %d
					AND date_time > '%s'",
					$query_date_format,
					$user_ID,
					$date_time . ' 00:00:00'
				);
			
				$report_results = $wpdb->get_results( $query, ARRAY_A );
				while ( $days <= $total_days ) {
					$count = 0;
					$value = 0;
					$day = reports_days_ago($days,'Y-m-d');
					if ( count( $report_results ) > 0 ) {
						foreach ( $report_results as $report_result ) {
							if ($report_result['formatted_date'] == $day) {
								$count = $count + 1;
							}
						}
					}
					$label = reports_days_ago($days,$date_format);
					$value = $count;
					$report_data[] = array($label,$value);
					$days = $days + 1;
				}

				$report_data = array_reverse($report_data);

				$count = 0;
				$array_labels = array();
				$array_values = array();
				$piwik_api_response = array();
				$piwik_api_response[] = array('1','2');
				foreach ( $report_data as $array_item ) {
					$count = $count + 1;
					if ( $count != 1 ) {
						$array_labels[] = $array_item[0];
						$array_values[] = $array_item[1];
					}
				}
				$label_count = count( $array_labels );
				$highest_value = 0;
				foreach ( $array_values as $value ) {
					if ( $value > $highest_value) {
						$highest_value = $value;
					}
				}
				//=======================================//
				$g = new graph();
				//------------------------------//
				//---Data-----------------------//
				//------------------------------//
				$g->set_data( $array_values );

				//------------------------------//
				//---X--------------------------//
				//------------------------------//
				$g->set_x_labels( $array_labels );
				//------------------------------//
				//---Y--------------------------//
				//------------------------------//
				$g->set_y_min( 0 );
				$g->set_y_max( $highest_value );
				//------------------------------//
				$g->set_num_decimals ( 0 );
				$g->set_is_decimal_separator_comma( false );
				$g->set_is_thousand_separator_disabled( true );
				$g->y_axis_colour = '#ffffff';
				$g->x_axis_colour = '#596171';
				$g->x_grid_colour = $g->y_grid_colour = '#E0E1E4';

				// approx 5 x labels on the graph
				$steps = ceil($label_count / 5);
				$steps = $steps + $steps % 2; // make sure modulo 2

				$g->set_x_label_style( 10, $g->x_axis_colour, 0, $steps, $g->x_grid_colour );
				$g->set_x_axis_steps( $steps / 2 );


				$stepsY = ceil($highest_value / 4);
				$g->y_label_steps( $stepsY / 3 );
				$g->y_label_steps( 4 );

				$g->bg_colour = '#ffffff';
				$g->set_inner_background('#ffffff');
				$g->area_hollow( 1, 3, 4, '#3357A0', __( 'comment(s)', 'reports' ), 10 );

				$g->set_tool_tip( '#x_label# <br>#val# #key# ' );
				//------------------------------//
				$g->set_width( '100%' );
				$g->set_height( 250 );
				$g->set_js_path ( REPORTS_PLUGIN_URL . 'report-graphs/open-flash-chart/js/' );
				$g->set_swf_path ( REPORTS_PLUGIN_URL . 'report-graphs/open-flash-chart/' );
				$g->set_output_type('js');
				echo $g->render();
				//=======================================//
			}
		break;
		//---------------------------------------------------//
	}
}
