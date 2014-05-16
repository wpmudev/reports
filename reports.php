<?php
/*
Plugin Name: Reports
Plugin URI: http://premium.wpmudev.org/project/reports
Description: Displays post and comment activity per blog and per user
Author: WPMU DEV
Version: 1.0.7
Network: true
Author URI: http://premium.wpmudev.org/
WDP ID: 47
*/

/*
Copyright 2007-2014 Incsub (http://incsub.com)
Author - YOUR NAME(s)
Contributors - ADD AS THEY CONTRIBUTE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

if( ! defined( 'REPORTS_PLUGIN_DIR' ) )
	define( 'REPORTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) . 'reports-files/' );

if( ! defined( 'REPORTS_PLUGIN_URL' ) )
	define( 'REPORTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) . 'reports-files/' );

/**
 * Plugin main class
 **/
class Activity_Reports {

	/**
	 * Current version of the plugin
	 **/
	var $version = '1.0.5';

	/**
	 * Available reports
	 **/
	var $available_reports = array();

	/**
	 * PHP4 constructor
	 **/
	function Activity_Reports() {
		__construct();
	}

	/**
	 * PHP5 constructor
	 **/
	function __construct() {
		global $wp_version;

		add_action( 'admin_init', array( &$this, 'make_current' ) );
		add_action( 'admin_head', array( &$this, 'css' ) );

		// Add the admin page
		if ( ! is_multisite() )
			add_action( 'admin_menu', array( &$this, 'admin_page' ) );
		elseif ( version_compare( $wp_version , '3.0.9', '>' ) )
			add_action( 'network_admin_menu', array( &$this, 'network_admin_page' ) );
		else
			add_action( 'admin_menu', array( &$this, 'pre_3_1_network_admin_page' ) );

		// log user data
		//add_action( 'admin_footer', array( &$this, 'user_activity' ) );
		//add_action( 'wp_footer', array( &$this, 'user_activity' ) );
		// log comment data
		add_action( 'comment_post', array( &$this, 'comment_activity' ) );
		add_action( 'delete_comment', array( &$this, 'comment_activity_remove' ) );
		add_action( 'delete_blog', array( &$this, 'comment_activity_remove_blog' ) , 10, 1 );
		// log post data
		add_action( 'save_post', array( &$this, 'post_activity' ) );
		add_action( 'delete_post', array( &$this, 'post_activity_remove' ) );
		add_action( 'delete_blog', array( &$this, 'post_activity_remove_blog' ) , 10, 1 );

		// load text domain
		if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/reports.php' ) ) {
			load_muplugin_textdomain( 'reports', 'reports-files/languages' );
		} else {
			load_plugin_textdomain( 'reports', false, dirname( plugin_basename( __FILE__ ) ) . '/reports-files/languages' );
		}
	}

	function make_current() {
		global $plugin_page;

		if( 'reports' !== $plugin_page )
			return;

		if ( get_site_option( 'reports_version' ) == '' )
			add_site_option( 'reports_version', '0.0.0' );

		if ( get_site_option( 'reports_version' ) !== $this->version ) {
			update_site_option( 'reports_version', $this->version );
			update_site_option( 'reports_installed', 'no' );
		}

		$this->global_install();

		if ( get_option( 'reports_version' ) == '' )
			add_option( 'reports_version', $this->version );

		if ( get_option( 'reports_version' ) !== $this->version )
			update_option( 'reports_version', $this->version );
	}

	function global_install() {
		global $wpdb;

		if ( get_site_option( 'reports_installed' ) == '' )
			add_site_option( 'reports_installed', 'no' );

		if ( get_site_option( 'reports_installed' ) !== 'yes' ) {

			if( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) )
				include_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
			else
				die( __( 'We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'', 'reports' ) );

			$charset_collate = '';
			if( $wpdb->supports_collation() ) {
				if( !empty( $wpdb->charset ) ) {
					$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
				}
				if( !empty( $wpdb->collate ) ) {
					$charset_collate .= " COLLATE $wpdb->collate";
				}
			}

			$user_activity_table = "CREATE TABLE `{$wpdb->base_prefix}reports_user_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`user_ID` bigint(35) NOT NULL default '0',
				`location` TEXT,
				`date_time` datetime NOT NULL default '0000-00-00 00:00:00',
				PRIMARY KEY (`active_ID`)
			) $charset_collate;";

			$post_activity_table = "CREATE TABLE `{$wpdb->base_prefix}reports_post_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`user_ID` bigint(35) NOT NULL default '0',
				`post_ID` bigint(35) NOT NULL default '0',
				`post_type` VARCHAR(255),
				`date_time` datetime NOT NULL default '0000-00-00 00:00:00',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";

			$comment_activity_table = "CREATE TABLE `{$wpdb->base_prefix}reports_comment_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`user_ID` bigint(35) NOT NULL default '0',
				`user_email` VARCHAR(255) default '0',
				`comment_ID` bigint(35) NOT NULL default '0',
				`date_time` datetime NOT NULL default '0000-00-00 00:00:00',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";

			maybe_create_table( "{$wpdb->base_prefix}reports_user_activity", $user_activity_table );
			maybe_create_table( "{$wpdb->base_prefix}reports_post_activity", $post_activity_table );
			maybe_create_table( "{$wpdb->base_prefix}reports_comment_activity", $comment_activity_table );

			update_site_option( 'reports_installed', 'yes' );
		}
	}

	function admin_page() {
		add_submenu_page( 'options-general.php', __( 'Reports', 'reports' ), __( 'Reports', 'reports' ), 'manage_options', 'reports', array( &$this, 'page_output' ) );
	}

	function network_admin_page() {
		add_submenu_page( 'settings.php', __( 'Reports', 'reports' ), __( 'Reports', 'reports' ), 'manage_network_options', 'reports', array( &$this, 'page_output' ) );
	}

	function pre_3_1_network_admin_page() {
		add_submenu_page( 'ms-admin.php', __( 'Reports', 'reports' ), __( 'Reports', 'reports' ), 'manage_network_options', 'reports', array( &$this, 'page_output' ) );
	}

	function add_report( $name, $nicename, $description ) {
		$this->available_reports[] = array( $name, $nicename, $description );
	}

	function user_activity() {
		global $wpdb, $current_user;

		if ( !empty($current_user->ID) ){
			$table = $wpdb->base_prefix . "reports_user_activity";
			$wpdb->query( 
				$wpdb->prepare(
					"INSERT INTO $table (user_ID, location, date_time) 
					VALUES ( %d, '%s', '%s' )",
					$current_user->ID,
					esc_url_raw( $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ),
					current_time( 'mysql', 1 )
				)
			);
		}
	}

	function comment_activity( $comment_ID ) {
		global $wpdb, $current_site;

		$comment_details = get_comment($comment_ID);
		if ( !empty($comment_details->comment_content) ){
			$table = $wpdb->base_prefix . "reports_comment_activity";
			$comment_activity_count = $wpdb->get_var( 
				$wpdb->prepare( 
					"SELECT COUNT(*) FROM $table WHERE blog_ID = %d AND comment_ID = %d",
					$wpdb->blogid,
					$comment_ID
				)
			);
			if ($comment_activity_count == '0') {
				$table = $wpdb->base_prefix . "reports_comment_activity";
				$wpdb->query( 
					$wpdb->prepare(
						"INSERT INTO $table (blog_ID, user_ID, user_email, comment_ID, date_time) 
						VALUES ( %d, %d, '%s', %d, '%s' )",
						$wpdb->blogid,
						$comment_details->user_id,
						$comment_details->comment_author_email,
						$comment_ID,
						current_time( 'mysql', 1 )
					)
				);
			}
		}
	}

	function comment_activity_remove( $comment_ID ) {
		global $wpdb;
		$table = $wpdb->base_prefix . "reports_comment_activity";
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM $table WHERE comment_ID = %d AND blog_ID = %d",
				$comment_ID,
				$wpdb->blogid
			)
		);
	}

	function comment_activity_remove_blog( $blog_ID ) {
		global $wpdb;
		$table = $wpdb->base_prefix . "reports_comment_activity";
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM $table WHERE blog_ID = %d",
				$wpdb->blogid
			)
		);
	}

	function post_activity( $post_ID ) {
		global $wpdb, $current_site;

		$post_details = get_post($post_ID);
		if ( !empty($post_details->post_content) && $post_details->post_type != 'revision' && $post_details->post_status == 'publish' ){
			$table = $wpdb->base_prefix . "reports_post_activity";
			$post_activity_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table  WHERE blog_ID = %d AND post_ID = %d",
					$wpdb->blogid,
					$post_ID
				)
			);
			if ($post_activity_count == '0') {		
				$table = $wpdb->base_prefix . "reports_post_activity";
				$wpdb->query( 
					$wpdb->prepare( 
						"INSERT INTO $table (blog_ID, user_ID, post_ID, post_type, date_time) VALUES ( %d, '%s', %d, '%s', '%s' )",
						$wpdb->blogid,
						$post_details->post_author,
						$post_ID,
						$post_details->post_type,
						current_time( 'mysql', 1 )
					)
				);
			}
		}
	}

	function post_activity_remove( $post_ID ) {
		global $wpdb;
		$table = $wpdb->base_prefix . "reports_post_activity";
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM $table WHERE post_ID = %d AND blog_ID = %d",
				$post_ID,
				$wpdb->blogid
			)
		);
	}

	function post_activity_remove_blog( $blog_ID ) {
		global $wpdb;
		$table = $wpdb->base_prefix . "reports_post_activity";
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM $table WHERE blog_ID = %d",
				$wpdb->blogid
			)
		);
	}

	function css() {
		global $wpdb, $parent_file;
		if ( isset( $_GET['page'] ) && 'reports' == $_GET['page'] ) {
			?>
	<style type="text/css">

	#statchart {
		margin-top: 1em;
		text-align: center;
	}

	.statsdiv{
		width: 44%;
		float: left;
		margin-right: 2%;
		border: 1px solid #eee;
		margin-top: 1.5em;
		padding: 1%;
	}

	.sumdiv {
		width: 55%;
		margin: auto;
		border: 1px solid #eee;
		padding: 1%;
	}

	.sumdiv table {
		margin-bottom: 1em;
		border-bottom: 2px solid #ccc;
		padding-bottom: 1em;
	}

	.statsdiv table, .sumdiv table {
		width: 100%;
	}

	.statsdiv p {
		font-size: 12px;
	}

	#statsdash {
		font-size: 14px;
	}
	#estats {
		background: #fff url("/i/thinblueline.gif") top left repeat-x;
		text-align: right;
		margin: 0 -14px 6px -14px;
		padding: 2px 10px 0 0;
		height: 20px;
	}
	#estats, #estats a, #estats a:visited {
		color: #e8e8f8;
	}
	#estats a:hover {
		background-color: #e8e8f8;
		color: #224;
	}
	.wrap .statsdiv tr.alternate {
		background-color: #E6F0FF;
	}

	.wrap .statsdiv tr, .statsDay tr {
		height: 22px;
	}

	.statsDay th {
		text-align: left;
		border-bottom: 2px solid #ccc;
	}

	.wrap .statsdiv .label, .statsDay .label {
		padding-left: 8px;
	}

	.wrap .statsdiv .more {
		text-align: center;
	}

	.wrap .statsdiv .more a {
		border-bottom: none;
	}

	.views {
		text-align: center;
		width: 6em;
	}

	#generalblog span {
		float: left;
		display: block;
		width: 8em;
	}

	.selector {
		float: right;
	}

	* html { overflow-x: auto; }

	.stat-chart {
		clear:left;
	}
	</style>
			<?php
		}
	}

	function page_output() {
		global $wpdb;

		$available_reports = $this->available_reports;

		if(!current_user_can('manage_options')) {
			echo "<p>Nice Try...</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

		echo '<div class="wrap">';

		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		switch( $action ) {
			//---------------------------------------------------//
			default:

				?>
				<h2><?php _e( 'Reports', 'reports' ) ?></h2>
				<?php
				if ( count( $available_reports ) > 0 ) {
					?>
					<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
					<thead><tr>
					<th scope='col'>Name</th>
					<th scope='col'>Description</th>
					<th scope='col'>Actions</th>
					</tr></thead>
					<tbody id='the-list'>
					<?php
					if ( count( $available_reports ) > 0 ) {
						$class = ( isset( $class ) && 'alternate' == $class ) ? '' : 'alternate';
						foreach ($available_reports as $available_report){
						//=========================================================//
						echo "<tr class='" . $class . "'>";
						echo "<td valign='top'>" . $available_report[0] . "</td>";
						echo "<td valign='top'>" . $available_report[2] . "</td>";
						echo "<td valign='top'><a href='?page=reports&action=view-report&report=" . $available_report[1] . "' rel='permalink' class='edit'>" . __( 'View Report', 'reports' ) . "</a></td>";
						echo "</tr>";
						$class = ('alternate' == $class) ? '' : 'alternate';
						//=========================================================//
						}
					}
					?>
					</tbody></table>
					<?php
				} else {
					?>
						<p><?php _e( 'No reports available', 'reports' ) ?></p>
					<?php
				}
			break;
			//---------------------------------------------------//
			case "view-report":
				foreach ($available_reports as $available_report){
					if ( $available_report[1] == $_GET['report'] ) {
						$report_name = $available_report[0];
						$report_nicename = $available_report[1];
					}
				}
				?>
				<h2><a href="?page=reports" style="text-decoration:none;"><?php _e( 'Reports', 'reports' ) ?></a> &raquo; <a href="?page=reports&action=view-report&report=<?php echo esc_attr($report_nicename); ?>" style="text-decoration:none;"><?php echo esc_html($report_name); ?></a></h2>
				<?php
				do_action('view_report');
			break;
			//---------------------------------------------------//
		}
		echo '</div>';
	}

}
$activity_reports =& new Activity_Reports();

/**
 * Format date
 **/
function reports_days_ago( $n, $date_format ) {
	if ( empty( $date_format ) )
		$date_format = 'Y-m-d H:i:s';

	return date( $date_format, time() - 86400 * $n );
}

/**
 * Load reports.
 **/
include_once( REPORTS_PLUGIN_DIR . 'report-graphs/open-flash-chart/open-flash-chart.php' );

if( is_dir( REPORTS_PLUGIN_DIR . 'reports' ) ) {
	if( $udh = opendir( REPORTS_PLUGIN_DIR . 'reports' ) ) {
		while( ( $report = readdir( $udh ) ) !== false ) {
			if( substr( $report, -4 ) == '.php' ) {
				include_once( REPORTS_PLUGIN_DIR . 'reports/' . $report );
			}
		}
	}
}


/**
 * Show notification if WPMUDEV Update Notifications plugin is not installed
 **/
if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
