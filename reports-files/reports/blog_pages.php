<?php

$activity_reports->add_report( __( 'Blog Pages', 'reports' ), 'blog-pages', __( 'Displays pages activity for a blog', 'reports' ) );

if ( isset( $_GET['report'] ) && 'blog-pages' == $_GET['report'] )
	add_action( 'view_report','report_blog_pages_ouput' );

function report_blog_pages_ouput(){
	global $wpdb, $current_site;

	$action = isset( $_GET[ 'report-action' ] ) ? $_GET[ 'report-action' ] : '';
	switch( $action ) {
		//---------------------------------------------------//
		default:
			?>
			<form name="report" method="POST" action="?page=reports&action=view-report&report=blog-pages&report-action=view">
				<table class="form-table">
					<?php if ( is_multisite() ) { ?>
					<tr valign="top">
						<th scope="row"><?php _e( 'Blog ID', 'reports' ) ?></th>
						<td><input type="text" name="blog_ID" id="blog_ID" style="width: 95%" tabindex='1' maxlength="200" value="" /></td>
					</tr>
					<?php } else { ?>
						<input type="hidden" name="blog_ID" id="blog_ID" value="0" />
					<?php } ?>
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
					<input type="submit" name="Submit" value="<?php _e( 'View', 'reports' ) ?>" />
				</p>
			</form>
			<?php
		break;
		//---------------------------------------------------//
		case 'view':
			$blog = is_multisite() ? get_blog_details( (int) $_POST['blog_ID'], false ) : true;
			if ( ! $blog ) {
				?>
                <p><?php _e( 'Blog not found.', 'reports' ); ?></p>
                <?php
			}
			if ( $blog ) {
				?>
                <p>
                    <ul>
                        <li><strong><?php _e( 'Blog', 'reports' ); ?></strong>: <?php echo $_POST['blog_ID']; ?> (<?php echo get_site_url( $_POST['blog_ID'] ); ?>)</li>
                        <li><strong><?php _e( 'Period', 'reports' ); ?></strong>: <?php printf( __( '%d Days', 'reports' ), $_POST['period'] ); ?></li>
                    </ul>
                </p>
                <?php
				//=======================================//
				$report_data = array();
				$days = 0;
				$total_days = $_POST['period'];
				$total_days_safe = $_POST['period'] + 3;
				$date_format = get_option('date_format');

				$query = "SELECT DATE_FORMAT( date_time, '%Y-%m-%d' ) as formatted_date FROM " . $wpdb->base_prefix . "reports_post_activity WHERE blog_ID = " . $_POST['blog_ID'] . " AND post_type = 'page' AND date_time > '" . reports_days_ago($total_days_safe,'Y-m-d') . " 00:00:00'";
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
				$g->area_hollow( 1, 3, 4, '#3357A0', __( 'page(s)', 'reports' ), 10 );

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

?>
