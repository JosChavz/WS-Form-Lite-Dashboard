<?php
	/**
	 * Plugin Name: WS Form Submissions
	 * Description: Display WS Form submissions on the front-end.
	 * Version: 1.0.0
	 * Author: your-name
	 * Author URI: your-url
	 */

// Exit if accessed directly.
	use WSFormSubmissionsDisplay\Services\CustomMask\GeneralCustomMask;
	use WSFormSubmissionsDisplay\Services\CustomMask\PhotoCustomMask;
	use WSFormSubmissionsDisplay\Utilities\Logger;
	
	if (!defined('ABSPATH')) {
		exit;
	}

// Define plugin constants
	define('WFS_FRONTEND_PATH', plugin_dir_path(__FILE__));
	define('WFS_FRONTEND_URL', plugin_dir_url(__FILE__));
	
	/**
	 * Register and enqueue assets
	 *
	 * @return void
	 */
	function register_assets(): void {
		wp_enqueue_script('wfs-frontend-js', WFS_FRONTEND_URL . '/assets/wfs-dialog.js', ['jquery']);
		
		wp_register_style('wfs-frontend-css', WFS_FRONTEND_URL . '/assets/wfs-styles.css', [], '1.0', 'all');
		
		wp_enqueue_style('wfs-frontend-css');
	}
	add_action('wp_enqueue_scripts', 'register_assets');
	
	/**
	 * Fetch WS Form fields and labels.
	 *
	 * @param int $form_id The ID of the form.
	 * @return array The form fields and their labels.
	 */
	function get_ws_form_fields(int $form_id ): array
    {
		// Load WS Form classes.
		if ( ! class_exists( 'WS_Form_Submit' ) ) {
			return array();
		}
		
		$ws_form_submit         = new WS_Form_Submit();
		$ws_form_submit->form_id = $form_id;
		$submit_fields          = $ws_form_submit->db_get_submit_fields();
        var_dump($submit_fields);
		
		$fields = array();
		if ($submit_fields) {
			foreach ( $submit_fields as $id => $field ) {
				$fields[ 'field_' . $id ] = $field['label'];
			}
		}
		
		return $fields;
	}
	
	/**
	 * Fetch WS Form submissions.
	 *
	 * @param int $form_id The ID of the form.
	 * @return array The form submissions.
	 */
	function get_ws_form_submissions(int $form_id, $limit = 100, $current_user_id = 0 ): array
    {
		// Load WS Form classes.
		if ( ! class_exists( 'WS_Form_Submit' ) ) {
			return array();
		}
		
		$ws_form_submit         = new WS_Form_Submit();
		$ws_form_submit->form_id = $form_id;
		
		// Retrieve the submissions.
		return $ws_form_submit->db_read_all(
			$ws_form_submit->get_search_join(),
			$ws_form_submit->get_where([
				[
					'field' => 'user_id',
					'operator' => '==',
					'value' => $current_user_id,
				]
			]),
			$ws_form_submit->get_search_group_by(),
			$ws_form_submit->get_search_order_by(),
			$limit, // Limit.
			0, // Offset.
			true, // Get meta.
			true, // Get expanded.
			false, // Bypass user capability check.
			false // Clear hidden fields.
		);
	}
	
	/**
	 * Displays the submission table which contains the ID, Date, and a Button to open the
	 * dialog
	 *
	 * @param $submissions
	 * @return void
	 */
	function render_submissions_table($submissions): void {
		?>
		<table class="wfs-submissions-table">
			<thead class="wfs-header">
			<tr class="wfs-header-row">
				<th>ID</th>
				<th>Date</th>
				<th>Actions</th>
			</tr>
			</thead>
			<tbody class="wfs-body">
			<?php foreach ($submissions as $submission) : ?>
                <tr class="wfs-body-row">
                    <td data-label="Name"><?php echo esc_html($submission->id); ?></td>
                    <td data-label="Date"><?php echo esc_html($submission->date_added); ?></td>
                    <td data-label="Actions">
                        <button id="submission_<?php echo esc_attr($submission->id); ?>-button" class="wfs-button">View</button>
                    </td>
                </tr>
		<?php endforeach; ?>
            </tbody>
        </table>
        <?php
	}

    /**
     * Render submission dialogs
     *
     * @param array $submissions
     * @param array $fields
     * @return void
     */
	function render_dialogs(array $submissions, array $fields): void {
		foreach ($submissions as $submission) :
			?>
			<dialog class="wfs-form-dialog" id="submission_<?php echo esc_attr($submission->id); ?>-dialog">
				<form method="dialog">
					<header>
						<h2><?php echo esc_html($submission->id); ?></h2>
						<button autofocus type="reset" onclick="this.closest('dialog').close('cancel')">Close</button>
					</header>
					<table id="wfs_dialog_table-<?php echo esc_attr($submission->id); ?>" class="wfs_form_table">
						<?php
                            foreach ($fields as $field_id => $label) {
                                $value = $submission->meta[$field_id]['value'];
                                render_dialog_row($label, $value);
							}
						?>
					</table>
				</form>
			</dialog>
			<?php endforeach;
	}

    /**
     * Renders the row for the dialog using its passed label and value(s)
     * If the value is an array, the result will be a joined string with comma delimiter
     *
     * @param string $label
     * @param mixed $value
     * @return void
     */
    function render_dialog_row(string $label, mixed $value) : void {
        ?>
        <tr style="display: table-row;">
            <th><?php echo esc_html($label) ?></th>
            <td style="padding: 12px 10px; display: table-cell;">
                <?php
                    if ( !is_array($value) ) echo esc_html($value);
                    else echo esc_html(implode(', ', $value));
                ?>
            </td>
        </tr>

        <?php
    }
	
	/**
	 * Display WS Form submissions on the front-end.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The HTML output for the form submissions.
	 */
	function display_ws_form_submissions(array $atts ): string
    {
		$atts = shortcode_atts(
			array(
				'form_id' => 1,
				'limit'   => 100,
			),
			$atts,
			'ws_form_submissions'
		);
		
		$form_id          = intval( $atts['form_id'] );
		$limit            = intval( $atts['limit'] );
		$current_user_id  = get_current_user_id();
		
		if (!$current_user_id) {
			return '<p>Sorry, you are not logged in.</p>';
		}
		
		$fields       = get_ws_form_fields( $form_id );
		$submissions  = get_ws_form_submissions( $form_id, $limit, $current_user_id );
        var_dump($submissions[0]->meta);
		
		if ( empty( $submissions ) ) {
			return '<p>No submissions found.</p>';
		}
		
		ob_start();

		render_submissions_table($submissions);
		render_dialogs($submissions, $fields);

		return ob_get_clean();
	}
	add_shortcode( 'ws_form_submissions', 'display_ws_form_submissions' );