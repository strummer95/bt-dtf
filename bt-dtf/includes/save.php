<?php
/**
 * BT Transfers — Save & Resume module (ported from the DTF Studio - Save snippet)
 *
 * Function names are btdtf_ so they cannot collide with the snippet. The AJAX
 * action names, the wp_btgsb_saves table, the btgsb_saves_db_v1 option, the
 * btgsb_cleanup_saves cron hook and the btgsb_nonce all stay unchanged — they
 * are stored state or contracts the builder and cron already depend on.
 *
 * Original header:
 * PresStora Studio — Save & Resume
 * Saves sheet designs server-side, emails customer a resume link.
 * Sessions expire after 30 days. Auto-cleanup runs nightly.
 */

defined('ABSPATH') || exit;

/* ── DB table creation ───────────────────────────────────────────────── */

/* ── Nightly cleanup of expired saves ───────────────────────────────── */

/* ── AJAX — Save sheet + send email ─────────────────────────────────── */
function btdtf_ajax_save_sheet_email() {
    check_ajax_referer('btgsb_nonce', 'nonce');

    $email   = sanitize_email($_POST['email'] ?? '');
    $designs = $_POST['designs'] ?? '';

    if (!is_email($email))        wp_send_json_error('Please enter a valid email address.');
    if (!$designs)                wp_send_json_error('No design data received.');
    if (strlen($designs) > 5000000) wp_send_json_error('Sheet data too large to save.');

    // Generate unique token
    $token      = bin2hex(random_bytes(24));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    $page_url   = get_permalink(get_page_by_path('gang-sheet-builder')) ?: home_url('/gang-sheet-builder/');
    $resume_url = add_query_arg(['btgsb_resume' => $token], $page_url);

    global $wpdb;
    $table  = $wpdb->prefix . 'btgsb_saves';
    $result = $wpdb->insert($table, [
        'token'      => $token,
        'email'      => $email,
        'designs'    => $designs,
        'expires_at' => $expires_at,
    ]);

    if (!$result) wp_send_json_error('Could not save your sheet. Please try again.');

    // Send the email
    $sent = btdtf_send_resume_email($email, $resume_url, $expires_at);
    if (!$sent) {
        // Still succeeded saving — just warn about email
        wp_send_json_success(['warning' => 'Sheet saved but email could not be sent. Copy this link: ' . $resume_url]);
    }

    wp_send_json_success(['message' => 'Check your email for your resume link!']);
}

/* ── AJAX — Load saved sheet by token ───────────────────────────────── */
function btdtf_ajax_load_save() {
    check_ajax_referer('btgsb_nonce', 'nonce');

    $token = sanitize_text_field($_POST['token'] ?? '');
    if (!$token) wp_send_json_error('Invalid link.');

    global $wpdb;
    $table = $wpdb->prefix . 'btgsb_saves';
    $row   = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE token = %s AND expires_at > %s",
        $token, current_time('mysql')
    ));

    if (!$row) wp_send_json_error('This link has expired or is invalid. Links are valid for 30 days.');

    wp_send_json_success(['designs' => $row->designs, 'expires_at' => $row->expires_at]);
}

/* ── Email sending function ──────────────────────────────────────────── */
function btdtf_send_resume_email($to, $resume_url, $expires_at) {
    $site_name  = get_bloginfo('name');
    $logo_url   = 'https://boomerts.com/wp-content/uploads/2024/01/BT-Logo-250px.png';
    $expires_fmt = date('F j, Y', strtotime($expires_at));
    $from_name  = get_option('woocommerce_email_from_name', $site_name);
    $from_email = 'orders@boomerts.com';
    $subject    = 'Your Saved Gang Sheet — ' . $site_name;

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . esc_html($subject) . '</title>
</head>
<body style="margin:0;padding:0;background:#f0f0f5;font-family:Arial,Helvetica,sans-serif;">

<!-- Wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f0f5;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

  <!-- Header -->
  <tr>
    <td style="background:#1a1060;border-radius:10px 10px 0 0;padding:28px 40px;text-align:center;">
      <img src="' . esc_url($logo_url) . '" alt="Boomer T\'s Ink &amp; Thread" width="160" style="display:block;margin:0 auto 12px;max-width:160px;">
      <p style="margin:0;color:rgba(255,255,255,0.75);font-size:13px;letter-spacing:1px;text-transform:uppercase;">Your Gang Sheet Is Saved</p>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="background:#ffffff;padding:40px 40px 32px;">

      <h2 style="margin:0 0 16px;color:#1a1060;font-size:22px;font-weight:700;">Ready when you are!</h2>
      <p style="margin:0 0 20px;color:#444;font-size:15px;line-height:1.6;">
        We\'ve saved your gang sheet design so you can pick up right where you left off &mdash; on any device, any browser.
      </p>

      <!-- Resume button -->
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0;">
        <tr>
          <td align="center">
            <a href="' . esc_url($resume_url) . '"
               style="display:inline-block;background:#e535ab;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:16px 40px;border-radius:8px;letter-spacing:0.3px;">
              Continue Building My Sheet &rarr;
            </a>
          </td>
        </tr>
      </table>

      <p style="margin:0 0 12px;color:#666;font-size:13px;line-height:1.6;">
        Or copy and paste this link into your browser:
      </p>
      <p style="margin:0 0 28px;background:#f5f5fa;border-radius:6px;padding:12px 16px;font-family:monospace;font-size:12px;color:#27267e;word-break:break-all;">
        ' . esc_html($resume_url) . '
      </p>

      <!-- Expiry notice -->
      <table width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:#fff8e1;border-left:4px solid #f59e0b;border-radius:4px;margin-bottom:28px;">
        <tr>
          <td style="padding:14px 18px;">
            <p style="margin:0;color:#92400e;font-size:13px;">
              <strong>&#x23F0; Expires ' . esc_html($expires_fmt) . '</strong> &mdash;
              This link will work for 30 days. After that, you\'ll need to start a new sheet.
            </p>
          </td>
        </tr>
      </table>

      <p style="margin:0;color:#888;font-size:13px;line-height:1.6;">
        Questions? Reply to this email or contact us at
        <a href="mailto:orders@boomerts.com" style="color:#27267e;">orders@boomerts.com</a>
      </p>

    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#1a1060;border-radius:0 0 10px 10px;padding:24px 40px;text-align:center;">
      <p style="margin:0 0 6px;color:rgba(255,255,255,0.9);font-size:14px;font-weight:700;">Boomer T\'s Ink &amp; Thread</p>
      <p style="margin:0 0 6px;color:rgba(255,255,255,0.6);font-size:12px;">Oswego, IL &nbsp;&middot;&nbsp; boomerts.com &nbsp;&middot;&nbsp; (630) 851-0000</p>
      <p style="margin:12px 0 0;color:rgba(255,255,255,0.4);font-size:11px;">
        You received this because you saved a gang sheet on our website.<br>
        This is an automated message &mdash; no need to reply.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
    ];

    return wp_mail($to, $subject, $html, $headers);
}


function btdtf_save_snippet_is_active() {
    return function_exists('btgsb_ajax_save_sheet_email')
        || function_exists('btgsb_send_resume_email');
}

add_action('plugins_loaded', function () {
    if (btdtf_save_snippet_is_active()) return;   // snippet still on — stay dormant
    btdtf_save_register_hooks();
}, 999);

function btdtf_save_register_hooks() {
    add_action('init', function () {
        if (get_option('btgsb_saves_db_v1')) return;
        global $wpdb;
        $table   = $wpdb->prefix . 'btgsb_saves';
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token varchar(64) NOT NULL,
            email varchar(255) NOT NULL,
            designs longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY expires_at (expires_at)
        ) $charset;");
        update_option('btgsb_saves_db_v1', '1');
    });

    add_action('init', function () {
        if (!wp_next_scheduled('btgsb_cleanup_saves'))
            wp_schedule_event(time(), 'daily', 'btgsb_cleanup_saves');
    });

    add_action('btgsb_cleanup_saves', function () {
        global $wpdb;
        $table = $wpdb->prefix . 'btgsb_saves';
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at < %s", current_time('mysql')));
    });

    add_action('wp_ajax_btgsb_save_sheet_email',        'btdtf_ajax_save_sheet_email');

    add_action('wp_ajax_nopriv_btgsb_save_sheet_email', 'btdtf_ajax_save_sheet_email');

    add_action('wp_ajax_btgsb_load_save',        'btdtf_ajax_load_save');

    add_action('wp_ajax_nopriv_btgsb_load_save', 'btdtf_ajax_load_save');
}
