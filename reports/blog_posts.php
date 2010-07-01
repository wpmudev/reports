<?php
/*
Plugin Name: Report - Blog Posts
Plugin URI: 
Description:
Author: Andrew Billits
Version: 1.0.1
Author URI:
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

reports_add_report('Blog Posts','blog-posts','Displays post activity for a blog');

if ( $_GET['report'] == 'blog-posts' ) {
	add_action('view_report','report_blog_posts_ouput');
}

//------------------------------------------------------------------------//
//---Outpur Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function report_blog_posts_ouput(){
	global $wpdb, $current_site;
	switch( $_GET[ 'report-action' ] ) {
		//---------------------------------------------------//
		default:
			?>
			<form name="report" method="POST" action="ms-admin.php?page=reports&action=view-report&report=blog-posts&report-action=view">
				<table class="form-table">
				<tr valign="top">
				<th scope="row"><?php _e('Blog ID') ?></th>
				<td><input type="text" name="blog_ID" id="blog_ID" style="width: 95%" tabindex='1' maxlength="200" value="" />
				<br />
				<?php //_e('') ?></td> 
				</tr>
				<tr valign="top">
				<th scope="row"><?php _e('Period') ?></th>
				<td>
				<select name="period" id="period">
					<option value="15" ><?php _e('15 Days'); ?></option>
					<option value="30" ><?php _e('30 Days'); ?></option>
					<option value="45" ><?php _e('45 Days'); ?></option>
				</select>
				<br /><?php //_e('') ?></td>
				</tr>
				</table>
			<p class="submit">
			<input type="submit" name="Submit" value="<?php _e('View') ?>" />
			</p>
			</form>
			<?php
		break;
		//---------------------------------------------------//
		case "view":
			$blog_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $_POST['blog_ID'] . "'");
			if ( $blog_count == '0' ) {
				?>
                <p><?php _e('Blog not found.'); ?></p>
                <?php
			} else {
				$blog_domain = $wpdb->get_var("SELECT domain FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $_POST['blog_ID'] . "'");
				$blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $_POST['blog_ID'] . "'");
				?>
                <p>
                    <ul>
                        <li><strong><?php _e('Blog'); ?></strong>: <?php echo $_POST['blog_ID']; ?> (<?php echo $blog_domain . $blog_path; ?>)</li>
                        <li><strong><?php _e('Period'); ?></strong>: <?php echo __($_POST['period'] . ' ' . 'Days'); ?></li>
                    </ul>
                </p>
                <?php
				//=======================================//
				$report_data = array();
				$days = 0;
				$total_days = $_POST['period'];
				$total_days_safe = $_POST['period'] + 3;
				$date_format = get_option('date_format');

				$query = "SELECT DATE_FORMAT( date_time, '%Y-%m-%d' ) as formatted_date FROM " . $wpdb->base_prefix . "reports_post_activity WHERE blog_ID = " . $_POST['blog_ID'] . " AND post_type = 'post' AND date_time > '" . reports_days_ago($total_days_safe,'Y-m-d') . " 00:00:00'";
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
				//=======================================//
				include_once(ABSPATH . 'wp-content/report-graphs/open-flash-chart/open-flash-chart.php');
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
				$g->area_hollow(1,3,4,'#3357A0', __('post(s)'),10);
				
				$g->set_tool_tip( '#x_label# <br>#val# #key# ' );
				//------------------------------//
				$g->set_width( '100%' );
				$g->set_height( 250 );
				$g->set_js_path ( 'http://' . $current_site->domain . $current_site->path . 'wp-content/report-graphs/open-flash-chart/js/' );
				$g->set_swf_path ( 'http://' . $current_site->domain . $current_site->path . 'wp-content/report-graphs/open-flash-chart/' );
				$g->set_output_type('js');
				echo $g->render();
				//=======================================//
			}
		break;
		//---------------------------------------------------//
	}
}

?>
