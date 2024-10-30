<?php
/**
 * Moving Users
 *
 * @package    Moving Users
 * @subpackage MovingUsers Main function
/*  Copyright (c) 2021- Katsushi Kawamori (email : dodesyoswift312@gmail.com)
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

$movingusers = new MovingUsers();

/** ==================================================
 * Class Main function
 *
 * @since 1.00
 */
class MovingUsers {

	/** ==================================================
	 * Path
	 *
	 * @var $upload_dir  upload_dir.
	 */
	private $upload_dir;

	/** ==================================================
	 * Construct
	 *
	 * @since 1.00
	 */
	public function __construct() {

		$wp_uploads = wp_upload_dir();
		$upload_dir = wp_normalize_path( $wp_uploads['basedir'] );
		$upload_dir = untrailingslashit( $upload_dir );
		$this->upload_dir = trailingslashit( $upload_dir ) . trailingslashit( 'moving-users' );

		/* Make json files dir */
		if ( ! is_dir( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
		}

		add_action( 'movingusers_generate_json_hook', array( $this, 'generate_json' ) );
		add_action( 'movingusers_update_db_hook', array( $this, 'update_db' ), 10, 2 );
		add_action( 'movingusers_clear_db_hook', array( $this, 'clear_db' ), 10, 1 );
		add_action( 'movingusers_logs_check_files', array( $this, 'logs_check_files' ), 10, 1 );
		add_action( 'movingusers_logs_slice_create', array( $this, 'logs_slice_create' ), 10, 3 );
		add_action( 'movingusers_logs_slice_delete', array( $this, 'logs_slice_delete' ), 10, 2 );
	}

	/** ==================================================
	 * Gennerate json
	 *
	 * @since 1.00
	 */
	public function generate_json() {

		if ( function_exists( 'wp_date' ) ) {
			$time_stamp = wp_date( 'Y-m-d_H-i-s' );
		} else {
			$time_stamp = date_i18n( 'Y-m-d_H-i-s' );
		}
		$name = 'moving_users_' . $time_stamp . '.json';

		$json_file = $this->upload_dir . $name;

		global $wpdb;
		$users = $wpdb->get_results(
			"
			SELECT	*
			FROM	{$wpdb->prefix}users
			"
		);

		$user_meta = $wpdb->get_results(
			"
			SELECT	*
			FROM	{$wpdb->prefix}usermeta
			"
		);

		$users_json = wp_json_encode(
			array(
				'user' => $users,
				'usermeta' => $user_meta,
			)
		);

		$file = new SplFileObject( $json_file, 'w' );
		$file->fwrite( $users_json );
		$file = null;

		/* Option logs slice and file delete for create json */
		do_action( 'movingusers_logs_slice_create', 'moving_users_export_files', $name, get_option( 'moving_users_number_files', 5 ) );

		if ( get_option( 'moving_users_mail_send' ) ) {
			/* Send mail JSON file */
			$message = 'Moving Users : ' . __( 'The JSON file for the contents has been generated.', 'moving-users' );
			wp_mail( get_option( 'admin_email' ), $message, $message, null, $json_file );
		}
	}

	/** ==================================================
	 * Update DB
	 *
	 * @param string $json_file  json_file.
	 * @param int    $uid  Current user ID.
	 * @since 1.00
	 */
	public function update_db( $json_file, $uid ) {

		global $wpdb;

		$file = new SplFileObject( $json_file );
		$import_data = json_decode( $file );
		$file = null;

		foreach ( $import_data as $key1 => $value1 ) {
			foreach ( $value1 as $key2 => $value2 ) {
				if ( 'user' === $key1 && $uid != $value2->ID ) {
					$table = $wpdb->prefix . 'users';
					if ( ! get_user_by( 'id', $value2->ID ) ) {
						$wpdb->insert( $table, (array) $value2 );
					} else {
						$wpdb->update( $table, (array) $value2, array( 'ID' => $value2->ID ) );
					}
				} else if ( 'usermeta' === $key1 && $uid != $value2->user_id ) {
					$table = $wpdb->prefix . 'usermeta';
					$is_meta_id = false;
					$is_meta_id = $wpdb->get_var(
						$wpdb->prepare(
							"
							SELECT umeta_id
							FROM {$wpdb->prefix}usermeta
							WHERE umeta_id = %d
							",
							$value2->umeta_id
						)
					);
					if ( ! $is_meta_id ) {
						$wpdb->insert( $table, (array) $value2 );
					} else {
						$wpdb->update( $table, (array) $value2, array( 'umeta_id' => $value2->umeta_id ) );
					}
				}
			}
		}

		echo '<div class="notice notice-success is-dismissible"><ul><li>' . esc_html__( 'The import is now complete.', 'moving-users' ) . '</li></ul></div>';
	}

	/** ==================================================
	 * Clear DB
	 *
	 * @param int $uid  Current user ID.
	 * @since 1.00
	 */
	public function clear_db( $uid ) {

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"
				DELETE FROM {$wpdb->prefix}users
				WHERE ID != %d
				",
				$uid
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}usermeta
				WHERE user_id != %d
				",
				$uid
			)
		);
	}

	/** ==================================================
	 * Check option logs and files
	 *
	 * @param string $option_name  option_name.
	 * @since 1.00
	 */
	public function logs_check_files( $option_name ) {

		$logs = get_option( $option_name, array() );

		$json_files = array();
		$files = glob( $this->upload_dir . '*.json' );
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$json_files[] = wp_basename( $file );
			}
		}
		$delete_files_diff = array_diff( $logs, $json_files );
		if ( ! empty( $delete_files_diff ) ) {
			foreach ( $logs as $key => $value ) {
				if ( in_array( $value, $delete_files_diff ) ) {
					unset( $logs[ $key ] );
				}
			}
			array_values( $logs );
			update_option( $option_name, $logs );
		}
	}

	/** ==================================================
	 * Option logs slice and file delete for create json
	 *
	 * @param string $option_name  option_name.
	 * @param string $name  name.
	 * @param int    $number_files  number_files.
	 * @since 1.00
	 */
	public function logs_slice_create( $option_name, $name, $number_files ) {

		$logs = get_option( $option_name, array() );

		if ( ! empty( $logs ) ) {
			$log_files_all = array_merge( array( $name ), $logs );
		} else {
			$log_files_all = array( $name );
		}
		$log_files = array_slice( $log_files_all, 0, $number_files );
		$delete_files = array_slice( $log_files_all, $number_files );
		update_option( $option_name, $log_files );
		foreach ( $delete_files as $value ) {
			$delete_file = $this->upload_dir . $value;
			if ( file_exists( $delete_file ) ) {
				wp_delete_file( $delete_file );
			}
		}
	}

	/** ==================================================
	 * Option logs slice and file delete for delete json
	 *
	 * @param string $option_name  option_name.
	 * @param array  $delete_files  delete_files.
	 * @since 1.00
	 */
	public function logs_slice_delete( $option_name, $delete_files ) {

		$logs = get_option( $option_name, array() );

		foreach ( $delete_files as $name ) {
			if ( ! empty( $logs ) ) {
				foreach ( $logs as $key => $value ) {
					if ( $value === $name ) {
						unset( $logs[ $key ] );
					}
				}
				array_values( $logs );
				update_option( $option_name, $logs );
			}
			if ( file_exists( $this->upload_dir . $name ) ) {
				wp_delete_file( $this->upload_dir . $name );
			}
		}
	}
}
