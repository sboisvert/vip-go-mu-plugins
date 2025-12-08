<?php
/**
 * Plugin Name: VIP Upload Plugin
 * Plugin URI: https://github.com/Automattic/vip-go-mu-plugins
 * Description: Upload files to custom paths within wp-uploads on WordPress VIP infrastructure.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: https://automattic.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vip-upload-plugin
 *
 * @package VIP_Upload_Plugin
 */

namespace Automattic\VIP\Upload_Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class VIP_Upload_Plugin {

	/**
	 * Maximum path length allowed by VIP Files Service.
	 */
	const MAX_PATH_LENGTH = 255;

	/**
	 * Capability required to use this plugin.
	 */
	const REQUIRED_CAPABILITY = 'upload_files';

	/**
	 * Nonce action name.
	 */
	const NONCE_ACTION = 'vip_upload_plugin_upload';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'vip_upload_nonce';

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_upload' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'VIP File Upload', 'vip-upload-plugin' ),
			__( 'VIP File Upload', 'vip-upload-plugin' ),
			self::REQUIRED_CAPABILITY,
			'vip-upload-plugin',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_styles( $hook_suffix ) {
		if ( 'tools_page_vip-upload-plugin' !== $hook_suffix ) {
			return;
		}

		wp_add_inline_style(
			'common',
			'.vip-upload-form { max-width: 600px; }
			.vip-upload-form .form-table th { width: 150px; }
			.vip-upload-notice { margin: 15px 0; }
			.vip-upload-path-info { background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1; margin: 15px 0; }
			.vip-upload-path-info code { background: #fff; padding: 2px 6px; }'
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		// Check permissions.
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vip-upload-plugin' ) );
		}

		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_path  = $upload_dir['basedir'];

		// Display any notices from upload handling.
		$this->display_notices();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'VIP File Upload', 'vip-upload-plugin' ); ?></h1>

			<div class="vip-upload-path-info">
				<h3><?php esc_html_e( 'Upload Directory Information', 'vip-upload-plugin' ); ?></h3>
				<p>
					<strong><?php esc_html_e( 'Base Path:', 'vip-upload-plugin' ); ?></strong>
					<code><?php echo esc_html( $base_path ); ?></code>
				</p>
				<p>
					<strong><?php esc_html_e( 'Base URL:', 'vip-upload-plugin' ); ?></strong>
					<code><?php echo esc_html( $base_url ); ?></code>
				</p>
				<?php if ( is_multisite() ) : ?>
					<p>
						<strong><?php esc_html_e( 'Multisite:', 'vip-upload-plugin' ); ?></strong>
						<?php
						printf(
							/* translators: %d: blog ID */
							esc_html__( 'Yes (Site ID: %d)', 'vip-upload-plugin' ),
							get_current_blog_id()
						);
						?>
					</p>
				<?php endif; ?>
				<p>
					<em><?php esc_html_e( 'Files will be uploaded relative to the Base Path shown above.', 'vip-upload-plugin' ); ?></em>
				</p>
			</div>

			<form method="post" enctype="multipart/form-data" class="vip-upload-form">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="vip_upload_file"><?php esc_html_e( 'File', 'vip-upload-plugin' ); ?></label>
							</th>
							<td>
								<input type="file" name="vip_upload_file" id="vip_upload_file" required />
								<p class="description">
									<?php esc_html_e( 'Select the file to upload. File types are restricted by WordPress.', 'vip-upload-plugin' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="vip_upload_path"><?php esc_html_e( 'Path', 'vip-upload-plugin' ); ?></label>
							</th>
							<td>
								<input type="text" name="vip_upload_path" id="vip_upload_path" class="regular-text"
									placeholder="<?php esc_attr_e( 'custom/path', 'vip-upload-plugin' ); ?>" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: example path */
										esc_html__( 'Optional subdirectory path within uploads (e.g., %s). Leave empty to use the default year/month structure.', 'vip-upload-plugin' ),
										'<code>custom/subfolder</code>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="vip_upload_filename"><?php esc_html_e( 'Filename', 'vip-upload-plugin' ); ?></label>
							</th>
							<td>
								<input type="text" name="vip_upload_filename" id="vip_upload_filename" class="regular-text"
									placeholder="<?php esc_attr_e( 'custom-filename.ext', 'vip-upload-plugin' ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Optional custom filename. Leave empty to use the original filename. Include the file extension.', 'vip-upload-plugin' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Upload File', 'vip-upload-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the file upload form submission.
	 */
	public function handle_upload() {
		// Check if we're on the right page and form was submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			$this->add_notice( 'error', __( 'Security check failed. Please try again.', 'vip-upload-plugin' ) );
			return;
		}

		// Check permissions.
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			$this->add_notice( 'error', __( 'You do not have permission to upload files.', 'vip-upload-plugin' ) );
			return;
		}

		// Check if file was uploaded.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload handling.
		if ( ! isset( $_FILES['vip_upload_file'] ) || empty( $_FILES['vip_upload_file']['name'] ) ) {
			$this->add_notice( 'error', __( 'Please select a file to upload.', 'vip-upload-plugin' ) );
			return;
		}

		// Get and sanitize form inputs.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in process_upload.
		$file     = $_FILES['vip_upload_file'];
		$path     = isset( $_POST['vip_upload_path'] ) ? sanitize_text_field( wp_unslash( $_POST['vip_upload_path'] ) ) : '';
		$filename = isset( $_POST['vip_upload_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['vip_upload_filename'] ) ) : '';

		// Process the upload.
		$result = $this->process_upload( $file, $path, $filename );

		if ( is_wp_error( $result ) ) {
			$this->add_notice( 'error', $result->get_error_message() );
		} else {
			$this->add_notice(
				'success',
				sprintf(
					/* translators: 1: file URL */
					__( 'File uploaded successfully! URL: %s', 'vip-upload-plugin' ),
					'<a href="' . esc_url( $result['url'] ) . '" target="_blank">' . esc_html( $result['url'] ) . '</a>'
				)
			);
		}
	}

	/**
	 * Process the file upload.
	 *
	 * @param array  $file     The $_FILES array element for the uploaded file.
	 * @param string $path     The subdirectory path within uploads.
	 * @param string $filename The custom filename (optional).
	 * @return array|WP_Error Array with 'file' and 'url' on success, WP_Error on failure.
	 */
	public function process_upload( $file, $path = '', $filename = '' ) {
		// Validate the uploaded file.
		$validation = $this->validate_upload( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Sanitize and validate the path.
		$path = $this->sanitize_path( $path );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		// Determine the filename.
		$original_filename = sanitize_file_name( $file['name'] );
		$target_filename   = ! empty( $filename ) ? sanitize_file_name( $filename ) : $original_filename;

		// Ensure the filename has an extension.
		if ( ! $this->has_valid_extension( $target_filename ) ) {
			// Try to get extension from original file.
			$original_ext = pathinfo( $original_filename, PATHINFO_EXTENSION );
			if ( $original_ext ) {
				$target_filename .= '.' . $original_ext;
			} else {
				return new \WP_Error(
					'invalid_filename',
					__( 'Filename must include a valid file extension.', 'vip-upload-plugin' )
				);
			}
		}

		// Validate file extension matches MIME type.
		$file_type = wp_check_filetype( $target_filename );
		if ( ! $file_type['type'] ) {
			return new \WP_Error(
				'invalid_filetype',
				__( 'This file type is not allowed.', 'vip-upload-plugin' )
			);
		}

		// Read the file contents.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading uploaded temp file.
		$file_contents = file_get_contents( $file['tmp_name'] );
		if ( false === $file_contents ) {
			return new \WP_Error(
				'read_error',
				__( 'Failed to read the uploaded file.', 'vip-upload-plugin' )
			);
		}

		// Build the full path for the file.
		$subdir = '';
		if ( ! empty( $path ) ) {
			$subdir = '/' . trim( $path, '/' );
		}

		// Check path length constraint.
		$upload_dir    = wp_upload_dir();
		$full_filepath = $upload_dir['basedir'] . $subdir . '/' . $target_filename;

		// VIP Files Service has a 255 character path limit.
		// Calculate the relative path from wp-content/uploads.
		$relative_path = 'wp-content/uploads' . str_replace( $upload_dir['basedir'], '', $full_filepath );
		if ( strlen( $relative_path ) > self::MAX_PATH_LENGTH ) {
			return new \WP_Error(
				'path_too_long',
				sprintf(
					/* translators: %d: maximum path length */
					__( 'The full file path exceeds the maximum allowed length of %d characters.', 'vip-upload-plugin' ),
					self::MAX_PATH_LENGTH
				)
			);
		}

		// Use a custom upload directory filter if path is specified.
		if ( ! empty( $subdir ) ) {
			$upload_dir_filter = function ( $uploads ) use ( $subdir ) {
				$uploads['subdir'] = $subdir;
				$uploads['path']   = $uploads['basedir'] . $subdir;
				$uploads['url']    = $uploads['baseurl'] . $subdir;
				return $uploads;
			};
			add_filter( 'upload_dir', $upload_dir_filter );
		}

		// Upload the file using WordPress's built-in function.
		// wp_upload_bits() handles the VIP filesystem integration automatically.
		$upload_result = wp_upload_bits( $target_filename, null, $file_contents );

		// Remove the filter.
		if ( ! empty( $subdir ) ) {
			remove_filter( 'upload_dir', $upload_dir_filter );
		}

		// Check for errors.
		if ( ! empty( $upload_result['error'] ) ) {
			return new \WP_Error(
				'upload_error',
				$upload_result['error']
			);
		}

		return [
			'file' => $upload_result['file'],
			'url'  => $upload_result['url'],
			'type' => $upload_result['type'],
		];
	}

	/**
	 * Validate the uploaded file.
	 *
	 * @param array $file The $_FILES array element.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function validate_upload( $file ) {
		// Check for upload errors.
		if ( ! empty( $file['error'] ) ) {
			$error_messages = [
				UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'vip-upload-plugin' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'vip-upload-plugin' ),
				UPLOAD_ERR_PARTIAL    => __( 'The uploaded file was only partially uploaded.', 'vip-upload-plugin' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'vip-upload-plugin' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder.', 'vip-upload-plugin' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'vip-upload-plugin' ),
				UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'vip-upload-plugin' ),
			];

			$error_message = isset( $error_messages[ $file['error'] ] )
				? $error_messages[ $file['error'] ]
				: __( 'Unknown upload error.', 'vip-upload-plugin' );

			return new \WP_Error( 'upload_error', $error_message );
		}

		// Verify the file was actually uploaded via HTTP POST.
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error(
				'invalid_upload',
				__( 'Invalid file upload.', 'vip-upload-plugin' )
			);
		}

		// Check file size (VIP allows up to 4GB, but we'll also respect PHP limits).
		$max_size = wp_max_upload_size();
		if ( $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					__( 'The uploaded file exceeds the maximum allowed size of %s.', 'vip-upload-plugin' ),
					size_format( $max_size )
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize and validate the upload path.
	 *
	 * @param string $path The user-provided path.
	 * @return string|WP_Error Sanitized path or WP_Error on failure.
	 */
	private function sanitize_path( $path ) {
		// Empty path is valid (uses default year/month structure).
		if ( empty( $path ) ) {
			return '';
		}

		// Remove leading/trailing slashes and normalize.
		$path = trim( $path, '/' );
		$path = str_replace( '\\', '/', $path );

		// Disallow path traversal attempts.
		if ( preg_match( '/\.\./', $path ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Path traversal is not allowed.', 'vip-upload-plugin' )
			);
		}

		// Disallow absolute paths or paths starting with special characters.
		if ( preg_match( '/^[\/\\\\~]/', $path ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Invalid path format.', 'vip-upload-plugin' )
			);
		}

		// Only allow alphanumeric characters, dashes, underscores, and forward slashes.
		if ( ! preg_match( '/^[a-zA-Z0-9\-_\/]+$/', $path ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Path contains invalid characters. Only letters, numbers, dashes, underscores, and forward slashes are allowed.', 'vip-upload-plugin' )
			);
		}

		// Sanitize each path segment.
		$segments           = explode( '/', $path );
		$sanitized_segments = [];

		foreach ( $segments as $segment ) {
			if ( empty( $segment ) ) {
				continue;
			}
			$sanitized_segment = sanitize_file_name( $segment );
			if ( ! empty( $sanitized_segment ) ) {
				$sanitized_segments[] = $sanitized_segment;
			}
		}

		return implode( '/', $sanitized_segments );
	}

	/**
	 * Check if a filename has a valid extension.
	 *
	 * @param string $filename The filename to check.
	 * @return bool True if the filename has an extension.
	 */
	private function has_valid_extension( $filename ) {
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		return ! empty( $extension );
	}

	/**
	 * Add a notice to be displayed.
	 *
	 * @param string $type    Notice type (error, success, warning, info).
	 * @param string $message The notice message.
	 */
	private function add_notice( $type, $message ) {
		$notices   = get_transient( 'vip_upload_plugin_notices_' . get_current_user_id() ) ?: [];
		$notices[] = [
			'type'    => $type,
			'message' => $message,
		];
		set_transient( 'vip_upload_plugin_notices_' . get_current_user_id(), $notices, 60 );
	}

	/**
	 * Display any stored notices.
	 */
	private function display_notices() {
		$notices = get_transient( 'vip_upload_plugin_notices_' . get_current_user_id() );
		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$class = 'notice vip-upload-notice ';
			switch ( $notice['type'] ) {
				case 'error':
					$class .= 'notice-error';
					break;
				case 'success':
					$class .= 'notice-success';
					break;
				case 'warning':
					$class .= 'notice-warning';
					break;
				default:
					$class .= 'notice-info';
			}

			printf(
				'<div class="%s"><p>%s</p></div>',
				esc_attr( $class ),
				wp_kses(
					$notice['message'],
					[
						'a'    => [
							'href'   => [],
							'target' => [],
						],
						'code' => [],
					]
				)
			);
		}

		// Clear notices after display.
		delete_transient( 'vip_upload_plugin_notices_' . get_current_user_id() );
	}
}

// Initialize the plugin.
new VIP_Upload_Plugin();
