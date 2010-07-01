<?php
/*
Plugin Name: Reports
Plugin URI: 
Description:
Author: Andrew Billits (Incsub)
Version: 1.0.3
Author URI:
WDP ID: 47
*/

/* 
Copyright 2007-2010 Incsub (http://incsub.com)

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

$reports_current_version = '1.0.3';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
global $available_reports;
// load reports
if( defined( 'MUPLUGINDIR' ) == false ) 
	define( 'MUPLUGINDIR', 'wp-content/mu-plugins' );
if( defined( 'REPORTDIR' ) == false ) 
	define( 'REPORTDIR', '/reports' );
if( is_dir( ABSPATH . MUPLUGINDIR . REPORTDIR ) ) {
	if( $udh = opendir( ABSPATH . MUPLUGINDIR . REPORTDIR ) ) {
		while( ( $report = readdir( $udh ) ) !== false ) {
			if( substr( $report, -4 ) == '.php' ) {
				include_once( ABSPATH . MUPLUGINDIR . REPORTDIR . '/' . $report );
			}
		}
	}
}

//reports plugin
if ($_GET['page'] == 'reports'){
	reports_make_current();
}
add_action('admin_menu', 'reports_plug_pages');
add_action('admin_head','reports_css');
//log user data
add_action('admin_footer', 'reports_user_activity');
add_action('wp_footer', 'reports_user_activity');
//log comment data
add_action('comment_post', 'reports_comment_activity');
add_action('delete_comment', 'reports_comment_activity_remove');
add_action('delete_blog', 'reports_comment_activity_remove_blog', 10, 1);
//log post data
add_action('save_post', 'reports_post_activity');
add_action('delete_post', 'reports_post_activity_remove');
add_action('delete_blog', 'reports_post_activity_remove_blog', 10, 1);
//log blog data
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function reports_make_current() {
	global $wpdb, $reports_current_version;
	if (get_site_option( "reports_version" ) == '') {
		add_site_option( 'reports_version', '0.0.0' );
	}
	
	if (get_site_option( "reports_version" ) == $reports_current_version) {
		// do nothing
	} else {
		//update to current version
		update_site_option( "reports_installed", "no" );
		update_site_option( "reports_version", $reports_current_version );
	}
	reports_global_install();
	//--------------------------------------------------//
	if (get_option( "reports_version" ) == '') {
		add_option( 'reports_version', '0.0.0' );
	}
	
	if (get_option( "reports_version" ) == $reports_current_version) {
		// do nothing
	} else {
		//update to current version
		update_option( "reports_version", $reports_current_version );
		reports_blog_install();
	}
}

function reports_blog_install() {
	global $wpdb, $reports_current_version;
	$reports_hits_table = "";

	//$wpdb->query( $reports_hits_table );
}

function reports_global_install() {
	global $wpdb, $reports_current_version;
	if (get_site_option( "reports_installed" ) == '') {
		add_site_option( 'reports_installed', 'no' );
	}
	
	if (get_site_option( "reports_installed" ) == "yes") {
		// do nothing
	} else {
	
		$reports_table1 = "CREATE TABLE `" . $wpdb->base_prefix . "reports_user_activity` (
  `active_ID` bigint(20) unsigned NOT NULL auto_increment,
  `user_ID` bigint(35) NOT NULL default '0',
  `location` TEXT,
  `date_time` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`active_ID`)
) ENGINE=InnoDB;";
		$reports_table2 = "CREATE TABLE `" . $wpdb->base_prefix . "reports_post_activity` (
  `active_ID` bigint(20) unsigned NOT NULL auto_increment,
  `blog_ID` bigint(35) NOT NULL default '0',
  `user_ID` bigint(35) NOT NULL default '0',
  `post_ID` bigint(35) NOT NULL default '0',
  `post_type` VARCHAR(255),
  `date_time` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`active_ID`)
) ENGINE=InnoDB;";
		$reports_table3 = "CREATE TABLE `" . $wpdb->base_prefix . "reports_comment_activity` (
  `active_ID` bigint(20) unsigned NOT NULL auto_increment,
  `blog_ID` bigint(35) NOT NULL default '0',
  `user_ID` bigint(35) NOT NULL default '0',
  `user_email` VARCHAR(255) default '0',
  `comment_ID` bigint(35) NOT NULL default '0',
  `date_time` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`active_ID`)
) ENGINE=InnoDB;";
		$reports_table4 = "";
		$reports_table5 = "";

		$wpdb->query( $reports_table1 );
		$wpdb->query( $reports_table2 );
		$wpdb->query( $reports_table3 );
		//$wpdb->query( $reports_table4 );
		//$wpdb->query( $reports_table5 );

		update_site_option( "reports_installed", "yes" );
	}
}

function reports_plug_pages() {
	if ( is_site_admin() ) {
		add_submenu_page('ms-admin.php', __('Reports'), __('Reports'), 10, 'reports', 'reports_page_output');
	}
}

function reports_add_report($name, $nicename, $description) {
	global $available_reports;
	
	if ( !is_array( $available_reports ) ) {
		$available_reports = array();
	}
	$available_reports[] = array($name, $nicename, $description);
}

function reports_user_activity() {
	global $wpdb, $current_user;
	
	if ( !empty($current_user->ID) ){
		$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "reports_user_activity (user_ID, location, date_time) VALUES ( '" . $current_user->ID . "', '" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . "', '" . current_time( 'mysql', 1 ) . "' )" );
	}
}

function reports_comment_activity($comment_ID){
	global $wpdb, $current_site;

	$comment_details = get_comment($comment_ID);
	if ( !empty($comment_details->comment_content) ){
		$comment_activity_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "reports_comment_activity WHERE blog_ID = '" . $wpdb->blogid . "' AND comment_ID = '" . $comment_ID . "'");
		if ($comment_activity_count == '0') {
			$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "reports_comment_activity (blog_ID, user_ID, user_email, comment_ID, date_time) VALUES ( '" . $wpdb->blogid . "', '" . $comment_details->user_id . "', '" . $comment_details->comment_author_email . "', '" . $comment_ID . "', '" . current_time( 'mysql', 1 ) . "' )" );
		}
	}
}

function reports_comment_activity_remove($comment_ID){
	global $wpdb;

	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "reports_comment_activity WHERE comment_ID = '" . $comment_ID . "' AND blog_ID = '" . $wpdb->blogid . "'" );
}

function  reports_comment_activity_remove_blog($blog_ID){
	global $wpdb;

	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "reports_comment_activity WHERE blog_ID = '" . $wpdb->blogid . "'" );
}

function reports_post_activity($post_ID){
	global $wpdb, $current_site;
	
	$post_details = get_post($post_ID);
	if ( !empty($post_details->post_content) && $post_details->post_type != 'revision' && $post_details->post_status == 'publish' ){
		$post_activity_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "reports_post_activity WHERE blog_ID = '" . $wpdb->blogid . "' AND post_ID = '" . $post_ID . "'");
		if ($post_activity_count == '0') {
			$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "reports_post_activity (blog_ID, user_ID, post_ID, post_type, date_time) VALUES ( '" . $wpdb->blogid . "', '" . $post_details->post_author . "', '" . $post_ID . "', '" . $post_details->post_type . "', '" . current_time( 'mysql', 1 ) . "' )" );
		}
	}
}

function reports_post_activity_remove($post_ID){
	global $wpdb;

	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "reports_post_activity WHERE post_ID = '" . $post_ID . "' AND blog_ID = '" . $wpdb->blogid . "'" );
}

function  reports_post_activity_remove_blog($blog_ID){
	global $wpdb;

	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "reports_post_activity WHERE blog_ID = '" . $wpdb->blogid . "'" );
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function reports_css() {
	global $wpdb, $parent_file;
	if ( $_GET['page'] == 'reports' ) {
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

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function reports_page_output() {
	global $wpdb, $available_reports;
	
	if(!current_user_can('manage_options')) {
		echo "<p>Nice Try...</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
			<h2><?php _e('Reports') ?></h2>
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
					$class = ('alternate' == $class) ? '' : 'alternate';
					foreach ($available_reports as $available_report){
					//=========================================================//
					echo "<tr class='" . $class . "'>";
					echo "<td valign='top'>" . $available_report[0] . "</td>";
					echo "<td valign='top'>" . $available_report[2] . "</td>";
					echo "<td valign='top'><a href='ms-admin.php?page=reports&action=view-report&report=" . $available_report[1] . "' rel='permalink' class='edit'>" . __('View Report') . "</a></td>";
					//echo "<td valign='top'><a href='edit.php?page=manage_tips&action=delete_tip&tid=" . $tmp_tip['tip_ID'] . "' rel='permalink' class='delete'>" . __('Remove') . "</a></td>";
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
					<p><?php _e('No reports available') ?></p>
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
			<h2><a href="ms-admin.php?page=reports" style="text-decoration:none;"><?php _e('Reports') ?></a> &raquo; <a href="ms-admin.php?page=reports&action=view-report&report=<?php echo $report_nicename; ?>" style="text-decoration:none;"><?php _e($report_name); ?></a></h2>
            <?php
			do_action('view_report');
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//
function reports_days_ago($n,$date_format) {
	if ( empty($date_format) ) {
		$date_format = "Y-m-d H:i:s";
	}
	return date($date_format, time() - 86400 * $n);
}

?>
