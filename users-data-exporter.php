<?php 
/*
Plugin Name: Users Data Exporter
Plugin URI:  http://#
Description: Robust way to export selected user types and selected user data to .xlsx spreadsheet, specially when number of users of a site is very big like 100,000+. 
Version:     1.0
Author:      Taher Uddin
Author URI:  http://taheruddin.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

require_once( plugin_dir_path( __FILE__ ) . 'Classes/PHPExcel.php' );

$ude_admin_page_hook = '';

function export_users_data_menu() {
	global $ude_admin_page_hook;
	$ude_admin_page_hook = add_users_page('Export Users Data', 'Export Users data', 'manage_options', 'export-users-data-menu', 'export_users_data_admin_page');
}
add_action('admin_menu', 'export_users_data_menu');

function export_users_data_admin_page(){

	if( !current_user_can('manage_options') )
		return FALSE;

	global $wpdb;
	$prefix = $wpdb->prefix;
	$role_cond_SQL = '';
	$email_cond_SQL = '';
	$login_cond_SQL = '';
	$user_id_cond_SQL = '';
	$reg_date_cond_SQL = '';
	$meta_filters_SQL = array();
	$selected_IDs_by_meta_filters = array();

	$pmpro_cond_SQL = '';

	//echo $_POST['ude_export_selection']; // Debug

	if( isset($_POST['ude_export_selection']) && $_POST['ude_export_selection']=='Export' && (count($_POST['users_basics'])>0 || count($_POST['users_meta_keys'])>0) ){
		
		?><div id="ude-cont"><?php

		if(wp_verify_nonce( $_POST['ude_export_selection_37'], 'ude_export_selection_nonce' )){
			
			/* Generate SQL to Filter by Roles */
			if(isset($_POST['roles'])){
				$starter = " AND ( ";
				foreach ($_POST['roles'] as $key => $value) {
					$role_cond_SQL .= $wpdb->prepare("$starter {$prefix}usermeta.meta_value LIKE '%s' ", '%'.$value.'%');
					$starter = " OR ";
				}
				if( strlen($role_cond_SQL)>1 )
					$role_cond_SQL .= " ) ";
			}

			$SQL_FROM = "FROM {$prefix}users, {$prefix}usermeta";

			/* Generate SQL to Filter by Basic Field - Email, Login, ID, Registration Date, Paid Membership Pro Level */
			if( isset($_POST['add_basic_filter']) && $_POST['add_basic_filter']=='add_basic_filter' ){
				/* Email */
				if( isset($_POST['add_filter_email']) && $_POST['add_filter_email']=='add_filter_email' && !empty($_POST['filter_email']) ){
					$filter_email = sanitize_email($_POST['filter_email']);
					if( empty($filter_email) ){
						echo "<div class=\"update-nag\">Invalid Filter - Email = '{$_POST['filter_email']}'.</div>";
						return false;
					}else{
						$email_cond_SQL = $wpdb->prepare(" AND {$prefix}users.user_email LIKE %s ", $filter_email);
					}
				}
				/* Login */
				if( isset($_POST['add_filter_login']) && $_POST['add_filter_login']=='add_filter_login' && !empty($_POST['filter_login']) ){
					$filter_login = sanitize_user($_POST['filter_login']);
					if( empty($filter_login) ){
						echo "<div class=\"update-nag\">Invalid Filter - Login Name = '{$_POST['filter_login']}'.</div>";
						return false;
					}else{
						$login_cond_SQL = $wpdb->prepare(" AND {$prefix}users.user_login LIKE %s ", $filter_login);
					}
				}
				/* Id */
				if( isset($_POST['add_filter_user_id']) && $_POST['add_filter_user_id']=='add_filter_user_id' ){
					if( (empty($_POST['filter_user_id_begin']) && $_POST['filter_user_id_begin']!='0') || (empty($_POST['filter_user_id_end']) && $_POST['filter_user_id_end']!='0') ){
						echo "<div class=\"update-nag\">Did not fill 'User Id is Between ... and ...'' properly.</div>";
						return false;
					}
					$filter_user_id_begin = $_POST['filter_user_id_begin'];
					if( !is_numeric($filter_user_id_begin) ){
						echo "<div class=\"update-nag\">'{$_POST['filter_user_id_begin']}' - Begining Id of User Id Filter is invalid.</div>";
						return false;
					}
					$filter_user_id_end = $_POST['filter_user_id_end'];
					if( !is_numeric($filter_user_id_end) ){
						echo "<div class=\"update-nag\">'{$_POST['filter_user_id_end']}' - Ending Id of User Id Filter is invalid.</div>";
						return false;
					}
					$user_id_cond_SQL = $wpdb->prepare(" AND ( {$prefix}users.ID BETWEEN %s AND %s )", $filter_user_id_begin, $filter_user_id_end );
					
				}
				/* Registration Date */
				if( isset($_POST['add_filter_reg_date']) && $_POST['add_filter_reg_date']=='add_filter_reg_date' ){
					if( empty($_POST['filter_reg_date_begin']) || empty($_POST['filter_reg_date_end']) ){
						echo "<div class=\"update-nag\">Did not fill 'User Registration Date is Between ... and ...'' properly.</div>";
						return false;
					}
					$filter_reg_date_begin = date_create_from_format('Y-m-d', $_POST['filter_reg_date_begin']);
					if( empty($filter_reg_date_begin) ){
						echo "<div class=\"update-nag\">'{$_POST['filter_reg_date_begin']}' - Begining date of User Registration Period Filter is invalid.</div>";
						return false;
					}
					$filter_reg_date_end = date_create_from_format('Y-m-d', $_POST['filter_reg_date_end']);
					if( empty($filter_reg_date_end) ){
						echo "<div class=\"update-nag\">'{$_POST['filter_reg_date_end']}' - Ending date of User Registration Period Filter is invalid.</div>";
						return false;
					}
					$reg_date_cond_SQL = $wpdb->prepare(" AND ( {$prefix}users.user_registered BETWEEN %s AND %s )", $filter_reg_date_begin->format('Y-m-d'), $filter_reg_date_end->format('Y-m-d') );
					
				}
				/* Paid Membership Pro Level */
				if( function_exists('pmpro_getMembershipLevelsForUser') && isset($_POST['add_filter_pmpro_level']) && !empty($_POST['add_filter_pmpro_level']) && is_numeric($_POST['filter_pmpro_level']) ){
					$SQL_FROM .= ", {$prefix}pmpro_memberships_users, {$prefix}pmpro_membership_levels ";
					$pmpro_cond_SQL = $wpdb->prepare(" AND {$prefix}users.ID={$prefix}pmpro_memberships_users.user_id AND {$prefix}pmpro_memberships_users.membership_id={$prefix}pmpro_membership_levels.id AND {$prefix}pmpro_memberships_users.status='active' AND {$prefix}pmpro_membership_levels.id=%s", $_POST['filter_pmpro_level']);
				}
			}

			/* Generate SQL to Filter by Meta Field */
			if( isset($_POST['add_meta_filter']) && $_POST['add_meta_filter']=='add_meta_filter' ){
				
				//echo '<pre>'; print_r($_POST['filter_meta_key']); echo '</pre>'; // Debug
				
				foreach ($_POST['filter_meta_key'] as $meta_key_key => $meta_key) {
					$m_f_id = $_POST['m_f_id'][$meta_key_key];
					echo '$m_f_id='.$m_f_id.'<br>';
					if( isset($_POST['add_meta_filter_type_'.$m_f_id]) && $_POST['add_meta_filter_type_'.$m_f_id]=='equal' ){

						//echo '<pre>'; print_r($_POST['add_meta_filter_type_'.$m_f_id]); echo '</pre>'; // Debug

						if( empty($_POST['meta_filter_equal_to_value'][$meta_key_key]) ){
							echo "<div class=\"update-nag\">Meta Filter Value is not filled.</div>";
							return false;
						}else{
							$meta_filters_SQL[] = $wpdb->prepare("
													SELECT DISTINCT user_id AS ID 
													FROM {$prefix}usermeta 
													WHERE meta_key=%s 
												  	AND meta_value LIKE %s 
													", $_POST['filter_meta_key'][$meta_key_key], 
													$_POST['meta_filter_equal_to_value'][$meta_key_key]
							);
						}
					}elseif( isset($_POST['add_meta_filter_type_'.$m_f_id]) && $_POST['add_meta_filter_type_'.$m_f_id]=='between' ){
						if( (empty($_POST['meta_filter_between_value_0'][$meta_key_key]) && $_POST['meta_filter_between_value_0'][$meta_key_key]!='0') || (empty($_POST['meta_filter_between_value_1'][$meta_key_key]) && $_POST['meta_filter_between_value_1'][$meta_key_key]!='0') ){
							echo "<div class=\"update-nag\">One or more Meta Filter Values are not filled.</div>";
							return false;
						}else{
							echo '$_POST["add_meta_filter_type"]=between is OK';
							$meta_filters_SQL[] = $wpdb->prepare("
													SELECT DISTINCT user_id AS ID 
													FROM {$prefix}usermeta 
													WHERE meta_key=%s 
												  	AND meta_value BETWEEN %s AND %s
													", $_POST['filter_meta_key'][$meta_key_key], 
													$_POST['meta_filter_between_value_0'][$meta_key_key],
													$_POST['meta_filter_between_value_1'][$meta_key_key]
							);
						}
					}
				}
			}
			//echo '<pre>'; print_r($meta_filters_SQL); echo '</pre>'; //Debug

			/* Getting Results from Database by Executing SQLs */
			if(!empty($meta_filters_SQL)){
				foreach ($meta_filters_SQL as $meta_filter_SQL) {
					$selected_IDs_by_meta_filters[] = $wpdb->get_col( $meta_filter_SQL );
				}
			}
			

			$selected_users_SQL = 	"SELECT {$prefix}users.ID AS ID 
								 	{$SQL_FROM}
								 	WHERE {$prefix}users.ID = {$prefix}usermeta.user_id AND {$prefix}usermeta.meta_key = '{$prefix}capabilities' 
									".$role_cond_SQL.$email_cond_SQL.$login_cond_SQL.$user_id_cond_SQL.$reg_date_cond_SQL.$pmpro_cond_SQL; 
			//echo "<br>$selected_users_SQL<br>"; // Debug
			$selected_users_IDs = $wpdb->get_col( $selected_users_SQL );
			$num_users_selected = $wpdb->num_rows;
			
			/* Intesecting Database Query Results */
			if( !empty($selected_IDs_by_meta_filters) ){
				foreach ($selected_IDs_by_meta_filters as $selected_IDs_by_meta_filter) {
					$selected_users_IDs = array_intersect($selected_users_IDs, $selected_IDs_by_meta_filter);
				}
				$selected_users_IDs = array_values($selected_users_IDs);
				//echo '<pre>'; print_r($selected_users_IDs); echo '</pre>'; // Debug
				$num_users_selected = count($selected_users_IDs);
			}
			
			/* Saving Resultant IDs and Some Values Need for AJAX Execution */
			if($num_users_selected > 0){
				echo "<h4>Exporting ".$num_users_selected." users.<h4>";
				update_option( 'num_users_selected', $num_users_selected );
				update_option( 'selected_users_IDs', serialize($selected_users_IDs) );
				//echo '<pre>'; print_r($selected_users_IDs); echo '</pre>'; // Debug
				if( is_numeric($_POST['limit_num_user_per_exec']) ){
					update_option( 'limit_num_user_per_exec', $_POST['limit_num_user_per_exec'] );
				}
				update_option( 'next_users_IDs_index', 0 );
				update_option( 'selected_users_basics', serialize($_POST['users_basics']) );
				update_option( 'selected_users_meta_keys', serialize($_POST['users_meta_keys']) );

				/* Column Titles in The Output .xlsx File */
				$output_col_list = array();
				$output_col_key = 'A';
				if(isset($_POST['users_basics'])){
					foreach ($_POST['users_basics'] as $key => $users_basic) {
						$output_col_list[$users_basic] = $output_col_key;
						$output_col_key++;
					}
				}
				if(isset($_POST['users_meta_keys'])){
					foreach ($_POST['users_meta_keys'] as $key => $users_meta_key) {
						$output_col_list[$users_meta_key] = $output_col_key;
						$output_col_key++;
					}
				}
				
				update_option( 'output_col_list', serialize($output_col_list) );
				//echo '<pre>'; print_r($output_col_list); echo '</pre>'; // Debug

				$sheet = new PHPExcel();
				$sheet->getProperties()->setCreator("Users Data")
							 ->setLastModifiedBy("Users Data Exporter WordPress Plugin")
							 ->setTitle("Users Data")
							 ->setSubject("Users Data")
							 ->setDescription("Exported Use Data.")
							 ->setKeywords("Users Data")
							 ->setCategory("Users Data");

				$sheet->setActiveSheetIndex(0);

				$activeSheet = $sheet->getActiveSheet();
				$activeSheet->setTitle('Users Data');
				$activeSheet->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
				$activeSheet->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

				foreach ($output_col_list as $data_key => $col_key) {
					$activeSheet->setCellValue($col_key.'1', $data_key);
					$activeSheet->getColumnDimension($col_key)->setAutoSize(TRUE);
				}
				update_option('next_output_row_key', 2);

				$upload_dir = wp_upload_dir();
				$xlsx_file_with_full_path = $upload_dir['path'].'/users-data.xlsx';
				update_option('xlsx_file_with_full_path', $xlsx_file_with_full_path);
				try {
					$sheetWriter = PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
					$sheetWriter->save( $xlsx_file_with_full_path );
				} catch (Exception $e) {
					echo $e->getMessage();
				}
				//echo '<br>'.$xlsx_file_with_full_path.'<br>'; // Debug

				?>
				<div class="progress" data-listener="<?php echo admin_url('admin-ajax.php'); ?>"><div><div></div></div><span>0%</span></div>
				<div class="let-finish"><strong>Do not close this page untill it finish exporting data.</strong><br></div>
				<div class="download"><a class="button-primary" href="<?php echo $upload_dir['url'].'/users-data.xlsx'; ?>">Download</a></div>
				<?php

			}else{
				echo "<h4>No users found with selected conditions.<h4>";
			}

			//echo '<pre>'; print_r($selected_users_basics); echo '</pre>'; // Debug
			 
		}else{
			?><h4>Something went wrong please try again. <a class="button-primary" href="<?php echo admin_url('users.php?page=export-users-data-menu'); ?>">Start Over</a></h4><?php 
		}
		?></div><?php 
	}else{
		if( isset($_POST['ude_export_selection']) && $_POST['ude_export_selection']=='Export' && count($_POST['users_basics'])<=0 && count($_POST['users_meta_keys'])<=0 ){
			echo '<div class="ude-error"><strong>No users data field has been selected.</strong></div>';
		}
	?>
	<div id="ude-cont">
		<h1>Export Users Data</h1>
		<form class="export-users-data" action="<?php echo admin_url('users.php?page=export-users-data-menu'); ?>" method="post">
			
			<?php wp_nonce_field('ude_export_selection_nonce', 'ude_export_selection_37'); ?> 

			<fieldset class="users-roles">
				<h3>Select Roles to Export:</h3>
				<?php
				$roles = get_editable_roles();
				foreach ($roles as $role_name => $role_info) {
					//echo "<br> ".$role_name; // Debug
					//echo '<pre>'; print_r($role_info); echo '</pre>'; // Debug
					?><label><input type="checkbox" name="roles[]" value="<?php echo $role_name; ?>"><?php echo ucfirst($role_name); ?> </label> <?php 
				}
				?> 
				<small>( Selecting none means no filtering by user roles. )</small>
			</fieldset>
			
			<hr>

			<fieldset class="users-meta-keys">
				<h3>Select Fields to Export:</h3>
				<label><input type="checkbox" name="users_basics[]" value="role">role</label>
				<label><input type="checkbox" name="users_basics[]" value="user_login">user_login</label>
				<label><input type="checkbox" name="users_basics[]" value="user_nicename">user_nicename</label>
				<label><input type="checkbox" name="users_basics[]" value="user_email">user_email</label>
				<label><input type="checkbox" name="users_basics[]" value="user_url">user_url</label>
				<label><input type="checkbox" name="users_basics[]" value="user_registered">user_registered</label>
				<label><input type="checkbox" name="users_basics[]" value="user_status">user_status</label>
				<label><input type="checkbox" name="users_basics[]" value="display_name">display_name</label>
				<?php
				$users_meta_keys_SQL = "SELECT DISTINCT meta_key 
										FROM {$prefix}usermeta
										WHERE meta_key NOT LIKE '%{$prefix}%'
										ORDER BY meta_key
										";
				//echo $users_meta_keys_SQL; // Debug
				$users_meta_keys = $wpdb->get_col($users_meta_keys_SQL);
				foreach ( $users_meta_keys as $pos=>$users_meta_key ) {
					?><label><input type="checkbox" name="users_meta_keys[]" value="<?php echo $users_meta_key; ?>"><?php echo $users_meta_key; ?> </label> <?php
				}
				
				?>
			</fieldset>

			<hr>

			<fieldset>
				<input id="add_basic_filter" class="expander" type="checkbox" name="add_basic_filter" value="add_basic_filter"> <label for="add_basic_filter"><strong>Add Basic Filter:</strong></label>
				<div>
					<div>
						<input id="add_filter_email" class="expander" type="checkbox" name="add_filter_email" value="add_filter_email"> <label for="add_filter_email">Filter by Email</label>
						<div>
							User Email = <input type="email" name="filter_email">
						</div>
					</div>

					<div>
						<input id="add_filter_login" class="expander" type="checkbox" name="add_filter_login" value="add_filter_login"> <label for="add_filter_login">Filter by User Login Name</label>
						<div>
							User Login Name = <input type="text" name="filter_login">
						</div>
					</div>

					<div>
						<input id="add_filter_user_id" class="expander" type="checkbox" name="add_filter_user_id" value="add_filter_user_id"> <label for="add_filter_user_id">Filter by User Id</label>
						<div>
							User Id is Between <input type="number" name="filter_user_id_begin"> and <input type="number" name="filter_user_id_end">
						</div>
					</div>

					<div>
						<input id="add_filter_reg_date" class="expander" type="checkbox" name="add_filter_reg_date" value="add_filter_reg_date"> <label for="add_filter_reg_date">Filter by User Registration Date</label>
						<div>
							User Registration Date is Between <input class="datepicker" type="text" name="filter_reg_date_begin" placeholder="yyyy-mm-dd"> and <input class="datepicker" type="date" name="filter_reg_date_end" placeholder="yyyy-mm-dd">
						</div>
					</div>
					
					<?php
					if(function_exists('pmpro_getMembershipLevelsForUser')){
						global $wpdb;
						$pmpro_levels = $wpdb->get_results("SELECT id, name FROM $wpdb->pmpro_membership_levels ORDER BY id");
						?> 
						<div>
							<input id="add_filter_pmpro_level" class="expander" type="checkbox" name="add_filter_pmpro_level" value="add_filter_pmpro_level"> <label for="add_filter_pmpro_level">Filter by Membership Level</label> 
							<div>
								Membership Level = 
								<select name="filter_pmpro_level">
									<?php 
									foreach ($pmpro_levels as $pmpro_level) {
										echo '<option value="'.$pmpro_level->id.'">Level '.$pmpro_level->id.' - '.$pmpro_level->name.'</option>';
									} 
									?>
								</select>
							</div>
						</div>
						<?php
					}
					?>
				</div>

			</fieldset>

			<hr>

			<fieldset class="meta-filters">
				<input id="add_meta_filter" class="expander" type="checkbox" name="add_meta_filter" value="add_meta_filter"> <label for="add_meta_filter"><strong>Add Meta Filter:</strong></label>
				<div class="grouped">
					<div class="meta-filter master hide">
						<input class="m_f_id" type="hidden" name="m_f_id[]" value="0">
						<div class="inline"><select name="filter_meta_key[]"> <?php foreach ( $users_meta_keys as $pos=>$users_meta_key ) {
							?><option value="<?php echo $users_meta_key; ?>"><?php echo $users_meta_key; ?> </option> <?php
						} ?></select> is </div> 
						<div class="inline">
							<input id="add_filter_meta_equal_0" class="expander hide equal_to" type="radio" name="add_meta_filter_type" value="equal" checked="checked"> <label class="equal_to" for="add_filter_meta_equal_0">Equal to</label>
							<div class="inline">
								equal to <input type="text" name="meta_filter_equal_to_value[]">
							</div>
						</div>
						<div class="inline">
							<input id="add_filter_meta_between_0" class="expander hide between" type="radio" name="add_meta_filter_type[]" value="between"> <label class="between" for="add_filter_meta_between_0">Between ... And ...</label>
							<div class="inline">
								between <input type="text" name="meta_filter_between_value_0[]"> and <input type="text" name="meta_filter_between_value_1[]">
							</div>
						</div>
						<div class="close"> X </div>
					</div>
				</div>

				<div id="more-meta-filter">+ Add A Meta Filter</div>

			</fieldset>

			

			<hr>

			<fieldset><br><label>Single Execution Length:</label> <input type="number" name="limit_num_user_per_exec" value="100"></fieldset>

			<fieldset>
				<br>
				<input id="export" class="button-primary aligncenter" type="button" name="ude_export_selection" value="Export">
				<input type="hidden" name="ude_export_selection" value="Export">
			</fieldset>

		</form>
	</div>
	<?php 
	}
}
/* ************************************************************************* */


/* ************************************************************************* */
function ude_enqueue_admin_scripts($hook) {
	global $ude_admin_page_hook;
    if ( $ude_admin_page_hook != $hook ) {
        return;
    }
	//echo '<pre>'; print_r($hook); echo '</pre>';
    wp_enqueue_script( 'ume-admin-script', plugins_url( '', __FILE__ ) . '/users-data-exporter.js', array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker') );

    wp_enqueue_style( 'jquery-ui-datepicker-css', 'https://code.jquery.com/ui/1.9.1/themes/base/jquery-ui.css' );

    wp_enqueue_style( 'ume-admin-style', plugins_url( '', __FILE__ ) . '/users-data-exporter.css' );
}
add_action( 'admin_enqueue_scripts', 'ude_enqueue_admin_scripts' );
/* ************************************************************************* */


/* ************************************************************************* */
/* AJAX for Exporting to File */
function users_data_exporter(){

	if( !current_user_can('manage_options') )
		die();

	$reply = array();

	/* Getting Resultant IDs and Some Variables to Start the Loop */
	$selected_users_basics = unserialize( get_option('selected_users_basics') );
	//echo '<pre>'; print_r($selected_users_basics); echo '</pre>'; // Debug
	$selected_users_IDs = unserialize( get_option('selected_users_IDs') );
	$selected_users_meta_keys = unserialize( get_option('selected_users_meta_keys') );
	$output_col_list = unserialize( get_option('output_col_list') );
	$num_users_selected = get_option('num_users_selected');
	$next_users_IDs_index = get_option('next_users_IDs_index');
	$limit_num_user_per_exec = get_option( 'limit_num_user_per_exec' );
	$output_row_key = (int)get_option('next_output_row_key');
	$xlsx_file_with_full_path = get_option('xlsx_file_with_full_path');
	//echo '---'.$output_row_key.'---'; // Debug
	//echo '---selected_users_IDs = <pre>'; print_r($selected_users_IDs); echo '</pre>'; // Debug
	
	$i = -1;
	if( $next_users_IDs_index > -1 ){

		/* Openning and Reading .xlsx File */
		try {
			$sheet = PHPExcel_IOFactory::load($xlsx_file_with_full_path);
			$sheet->setActiveSheetIndex(0);
			$activeSheet = $sheet->getActiveSheet();
		} catch (Exception $e) {
			//echo '***'.$e->getMessage(); // Debug
		}
		
		/* Adding Data into PHPExcel activeSheet by Looping through Resultant IDs */
		for($i=$next_users_IDs_index; $i<=$next_users_IDs_index-1+$limit_num_user_per_exec && $i<$num_users_selected; $i++){
			
			$user_id = $selected_users_IDs[$i];
			//echo 'output_row_key = '.$output_row_key; // Debug
			$user_basics = get_userdata($user_id);
			//echo '---Basics of user_id - '.$user_id.' <pre>'; print_r($user_basics); echo '</pre>'; // Debug
			$user_metas = get_user_meta($user_id);
			//echo 'Roles of user_id - '.$user_id.' <pre>'; print_r($user_metas); echo '</pre>'; // Debug
			foreach ($output_col_list as $data_key => $col_key) {
				if ( is_array($selected_users_basics) && count($selected_users_basics)>0 ) {
					if( in_array($data_key, $selected_users_basics) ){
						
						if($data_key=='role'){
							$roles = implode(', ', $user_basics->roles);
							$activeSheet->setCellValue( $col_key.$output_row_key, $roles );
						}else{
							$activeSheet->setCellValue( $col_key.$output_row_key, $user_basics->$data_key );
							//echo "---|---{$col_key}{$output_row_key}---{$data_key}---".$user_basics->$data_key."---|---"; // Debug
						}

					}else{

						if( array_key_exists($data_key, $user_metas) )
							$activeSheet->setCellValue( $col_key.$output_row_key, $user_metas[$data_key][0] );
						else{
							$activeSheet->setCellValue( $col_key.$output_row_key, '' );
						}
						//echo "---|---{$col_key}{$output_row_key}---{$data_key}---".$user_metas[$data_key][0]."---|---"; // Debug
					}
				}
				else{
					if( array_key_exists($data_key, $user_metas) )
						$activeSheet->setCellValue( $col_key.$output_row_key, $user_metas[$data_key][0] );
					else{
						$activeSheet->setCellValue( $col_key.$output_row_key, '' );
					}
					//echo "---|---{$col_key}{$output_row_key}---{$data_key}---".$user_metas[$data_key][0]."---|---"; // Debug
				}
			}
			$output_row_key++;
		}
		
		/* Saving Some Variable for Next AJAX Execution */
		update_option('next_users_IDs_index', $i);
		update_option('next_output_row_key', $output_row_key);

		/* Saving .xlsx File */
		try {
			$sheetWriter = PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
			$sheetWriter->save( $xlsx_file_with_full_path );
		} catch (Exception $e) {
			//echo '***'.$e->getMessage();
		}
	}

	/* Placing the Reply for Browser in JSON Format */
	//echo '-$i = '.$i;
	if($i<=$num_users_selected-1 && $i>-1){
		$progress = $i/$num_users_selected*100;
		$reply['running'] = TRUE;
		$reply['progress'] = (int)$progress.'%';
		echo json_encode($reply);
	}else{
		update_option( 'next_users_IDs_index', -1 );
		$reply['running'] = FALSE;
		$reply['progress'] = '100%';
		echo json_encode($reply);
	}
	
	
	die();
}
add_action( 'wp_ajax_export_users_data', 'users_data_exporter' );
/* End of - AJAX for Exporting to File */


?>