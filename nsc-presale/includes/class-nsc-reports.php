<?php
/**
 * Scam reports: the website form [nsc_report_form] and saving what visitors send.
 * Reports are read in NSC Presale → Scam reports. Nothing is published automatically.
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Reports {

	public static function types() {
		return array(
			'Crypto Rug Pull / Exit Scam',
			'Romance / Pig-Butchering Scam',
			'Phishing / Fake Website',
			'Investment Fraud',
			'NFT Scam',
			'Fake Exchange / Wallet',
			'Ponzi / Pyramid Scheme',
			'Social Media Impersonation',
			'Other Internet Fraud',
		);
	}

	public static function init() {
		add_shortcode( 'nsc_report_form', array( __CLASS__, 'form' ) );
		add_action( 'admin_post_nopriv_nsc_report', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nsc_report', array( __CLASS__, 'handle' ) );
	}

	public static function form() {
		$sent = isset( $_GET['reported'] ) ? sanitize_key( wp_unslash( $_GET['reported'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		ob_start();
		?>
		<form class="report-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<h3 class="rf-title">🚨 SUBMIT SCAM REPORT</h3>
			<?php if ( 'ok' === $sent ) : ?>
				<p class="rf-notice rf-ok">✅ Thank you. Your report was received and will be reviewed before anything is published.</p>
			<?php elseif ( 'err' === $sent ) : ?>
				<p class="rf-notice rf-err">Please describe what happened and how to identify the scammer, then send again.</p>
			<?php elseif ( 'slow' === $sent ) : ?>
				<p class="rf-notice rf-err">You've sent several reports in a short time. Please wait an hour and try again.</p>
			<?php endif; ?>
			<input type="hidden" name="action" value="nsc_report">
			<?php wp_nonce_field( 'nsc_report', 'nsc_report_nonce' ); ?>
			<div class="rf-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
			<div class="form-row">
				<div class="form-group"><label for="rf-name">Your Name (optional)</label><input id="rf-name" type="text" name="reporter_name" maxlength="120" placeholder="Anonymous OK"></div>
				<div class="form-group"><label for="rf-email">Your Email</label><input id="rf-email" type="email" name="reporter_email" maxlength="190" placeholder="For case updates"></div>
			</div>
			<div class="form-group"><label for="rf-type">Scam Type</label>
				<select id="rf-type" name="scam_type" required><option value="">Select scam category...</option>
				<?php foreach ( self::types() as $t ) : ?><option><?php echo esc_html( $t ); ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="form-group"><label for="rf-target">Scammer Wallet / URL / Phone / Email</label><input id="rf-target" type="text" name="target" maxlength="255" required placeholder="0x... or https:// or phone number"></div>
			<div class="form-group"><label for="rf-details">Describe What Happened</label><textarea id="rf-details" name="details" maxlength="5000" required placeholder="Provide as much detail as possible — dates, amounts, platforms used..."></textarea></div>
			<div class="form-group"><label for="rf-amount">Amount Lost (USD)</label><input id="rf-amount" type="number" name="amount_usd" min="0" step="0.01" placeholder="e.g. 500"></div>
			<button class="submit-btn" type="submit">🛡️ SUBMIT REPORT</button>
			<p class="rf-foot">Identity protected · Reviewed before publication · Never share your seed phrase</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle() {
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back = remove_query_arg( 'reported', $back );
		if ( ! isset( $_POST['nsc_report_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsc_report_nonce'] ) ), 'nsc_report' ) ) {
			wp_safe_redirect( add_query_arg( 'reported', 'err', $back ) . '#report' );
			exit;
		}
		// Spam trap: bots fill every field.
		if ( ! empty( $_POST['website'] ) ) {
			wp_safe_redirect( add_query_arg( 'reported', 'ok', $back ) . '#report' );
			exit;
		}
		// 5 reports per visitor per hour.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'nsc_rl_' . md5( $ip . wp_salt() );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			wp_safe_redirect( add_query_arg( 'reported', 'slow', $back ) . '#report' );
			exit;
		}
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		$target  = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
		if ( strlen( $details ) < 10 || '' === $target ) {
			wp_safe_redirect( add_query_arg( 'reported', 'err', $back ) . '#report' );
			exit;
		}
		$type = isset( $_POST['scam_type'] ) ? sanitize_text_field( wp_unslash( $_POST['scam_type'] ) ) : '';
		if ( ! in_array( $type, self::types(), true ) ) {
			$type = 'Other Internet Fraud';
		}
		$amount = isset( $_POST['amount_usd'] ) && '' !== $_POST['amount_usd'] ? round( (float) $_POST['amount_usd'], 2 ) : null;
		global $wpdb;
		$wpdb->insert(
			NSC_DB::t( 'reports' ),
			array(
				'created'        => current_time( 'mysql', true ),
				'reporter_name'  => isset( $_POST['reporter_name'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['reporter_name'] ) ), 0, 120 ) : '',
				'reporter_email' => isset( $_POST['reporter_email'] ) ? substr( sanitize_email( wp_unslash( $_POST['reporter_email'] ) ), 0, 190 ) : '',
				'scam_type'      => $type,
				'target'         => substr( $target, 0, 255 ),
				'details'        => substr( $details, 0, 5000 ),
				'amount_usd'     => $amount,
				'status'         => 'new',
			)
		);
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		$to = nsc_setting( 'report_email' );
		if ( is_email( $to ) ) {
			wp_mail( $to, '[NOSCAM] New scam report: ' . $type, "Type: $type\nTarget: $target\nAmount (USD): " . ( null === $amount ? '-' : $amount ) . "\n\n$details\n\nReview it in WordPress: " . admin_url( 'admin.php?page=nsc-reports' ) );
		}
		wp_safe_redirect( add_query_arg( 'reported', 'ok', $back ) . '#report' );
		exit;
	}
}
