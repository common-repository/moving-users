<?php
/**
 * Moving Users
 *
 * @package    Moving Users
 * @subpackage MovingUsersAdmin Management screen
	Copyright (c) 2021- Katsushi Kawamori (email : dodesyoswift312@gmail.com)
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

$movingusersadmin = new MovingUsersAdmin();

/** ==================================================
 * Management screen
 */
class MovingUsersAdmin {

	/** ==================================================
	 * Path
	 *
	 * @var $upload_dir  upload_dir.
	 */
	private $upload_dir;

	/** ==================================================
	 * Path
	 *
	 * @var $upload_url  upload_url.
	 */
	private $upload_url;

	/** ==================================================
	 * Construct
	 *
	 * @since 1.00
	 */
	public function __construct() {

		$wp_uploads = wp_upload_dir();
		$upload_url = $wp_uploads['baseurl'];
		$upload_dir = wp_normalize_path( $wp_uploads['basedir'] );
		if ( is_ssl() ) {
			$upload_url = str_replace( 'http:', 'https:', $upload_url );
		}
		$upload_dir = untrailingslashit( $upload_dir );
		$upload_url = untrailingslashit( $upload_url );
		$this->upload_dir = trailingslashit( $upload_dir ) . trailingslashit( 'moving-users' );
		$this->upload_url = trailingslashit( $upload_url ) . trailingslashit( 'moving-users' );

		/* Make json files dir */
		if ( ! is_dir( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
		}

		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_filter( 'plugin_action_links', array( $this, 'settings_link' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'allow_upload_json' ) );
	}

	/** ==================================================
	 * Add a "Settings" link to the plugins page
	 *
	 * @param  array  $links  links array.
	 * @param  string $file   file.
	 * @return array  $links  links array.
	 * @since 1.00
	 */
	public function settings_link( $links, $file ) {
		static $this_plugin;
		if ( empty( $this_plugin ) ) {
			$this_plugin = 'moving-users/movingusers.php';
		}
		if ( $file == $this_plugin ) {
			$links[] = '<a href="' . admin_url( 'admin.php?page=movingusers' ) . '">Moving Users</a>';
			$links[] = '<a href="' . admin_url( 'admin.php?page=movingusers-generate-json' ) . '">' . __( 'Export' ) . '</a>';
			$links[] = '<a href="' . admin_url( 'admin.php?page=movingusers-update-db' ) . '">' . __( 'Import' ) . '</a>';
		}
		return $links;
	}

	/** ==================================================
	 * Add page
	 *
	 * @since 1.00
	 */
	public function add_pages() {
		add_menu_page(
			'Moving Users',
			'Moving Users',
			'manage_options',
			'movingusers',
			array( $this, 'manage_page' ),
			'dashicons-admin-users'
		);
		add_submenu_page(
			'movingusers',
			__( 'Export' ),
			__( 'Export' ),
			'manage_options',
			'movingusers-generate-json',
			array( $this, 'generate_json_page' )
		);
		add_submenu_page(
			'movingusers',
			__( 'Import' ),
			__( 'Import' ),
			'manage_options',
			'movingusers-update-db',
			array( $this, 'update_db_page' )
		);
	}

	/** ==================================================
	 * Export Generate Json
	 *
	 * @since 1.00
	 */
	public function generate_json_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$scriptname = admin_url( 'admin.php?page=movingusers-generate-json' );

		if ( isset( $_POST['Jsonmailsend'] ) && ! empty( $_POST['Jsonmailsend'] ) ) {
			if ( check_admin_referer( 'zm_file_json', 'movingusers_file_json' ) ) {
				if ( ! empty( $_POST['mail_send'] ) ) {
					update_option( 'moving_users_mail_send', true );
				} else {
					update_option( 'moving_users_mail_send', false );
				}
				echo '<div class="notice notice-success is-dismissible"><ul><li>' . esc_html( __( 'Settings' ) . ' --> ' . __( 'Changes saved.' ) ) . '</li></ul></div>';
			}
		}

		if ( isset( $_POST['Cnumber'] ) && ! empty( $_POST['Cnumber'] ) ) {
			if ( check_admin_referer( 'zm_file_json', 'movingusers_file_json' ) ) {
				if ( ! empty( $_POST['number_files'] ) ) {
					update_option( 'moving_users_number_files', absint( $_POST['number_files'] ) );
					echo '<div class="notice notice-success is-dismissible"><ul><li>' . esc_html( __( 'Settings' ) . ' --> ' . __( 'Changes saved.' ) ) . '</li></ul></div>';
				}
			}
		}

		do_action( 'movingusers_logs_check_files', 'moving_users_export_files' );

		if ( isset( $_POST['Cjson'] ) && ! empty( $_POST['Cjson'] ) ) {
			if ( check_admin_referer( 'zm_file_json', 'movingusers_file_json' ) ) {
				do_action( 'movingusers_generate_json_hook' );
			}
		}
		if ( isset( $_POST['Djson'] ) && ! empty( $_POST['Djson'] ) ) {
			if ( check_admin_referer( 'zm_file_json', 'movingusers_file_json' ) ) {
				if ( ! empty( $_POST['delete_files'] ) ) {
					$delete_files = filter_var(
						wp_unslash( $_POST['delete_files'] ),
						FILTER_CALLBACK,
						array(
							'options' => function ( $value ) {
								return sanitize_text_field( $value );
							},
						)
					);
					do_action( 'movingusers_logs_slice_delete', 'moving_users_export_files', $delete_files );
				}
			}
		}

		?>
		<div class="wrap">

		<h2>Moving Users <a href="<?php echo esc_url( admin_url( 'admin.php?page=movingusers-generate-json' ) ); ?>" style="text-decoration: none;"><?php esc_html_e( 'Export' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=movingusers-update-db' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import' ); ?></a>
			<?php
			if ( class_exists( 'MovingContents' ) ) {
				$movingcontents_url = admin_url( 'admin.php?page=movingcontents' );
			} elseif ( is_multisite() ) {
					$movingcontents_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-contents' );
			} else {
				$movingcontents_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-contents' );
			}
			?>
			<a href="<?php echo esc_url( $movingcontents_url ); ?>" class="page-title-action">Moving Contents</a>
			<?php
			if ( class_exists( 'MovingMediaLibrary' ) ) {
				$movingmedialibrary_url = admin_url( 'admin.php?page=movingmedialibrary' );
			} elseif ( is_multisite() ) {
					$movingmedialibrary_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
			} else {
				$movingmedialibrary_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
			}
			?>
			<a href="<?php echo esc_url( $movingmedialibrary_url ); ?>" class="page-title-action">Moving Media Library</a>
		</h2>
		<div style="clear: both;"></div>

		<h3><?php esc_html_e( 'Export' ); ?></h3>
		<form method="post" action="<?php echo esc_url( $scriptname ); ?>">
		<div style="margin: 5px; padding: 5px;">
			<p class="description">
			<?php esc_html_e( 'Export the data related to the users to JSON files.', 'moving-users' ); ?>
			</p>
			<?php wp_nonce_field( 'zm_file_json', 'movingusers_file_json' ); ?>
			<div style="margin: 5px; padding: 5px; vertical-align: middle;">
			<input type="checkbox" name="mail_send" value="1" <?php checked( get_option( 'moving_users_mail_send' ), true ); ?>>
			<?php esc_html_e( 'Send the exported JSON file by e-mail', 'moving-users' ); ?>
			<?php submit_button( __( 'Change' ), 'large', 'Jsonmailsend', false, array( 'style' => 'vertical-align: middle;' ) ); ?>
			</div>
			<?php submit_button( __( 'Export as JSON' ), 'large', 'Cjson', true ); ?>
		</div>
		<?php
		$logs = get_option( 'moving_users_export_files' );
		if ( ! empty( $logs ) ) {
			?>
			<h3>JSON <?php esc_html_e( 'File', 'moving-users' ); ?></h3>
			<div style="margin: 5px; padding: 5px;">
			<?php esc_html_e( 'Number of latest files to keep', 'moving-users' ); ?> : 
			<input type="number" name="number_files" value="<?php echo esc_attr( get_option( 'moving_users_number_files', 5 ) ); ?>" min="1" max="100" step="1" style="width: 70px;" />
			<?php submit_button( __( 'Change' ), 'large', 'Cnumber', false ); ?>
			<?php submit_button( __( 'Delete' ), 'large', 'Djson', true ); ?>
			<table border=1 cellspacing="0" cellpadding="5" bordercolor="#000000" style="border-collapse: collapse;">
			<tr>
			<th><?php esc_html_e( 'Delete' ); ?></th>
			<th><?php esc_html_e( 'Name' ); ?></th>
			<th><?php esc_html_e( 'Date/time' ); ?></th>
			<th><?php esc_html_e( 'Size' ); ?></th>
			<th><?php esc_html_e( 'Action' ); ?></th>
			</tr>
			<?php
			foreach ( $logs as $value ) {
				$json_filename = $this->upload_dir . $value;
				$json_fileurl = $this->upload_url . $value;
				if ( file_exists( $json_filename ) ) {
					if ( function_exists( 'wp_date' ) ) {
						$json_time = wp_date( 'Y-m-d H:i:s', filemtime( $json_filename ) );
					} else {
						$json_time = date_i18n( 'Y-m-d H:i:s', filemtime( $json_filename ) );
					}
					$json_byte = filesize( $json_filename );
					$json_size = size_format( $json_byte, 2 );
					?>
					<tr>
					<td>
					<input type="checkbox" name="delete_files[]" value="<?php echo esc_attr( $value ); ?>">
					</td>
					<td>
					<?php echo esc_html( $value ); ?>
					</td>
					<td>
					<?php echo esc_html( $json_time ); ?>
					</td>
					<td>
					<?php echo esc_html( $json_size ); ?>
					</td>
					<td>
					<button type="button" class="button button-large" onclick="location.href='<?php echo esc_url( $json_fileurl ); ?>'"><?php esc_html_e( 'View' ); ?></button>
					&nbsp;
					<a href="<?php echo esc_url( $json_fileurl ); ?>" download="<?php echo esc_attr( $value ); ?>"><button type="button" class="button button-large"><?php esc_html_e( 'Download', 'moving-users' ); ?></button></a>
					</td>
					</tr>
					<?php
				}
			}
			?>
			</table>
			<?php submit_button( __( 'Delete' ), 'large', 'Djson', true ); ?>
			</div>
			<?php
		}
		?>
		</form>

		</div>

		<?php
	}

	/** ==================================================
	 * Import update db page
	 *
	 * @since 1.00
	 */
	public function update_db_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$scriptname = admin_url( 'admin.php?page=movingusers-update-db' );

		$max_upload_size = wp_max_upload_size();
		if ( ! $max_upload_size ) {
			$max_upload_size = 0;
		}
		if ( isset( $_SERVER['CONTENT_LENGTH'] ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) ) {
			if ( 0 < $max_upload_size && $max_upload_size < intval( $_SERVER['CONTENT_LENGTH'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><ul><li>' . esc_html__( 'This is larger than the maximum size. Please try another.' ) . '</li></ul></div>';
			}
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wp_filesystem = new WP_Filesystem_Direct( false );

		$import_html = null;
		if ( isset( $_POST['Import'] ) && ! empty( $_POST['Import'] ) ) {
			if ( check_admin_referer( 'mu_file_load', 'movingusers_import_file_load' ) ) {
				if ( isset( $_FILES['filename']['tmp_name'] ) && ! empty( $_FILES['filename']['tmp_name'] ) &&
						isset( $_FILES['filename']['name'] ) && ! empty( $_FILES['filename']['name'] ) &&
						isset( $_FILES['filename']['type'] ) && ! empty( $_FILES['filename']['type'] ) &&
						isset( $_FILES['filename']['error'] ) ) {
					if ( 0 === intval( wp_unslash( $_FILES['filename']['error'] ) ) ) {
						$tmp_file_path_name = wp_strip_all_tags( wp_unslash( wp_normalize_path( $_FILES['filename']['tmp_name'] ) ) );
						$tmp_file_name = sanitize_file_name( wp_basename( $tmp_file_path_name ) );
						$tmp_path_name = str_replace( $tmp_file_name, '', $tmp_file_path_name );
						$tmp_file_path_name = $tmp_path_name . $tmp_file_name;
						$filename = sanitize_file_name( wp_unslash( $_FILES['filename']['name'] ) );
						$mimetype = sanitize_text_field( wp_unslash( $_FILES['filename']['type'] ) );
						$filetype = wp_check_filetype( $filename );
						if ( ! $filetype['ext'] && ! current_user_can( 'unfiltered_upload' ) ) {
							echo '<div class="notice notice-error is-dismissible"><ul><li>' . esc_html__( 'Sorry, this file type is not permitted for security reasons.' ) . '</li></ul></div>';
						} else {
							$filetype2 = wp_check_filetype( $filename, array( $filetype['ext'] => $mimetype ) );
							if ( ! empty( $filetype2['type'] ) ) {
								$json_file = wp_normalize_path( $this->upload_dir . $filename );
								$move = $wp_filesystem->move( $tmp_file_path_name, $json_file, true );
								if ( $move ) {
									if ( ! empty( $_POST['all_clear'] ) ) {
										$all_clear = true;
									} else {
										$all_clear = false;
									}
									if ( ! empty( $_POST['current_user_id'] ) ) {
										$uid = get_current_user_id();
									} else {
										$uid = 0;
									}
									if ( ! empty( $_POST['search_url'] ) && ! empty( $_POST['replace_url'] ) ) {
										$search_url = esc_url_raw( wp_unslash( $_POST['search_url'] ) );
										$replace_url = esc_url_raw( wp_unslash( $_POST['replace_url'] ) );
									} else {
										$search_url = null;
										$replace_url = null;
									}
									$user_ids = array();
									if ( ! empty( $_POST['user_ids'] ) ) {
										$user_ids = filter_var(
											wp_unslash( $_POST['user_ids'] ),
											FILTER_CALLBACK,
											array(
												'options' => function ( $value ) {
													return absint( $value );
												},
											)
										);
										$user_ids = array_flip( $user_ids );
									}
									if ( $all_clear ) {
										do_action( 'movingusers_clear_db_hook', get_current_user_id() );
									}
									do_action( 'movingusers_update_db_hook', $json_file, get_current_user_id() );
									wp_delete_file( $json_file );
								} else {
									echo '<div class="notice notice-error is-dismissible"><ul><li>' . esc_html__( 'Could not copy file.' ) . '</li></ul></div>';
								}
							} else {
								echo '<div class="notice notice-error is-dismissible"><ul><li>' . esc_html__( 'Sorry, this file type is not permitted for security reasons.' ) . '</li></ul></div>';
							}
						}
					} else {
						echo '<div class="notice notice-error is-dismissible"><ul><li>' . esc_html__( 'No such file exists! Double check the name and try again.' ) . '</li></ul></div>';
					}
				}
			}
		}

		?>
		<div class="wrap">

		<h2>Moving Users <a href="<?php echo esc_url( admin_url( 'admin.php?page=movingusers-update-db' ) ); ?>" style="text-decoration: none;"><?php esc_html_e( 'Import' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=movingusers-generate-json' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export' ); ?></a>
			<?php
			if ( class_exists( 'MovingContents' ) ) {
				$movingcontents_url = admin_url( 'admin.php?page=movingcontents' );
			} elseif ( is_multisite() ) {
					$movingcontents_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-contents' );
			} else {
				$movingcontents_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-contents' );
			}
			?>
			<a href="<?php echo esc_url( $movingcontents_url ); ?>" class="page-title-action">Moving Contents</a>
			<?php
			if ( class_exists( 'MovingMediaLibrary' ) ) {
				$movingmedialibrary_url = admin_url( 'admin.php?page=movingmedialibrary' );
			} elseif ( is_multisite() ) {
					$movingmedialibrary_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
			} else {
				$movingmedialibrary_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
			}
			?>
			<a href="<?php echo esc_url( $movingmedialibrary_url ); ?>" class="page-title-action">Moving Media Library</a>
		</h2>
		<div style="clear: both;"></div>

		<h3><?php esc_html_e( 'Read JSON file', 'moving-users' ); ?></h3>
		<div style="margin: 5px; padding: 5px;">
			<form method="post" action="<?php echo esc_url( $scriptname ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'mu_file_load', 'movingusers_import_file_load' ); ?>
			<div style="padding: 5px;">
			<input type="checkbox" name="all_clear" value="1" checked="checked" />
			<span style="color: red;"><?php esc_html_e( 'Caution', 'moving-users' ); ?></span> : 
			<strong><?php esc_html_e( 'Clear all current users-related databases when importing', 'moving-users' ); ?></strong>
			<p class="description" style="padding: 0 25px 0;">
			<?php esc_html_e( 'Excluding the user executing the import', 'moving-users' ); ?>
			</p>
			</div>
			<?php
			if ( 0 == $max_upload_size ) {
				$limit_str = __( 'No limit', 'moving-users' );
			} else {
				$limit_str = size_format( $max_upload_size, 0 );
			}
			?>
			<div style="padding: 5px;">
			<?php
			/* translators: Maximum upload file size */
			echo esc_html( sprintf( __( 'Maximum upload file size: %s.' ), $limit_str ) );
			?>
			</div>
			<div style="padding: 5px;">
			<input name="filename" type="file" accept="application/json" size="80" />
			<?php submit_button( __( 'Import from JSON', 'moving-users' ), 'large', 'Import', false ); ?>
			</div>
			</form>
		</div>

		</div>
		<?php
	}

	/** ==================================================
	 * Main
	 *
	 * @since 1.00
	 */
	public function manage_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		?>
		<div class="wrap">

		<h2>Moving Users
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=movingusers-generate-json' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=movingusers-update-db' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import' ); ?></a>
			<?php
			if ( class_exists( 'MovingContents' ) ) {
				$movingcontents_url = admin_url( 'admin.php?page=movingcontents' );
			} elseif ( is_multisite() ) {
					$movingcontents_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-contents' );
			} else {
				$movingcontents_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-contents' );
			}
			?>
			<a href="<?php echo esc_url( $movingcontents_url ); ?>" class="page-title-action">Moving Contents</a>
			<?php
			if ( class_exists( 'MovingMediaLibrary' ) ) {
				$movingmedialibrary_url = admin_url( 'admin.php?page=movingmedialibrary' );
			} elseif ( is_multisite() ) {
					$movingmedialibrary_url = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
			} else {
				$movingmedialibrary_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=moving-media-library' );
			}
			?>
			<a href="<?php echo esc_url( $movingmedialibrary_url ); ?>" class="page-title-action">Moving Media Library</a>
		</h2>
		<div style="clear: both;"></div>

		<h3><?php esc_html_e( 'Supports the transfer of Users between servers.', 'moving-users' ); ?></h3>

		<?php $this->credit(); ?>

		</div>
		<?php
	}

	/** ==================================================
	 * Credit
	 *
	 * @since 1.00
	 */
	private function credit() {

		$plugin_name    = null;
		$plugin_ver_num = null;
		$plugin_path    = plugin_dir_path( __DIR__ );
		$plugin_dir     = untrailingslashit( wp_normalize_path( $plugin_path ) );
		$slugs          = explode( '/', $plugin_dir );
		$slug           = end( $slugs );
		$files          = scandir( $plugin_dir );
		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file || is_dir( $plugin_path . $file ) ) {
				continue;
			} else {
				$exts = explode( '.', $file );
				$ext  = strtolower( end( $exts ) );
				if ( 'php' === $ext ) {
					$plugin_datas = get_file_data(
						$plugin_path . $file,
						array(
							'name'    => 'Plugin Name',
							'version' => 'Version',
						)
					);
					if ( array_key_exists( 'name', $plugin_datas ) && ! empty( $plugin_datas['name'] ) && array_key_exists( 'version', $plugin_datas ) && ! empty( $plugin_datas['version'] ) ) {
						$plugin_name    = $plugin_datas['name'];
						$plugin_ver_num = $plugin_datas['version'];
						break;
					}
				}
			}
		}
		$plugin_version = __( 'Version:' ) . ' ' . $plugin_ver_num;
		/* translators: FAQ Link & Slug */
		$faq       = sprintf( __( 'https://wordpress.org/plugins/%s/faq', 'moving-users' ), $slug );
		$support   = 'https://wordpress.org/support/plugin/' . $slug;
		$review    = 'https://wordpress.org/support/view/plugin-reviews/' . $slug;
		$translate = 'https://translate.wordpress.org/projects/wp-plugins/' . $slug;
		$facebook  = 'https://www.facebook.com/katsushikawamori/';
		$twitter   = 'https://twitter.com/dodesyo312';
		$youtube   = 'https://www.youtube.com/channel/UC5zTLeyROkvZm86OgNRcb_w';
		$donate    = __( 'https://shop.riverforest-wp.info/donate/', 'moving-users' );

		?>
		<span style="font-weight: bold;">
		<div>
		<?php echo esc_html( $plugin_version ); ?> | 
		<a style="text-decoration: none;" href="<?php echo esc_url( $faq ); ?>" target="_blank" rel="noopener noreferrer">FAQ</a> | <a style="text-decoration: none;" href="<?php echo esc_url( $support ); ?>" target="_blank" rel="noopener noreferrer">Support Forums</a> | <a style="text-decoration: none;" href="<?php echo esc_url( $review ); ?>" target="_blank" rel="noopener noreferrer">Reviews</a>
		</div>
		<div>
		<a style="text-decoration: none;" href="<?php echo esc_url( $translate ); ?>" target="_blank" rel="noopener noreferrer">
		<?php
		/* translators: Plugin translation link */
		echo esc_html( sprintf( __( 'Translations for %s' ), $plugin_name ) );
		?>
		</a> | <a style="text-decoration: none;" href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-facebook"></span></a> | <a style="text-decoration: none;" href="<?php echo esc_url( $twitter ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-twitter"></span></a> | <a style="text-decoration: none;" href="<?php echo esc_url( $youtube ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-video-alt3"></span></a>
		</div>
		</span>

		<div style="width: 250px; height: 180px; margin: 5px; padding: 5px; border: #CCC 2px solid;">
		<h3><?php esc_html_e( 'Please make a donation if you like my work or would like to further the development of this plugin.', 'moving-users' ); ?></h3>
		<div style="text-align: right; margin: 5px; padding: 5px;"><span style="padding: 3px; color: #ffffff; background-color: #008000">Plugin Author</span> <span style="font-weight: bold;">Katsushi Kawamori</span></div>
		<button type="button" style="margin: 5px; padding: 5px;" onclick="window.open('<?php echo esc_url( $donate ); ?>')"><?php esc_html_e( 'Donate to this plugin &#187;' ); ?></button>
		</div>

		<?php
	}

	/** ==================================================
	 * Allow upload json
	 *
	 * @param array $mimes  mimes.
	 * @since 1.00
	 */
	public function allow_upload_json( $mimes ) {
		$mimes['json'] = 'application/json';
		return $mimes;
	}
}


