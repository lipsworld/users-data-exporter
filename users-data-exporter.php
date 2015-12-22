<?php 
/*
Plugin Name: Users Data Exporter
Plugin URI:  http://#
Description: Robust way to export selected user types and selected user data to .xlsx spreadsheet, specially when number of users of a WordPress is very big like 10,000+. 
Version:     1.0
Author:      Taher Uddin
Author URI:  http://taheruddin.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: user-data-exporter
*/

//$plg_root_url = plugins_url( '', __FILE__ );
//$plg_root_path = plugin_dir_path( __FILE__ );

require_once( plugin_dir_path( __FILE__ ) . 'Classes/PHPExcel.php' );

$ude_admin_page_hook = '';

function export_users_data_menu() {
	global $ude_admin_page_hook;
	$ude_admin_page_hook = add_users_page('Export Users data', 'Export Users data', 'manage_options', 'export-users-data-menu', 'export_users_data_admin_page');
}
add_action('admin_menu', 'export_users_data_menu');

function export_users_data_admin_page(){

	if( !current_user_can('manage_options') )
		return FALSE;

	global $wpdb;
	$prefix = $wpdb->prefix;
	$role_cond_SQL = '';

	if( isset($_POST['ude_export_selection']) && $_POST['ude_export_selection']=='Export' && (count($_POST['users_basics'])>0 || count($_POST['users_meta_keys'])>0) ){
		
		?><div id="ude-cont"><?php

		if(wp_verify_nonce( $_POST['ude_export_selection_37'], 'ude_export_selection_nonce' )){
			//echo "nonce is good ...<br>";
			if(isset($_POST['roles'])){
				$starter = " AND ( ";
				foreach ($_POST['roles'] as $key => $value) {
					//echo $key.' = '.$value.'<br>';
					$role_cond_SQL .= $starter." {$prefix}usermeta.meta_value LIKE '%{$value}%' ";
					$starter = " OR ";
				}
				if( strlen($role_cond_SQL)>1 )
					$role_cond_SQL .= " ) ";
			}

			$selected_users_SQL = 	"SELECT ID 
								 	FROM {$prefix}users, {$prefix}usermeta
								 	WHERE {$prefix}users.ID = {$prefix}usermeta.user_id AND {$prefix}usermeta.meta_key = '{$prefix}capabilities' 
									".$role_cond_SQL; 
			$selected_users_IDs = $wpdb->get_col( $selected_users_SQL );
			$num_users_selected = $wpdb->num_rows;
			
			if($num_users_selected > 0){
				echo "<h4>Exporting ".$num_users_selected." users.<h4>";
				//update_option( 'selected_users_SQL', $selected_users_SQL );
				update_option( 'num_users_selected', $num_users_selected );
				update_option( 'selected_users_IDs', serialize($selected_users_IDs) );
				//echo '<pre>'; print_r($selected_users_IDs); echo '</pre>';
				if( is_numeric($_POST['limit_num_user_per_exec']) ){
					update_option( 'limit_num_user_per_exec', $_POST['limit_num_user_per_exec'] );
				}
				update_option( 'next_users_IDs_index', 0 );
				update_option( 'selected_users_basics', serialize($_POST['users_basics']) );
				update_option( 'selected_users_meta_keys', serialize($_POST['users_meta_keys']) );

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
				//echo '<pre>'; print_r($output_col_list); echo '</pre>';

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
				//echo '<br>'.$xlsx_file_with_full_path.'<br>';
				//$sheetWriter->save(str_replace('.php', '.xlsx', __FILE__));

				?>
				<div class="progress" data-listener="<?php echo admin_url('admin-ajax.php'); ?>"><div><div></div></div><span>0%</span></div>
				<div class="let-finish"><strong>Do not close this page untill it finish exporting data.</strong><br></div>
				<div class="download"><a class="button-primary" href="<?php echo $upload_dir['url'].'/users-data.xlsx'; ?>">Download</a></div>
				<?php

			}else{
				echo "<h4>No users found with selected roles.<h4>";
			}
			
			$selected_users_basics = unserialize( get_option('selected_users_basics') );

			//echo '<pre>'; print_r($selected_users_basics); echo '</pre>';
			 
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
					//echo "<br> ".$role_name;
					//echo '<pre>'; print_r($role_info); echo '</pre>';
					?><label><input type="checkbox" name="roles[]" value="<?php echo $role_name; ?>"><?php echo ucfirst($role_name); ?> </label> <?php 
				}
				?> 
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
										";
				//echo $users_meta_keys_SQL;
				$users_meta_keys = $wpdb->get_col($users_meta_keys_SQL);
				foreach ( $users_meta_keys as $pos=>$users_meta_key ) {
					?><label><input type="checkbox" name="users_meta_keys[]" value="<?php echo $users_meta_key; ?>"><?php echo $users_meta_key; ?> </label> <?php
				}
				
				?>
			</fieldset>

			<fieldset><br><label>Single Execution Length:</label> <input type="number" name="limit_num_user_per_exec" value="100"></fieldset>

			<fieldset><br><input class="button-primary aligncenter" type="submit" name="ude_export_selection" value="Export"></fieldset>

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
    wp_enqueue_script( 'ume-admin-script', plugins_url( '', __FILE__ ) . '/users-data-exporter.js' );

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
	$selected_users_basics = unserialize( get_option('selected_users_basics') );

	//echo '<pre>'; print_r($selected_users_basics); echo '</pre>';
	//echo '---*---'.$selected_users_basics[0].'---*---';
	
	$selected_users_IDs = unserialize( get_option('selected_users_IDs') );
	$selected_users_meta_keys = unserialize( get_option('selected_users_meta_keys') );
	$output_col_list = unserialize( get_option('output_col_list') );
	$num_users_selected = get_option('num_users_selected');
	$next_users_IDs_index = get_option('next_users_IDs_index');
	$limit_num_user_per_exec = get_option( 'limit_num_user_per_exec' );
	$output_row_key = (int)get_option('next_output_row_key'); /*next_excel_row_key*/
	$xlsx_file_with_full_path = get_option('xlsx_file_with_full_path');
	//echo '---'.$output_row_key.'---';
	
	$i = -1;
	if( $next_users_IDs_index > -1 ){

		try {
			$sheet = PHPExcel_IOFactory::load($xlsx_file_with_full_path);
			$sheet->setActiveSheetIndex(0);
			$activeSheet = $sheet->getActiveSheet();
		} catch (Exception $e) {
			//echo '***'.$e->getMessage();
		}
		
		for($i=$next_users_IDs_index; $i<=$next_users_IDs_index-1+$limit_num_user_per_exec && $i<$num_users_selected; $i++){
			
			$user_id = $selected_users_IDs[$i];
			//echo 'output_row_key = '.$output_row_key;
			$user_basics = get_userdata($user_id);
			$user_metas = get_user_meta($user_id);
			//echo 'Roles of user_id - '.$user_id.' <pre>'; print_r($user_metas); echo '</pre>';
			foreach ($output_col_list as $data_key => $col_key) {
				if ( is_array($selected_users_basics) && count($selected_users_basics)>0 ) {
					if( in_array($data_key, $selected_users_basics) ){
						//$user_basics = get_userdata(1);
						if($data_key=='role'){
							$roles = implode(', ', $user_basics->roles);
							$activeSheet->setCellValue( $col_key.$output_row_key, $roles );
						}else{
							$activeSheet->setCellValue( $col_key.$output_row_key, $user_basics->$data_key );
							//echo "---|---{$col_key}{$output_row_key}---{$data_key}---".$user_basics->$data_key."---|---";
						}
					}else{
						if( array_key_exists($data_key, $user_metas) )
							$activeSheet->setCellValue( $col_key.$output_row_key, $user_metas[$data_key][0] );
						else{
							$activeSheet->setCellValue( $col_key.$output_row_key, '' );
						}
						//echo "---|---{$col_key}{$output_row_key}---{$data_key}---".$user_metas[$data_key][0]."---|---";
					}
				}
				else{
					if( array_key_exists($data_key, $user_metas) )
						$activeSheet->setCellValue( $col_key.$output_row_key, $user_metas[$data_key][0] );
					else{
						$activeSheet->setCellValue( $col_key.$output_row_key, '' );
					}
					//echo "---|---{$col_key}{$output_row_key}---{$data_key}---".$user_metas[$data_key][0]."---|---";
				}
				
				
			}
			$output_row_key++;
			
		}
		
		//echo '---OUT OF THE LOOP---';
		update_option('next_users_IDs_index', $i);
		update_option('next_output_row_key', $output_row_key);

		try {
			$sheetWriter = PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
			$sheetWriter->save( $xlsx_file_with_full_path );
		} catch (Exception $e) {
			//echo '***'.$e->getMessage();
		}
	}
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