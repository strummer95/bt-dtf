<?php
/**
 * PresStora Studio — Backend
 * Admin settings, AJAX handlers, WooCommerce hooks
 */

defined('ABSPATH') || exit;
defined('BTDTF_OPT')  || define('BTDTF_OPT',  'btgsb_settings');
defined('BTDTF_PROD') || define('BTDTF_PROD', 'btgsb_product_id');

function btdtf_defaults() {
    return [
        'canvas_width'  => 22,
        'max_height'    => 300,
        'start_height'  => 12,
        'moq_sq_in'     => 264,
        'margin'        => 0.15,
        'padding'       => 0.20,
        'dpi_good'      => 300,
        'dpi_fine'      => 200,
        'export_dpi'    => 300,
        'button_text'   => 'Add to Cart',
        'button_color'  => '#27267e',
        'pricing_tiers' => [
            ['max'=>264,  'rate'=>0.056],
            ['max'=>1320, 'rate'=>0.037],
            ['max'=>2640, 'rate'=>0.032],
            ['max'=>3960, 'rate'=>0.027],
            ['max'=>5280, 'rate'=>0.023],
            ['max'=>9999, 'rate'=>0.020],
        ],
    ];
}


function btdtf_ensure_product() {
    if (!class_exists('WC_Product_Simple')) return;

    $pid = get_option(BTDTF_PROD);

    if ($pid && get_post($pid)) {
        $existing = wc_get_product($pid);
        if ($existing) {
            $changed = false;
            if ($existing->is_virtual()) {
                $existing->set_virtual(false);
                $changed = true;
            }
            // One-time rename: strip the old "PresStora Studio" branding so it
            // doesn't show in customer carts / order emails / receipts.
            $name = $existing->get_name();
            if (stripos($name, 'PresStora') !== false || stripos($name, 'Pressly') !== false) {
                $existing->set_name('DTF Gang Sheet');
                $changed = true;
            }
            if ($changed) $existing->save();
        }
        return;
    }

    $skus_to_check = ['DTF-TRANSFERS', 'DTF-TRASNFERS', 'dtf-transfers'];
    foreach ($skus_to_check as $sku) {
        $found = wc_get_product_id_by_sku($sku);
        if ($found) {
            update_option(BTDTF_PROD, $found);
            return;
        }
    }

    $p = new WC_Product_Simple();
    $p->set_name('DTF Gang Sheet');
    $p->set_status('publish');
    $p->set_catalog_visibility('hidden');
    $p->set_price(0);
    $p->set_regular_price(0);
    $p->set_virtual(false);
    $p->set_sold_individually(false);
    update_option(BTDTF_PROD, $p->save());
}


function btdtf_settings_page() {
    if (isset($_POST['btgsb_save']) && check_admin_referer('btgsb_settings')) {
        $tiers = [];
        $maxes = (array)($_POST['tier_max']  ?? []);
        $rates = (array)($_POST['tier_rate'] ?? []);
        foreach ($maxes as $i => $max) {
            $max = trim($max); $rate = trim($rates[$i] ?? '');
            if ($max !== '' && $rate !== '') $tiers[] = ['max'=>floatval($max),'rate'=>floatval($rate)];
        }
        usort($tiers, fn($a,$b) => $a['max']<=>$b['max']);
        if ($tiers) $tiers[count($tiers)-1]['max'] = 9999;
        $saved = [
            'canvas_width'  => max(1,  floatval($_POST['canvas_width']  ?? 22)),
            'max_height'    => max(1,  floatval($_POST['max_height']     ?? 300)),
            'start_height'  => max(1,  floatval($_POST['start_height']  ?? 12)),
            'moq_sq_in'     => max(0,  floatval($_POST['moq_sq_in']     ?? 264)),
            'margin'        => max(0,  floatval($_POST['margin']        ?? 0.15)),
            'padding'       => max(0,  floatval($_POST['padding']       ?? 0.20)),
            'dpi_good'      => max(1,  intval($_POST['dpi_good']        ?? 300)),
            'dpi_fine'      => max(1,  intval($_POST['dpi_fine']        ?? 200)),
            'export_dpi'    => max(72, intval($_POST['export_dpi']      ?? 300)),
            'button_text'   => sanitize_text_field($_POST['button_text']  ?? 'Add to Cart'),
            'button_color'  => sanitize_hex_color($_POST['button_color']  ?? '#27267e'),
            'pricing_tiers' => $tiers,
        ];
        update_option(BTDTF_OPT, $saved);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $s = get_option(BTDTF_OPT, btdtf_defaults());
    $tiers = $s['pricing_tiers'] ?? [];
    $pid   = get_option(BTDTF_PROD);
    ?>
    <div class="wrap" style="max-width:800px">
    <h1><span style="color:#27267e;font-weight:900">BT Transfers</span> — Sheet Builder Settings</h1>
    <?php if ($pid):
        $linked = wc_get_product($pid);
        $linked_name = $linked ? $linked->get_name() : 'Unknown';
    ?>
    <div class="notice notice-info inline" style="padding:8px 14px;margin-bottom:16px">
        <p>Checkout product: <strong><?php echo esc_html($linked_name); ?></strong> (ID: <?php echo $pid; ?>) &nbsp;
        <a href="<?php echo get_edit_post_link($pid); ?>" target="_blank">Edit in WooCommerce →</a></p>
        <p style="margin:4px 0 0;font-size:12px;color:#666">To use a different product, clear this option in the database or change the SKU match in Snippet 1.</p>
    </div>
    <?php endif; ?>

    <form method="post"><?php wp_nonce_field('btgsb_settings'); ?>
    <h2 style="border-bottom:1px solid #ddd;padding-bottom:6px">Sheet Settings</h2>
    <table class="form-table">
        <tr><th>Sheet Width (in)</th><td><input name="canvas_width" type="number" step="0.5" min="1" max="60" value="<?php echo esc_attr($s['canvas_width']); ?>" class="small-text"> in</td></tr>
        <tr><th>Minimum Sheet Height (in)</th><td><input name="start_height" type="number" step="1" min="1" value="<?php echo esc_attr($s['start_height']); ?>" class="small-text"> in<p class="description">Min billed height — e.g. 12" × 22" = 264 sq in MOQ</p></td></tr>
        <tr><th>Maximum Sheet Height (in)</th><td><input name="max_height" type="number" step="1" min="1" value="<?php echo esc_attr($s['max_height']); ?>" class="small-text"> in</td></tr>
        <tr><th>MOQ (sq in)</th><td><input name="moq_sq_in" type="number" step="1" min="0" value="<?php echo esc_attr($s['moq_sq_in']); ?>" class="small-text"> sq in</td></tr>
        <tr><th>Margin Between Designs</th><td><input name="margin" type="number" step="0.01" min="0" value="<?php echo esc_attr($s['margin']); ?>" class="small-text"> in</td></tr>
        <tr><th>Sheet Edge Padding</th><td><input name="padding" type="number" step="0.01" min="0" value="<?php echo esc_attr($s['padding']); ?>" class="small-text"> in</td></tr>
    </table>
    <h2 style="border-bottom:1px solid #ddd;padding-bottom:6px">Output</h2>
    <table class="form-table">
        <tr><th>Export DPI</th><td><input name="export_dpi" type="number" step="1" min="72" value="<?php echo esc_attr($s['export_dpi']); ?>" class="small-text"> DPI<p class="description">300 recommended for DTF — matches the "Good" quality threshold for incoming artwork. Ensure server upload_max_filesize is at least 32MB at this resolution.</p></td></tr>
    </table>
    <h2 style="border-bottom:1px solid #ddd;padding-bottom:6px">DPI Warnings</h2>
    <table class="form-table">
        <tr><th><span style="color:#27ae60">●</span> Good min DPI</th><td><input name="dpi_good" type="number" min="1" value="<?php echo esc_attr($s['dpi_good']); ?>" class="small-text"></td></tr>
        <tr><th><span style="color:#e5a000">●</span> Fine min DPI</th><td><input name="dpi_fine" type="number" min="1" value="<?php echo esc_attr($s['dpi_fine']); ?>" class="small-text"><p class="description">Below Fine = red border warning on canvas.</p></td></tr>
    </table>
    <h2 style="border-bottom:1px solid #ddd;padding-bottom:6px">Storefront Button</h2>
    <table class="form-table">
        <tr><th>Button Label</th><td><input name="button_text" type="text" value="<?php echo esc_attr($s['button_text']); ?>" class="regular-text"></td></tr>
        <tr><th>Button Color</th><td><input name="button_color" type="color" value="<?php echo esc_attr($s['button_color']); ?>"></td></tr>
    </table>
    <h2 style="border-bottom:1px solid #ddd;padding-bottom:6px">Pricing Tiers (per sq inch)</h2>
    <p class="description">Tier selected by total sheet area — all sq inches billed at that tier's flat rate.</p>
    <table class="widefat striped" id="btgsb-tiers" style="max-width:560px;margin-bottom:10px">
        <thead><tr><th>Min sq in</th><th>Max sq in</th><th>Rate / sq in</th><th></th></tr></thead>
        <tbody>
        <?php $prev=0; foreach($tiers as $i=>$tier): $last=($i===count($tiers)-1); ?>
        <tr>
            <td style="padding:8px 10px;font-weight:600;color:#555"><?php echo number_format($prev); ?></td>
            <td><input name="tier_max[]" type="number" step="1" min="1" value="<?php echo esc_attr($last?'':$tier['max']); ?>" placeholder="(last)" style="width:100px"></td>
            <td>$ <input name="tier_rate[]" type="number" step="0.001" min="0" value="<?php echo esc_attr($tier['rate']); ?>" style="width:80px"> / sq in</td>
            <td><button type="button" class="button btgsb-del-tier">✕</button></td>
        </tr>
        <?php $prev=$last?$prev:$tier['max']; endforeach; ?>
        </tbody>
    </table>
    <button type="button" class="button" id="btgsb-add-tier">＋ Add Tier</button>
    <p class="submit"><input type="submit" name="btgsb_save" class="button-primary" value="Save Settings"></p>
    </form></div>
    <script>
    jQuery(function($){
        $('#btgsb-add-tier').on('click',function(){
            $('#btgsb-tiers tbody').append('<tr><td style="padding:8px 10px;font-weight:600;color:#555">—</td><td><input name="tier_max[]" type="number" step="1" min="1" placeholder="(last)" style="width:100px"></td><td>$ <input name="tier_rate[]" type="number" step="0.001" min="0" style="width:80px"> / sq in</td><td><button type="button" class="button btgsb-del-tier">✕</button></td></tr>');
        });
        $(document).on('click','.btgsb-del-tier',function(){
            if($('#btgsb-tiers tbody tr').length>1) $(this).closest('tr').remove();
            else alert('You need at least one pricing tier.');
        });
    });
    </script>
    <?php
}

/* ── Server-side pHYs injection (belt-and-suspenders) ──────────────── */
// The Frontend already stamps the PNG with pHYs before upload, but if any
// image-optimization plugin (Smush/EWWW/Imagify/ShortPixel/etc) or even
// wp_handle_upload re-encodes the file, the chunk can disappear. After save,
// we verify and inject if missing so production files always carry the right
// physical-size metadata.
function btdtf_ensure_png_dpi($file_path, $dpi) {
    $data = @file_get_contents($file_path);
    if (!$data || strlen($data) < 33) return false;
    if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;

    // Walk chunks until we hit IDAT (start of image data) or IEND.
    // pHYs MUST come before IDAT to be valid per the PNG spec.
    $offset = 8;
    $physOffset = -1;
    $idatOffset = -1;
    while ($offset + 12 <= strlen($data)) {
        $length = unpack('N', substr($data, $offset, 4))[1];
        $type   = substr($data, $offset + 4, 4);
        if ($type === 'pHYs') { $physOffset = $offset; }
        if ($type === 'IDAT') { $idatOffset = $offset; break; }
        if ($type === 'IEND') break;
        $offset += 12 + $length; // 4 length + 4 type + N data + 4 crc
    }

    if ($physOffset !== -1) return true; // already stamped — no change needed
    if ($idatOffset === -1) return false; // malformed file

    // Build pHYs chunk: 4 length + 4 type + 9 data + 4 crc
    $ppm = (int) round($dpi / 0.0254);
    $chunkData = pack('NN', $ppm, $ppm) . chr(1); // X PPM, Y PPM, unit=meter
    $crc = crc32('pHYs' . $chunkData) & 0xFFFFFFFF;
    $chunk = pack('N', 9) . 'pHYs' . $chunkData . pack('N', $crc);

    // Insert just before the first IDAT
    $newData = substr($data, 0, $idatOffset) . $chunk . substr($data, $idatOffset);
    return @file_put_contents($file_path, $newData) !== false;
}

/* ── Shared renderer for the production-files block ─────────────────── */
// Builds the HTML shown on both the admin order screen and in the admin
// "new order" email: a ZIP download link (filename stamped with the order
// number) plus the per-design list with x{qty} copy counts. When the
// combined full-sheet PNG/PDF was not generated (sheet too large to render
// as one image), it shows an explanatory note instead of an error.
function btdtf_production_files_html($order, $context) {
    if (!is_object($order) || !method_exists($order, 'get_items')) return '';

    $order_no = $order->get_order_number();
    $dl_base  = $order_no !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $order_no) : 'order';

    $zip_url       = '';
    $manifest      = [];
    $combined_made = true;

    foreach ($order->get_items() as $item) {
        if (!is_object($item) || !method_exists($item, 'get_meta')) continue;
        $z = $item->get_meta('_btgsb_zip_url');
        if ($z && !$zip_url) $zip_url = $z;
        $m = $item->get_meta('_btgsb_manifest');
        if ($m) {
            $decoded = json_decode($m, true);
            if (is_array($decoded)) $manifest = $decoded;
        }
        $c = $item->get_meta('_btgsb_combined');
        if ($c === '0' || $c === 0) $combined_made = false;
    }

    if (!$zip_url && empty($manifest)) return '';

    $accent = '#27267e';
    $out = '';

    if ($context === 'email') {
        $out .= '<h2 style="color:'.$accent.';margin:24px 0 12px;font-size:18px;border-bottom:2px solid '.$accent.';padding-bottom:6px">Production Files</h2>';
        $out .= '<div style="background:#f0eff8;border:1px solid #d0cff0;border-radius:6px;padding:16px;margin-bottom:12px;font-family:Arial,sans-serif">';
    } else {
        $out .= '<div class="btgsb-admin-files" style="margin-top:10px;padding:10px;background:#f0eff8;border:1px solid #d0cff0;border-radius:5px;font-size:13px">';
        $out .= '<div style="margin-bottom:6px"><strong style="color:'.$accent.'">Production Files</strong></div>';
    }

    if (!$combined_made) {
        $out .= '<p style="margin:0 0 10px;padding:8px 10px;background:#fff7e6;border:1px solid #f0d18a;border-radius:5px;color:#8a6100;font-size:13px;line-height:1.5">'
              . '<strong>Note:</strong> the combined full-sheet PNG/PDF was not generated for this order '
              . '(the sheet was too large to render as a single image). Use the individual design files '
              . 'in the ZIP below &mdash; each filename includes the number of copies to print.</p>';
    }

    if ($zip_url) {
        $zip_name = $dl_base . '-production-files.zip';
        if ($context === 'email') {
            $out .= '<a href="'.esc_url($zip_url).'" download="'.esc_attr($zip_name).'" '
                  . 'style="display:inline-block;margin:4px 0;background:'.$accent.';color:#fff;'
                  . 'padding:10px 18px;text-decoration:none;border-radius:5px;font-weight:bold">'
                  . 'Download Individual Files (ZIP)</a>';
        } else {
            $out .= '<a href="'.esc_url($zip_url).'" download="'.esc_attr($zip_name).'" '
                  . 'style="font-weight:bold;text-decoration:none">Download Individual Files (ZIP)</a>';
        }
    }

    if (!empty($manifest)) {
        $out .= '<div style="margin-top:10px">';
        $out .= '<div style="font-weight:bold;color:'.$accent.';margin-bottom:4px">Designs in this order</div>';
        $out .= '<table style="border-collapse:collapse;font-size:13px;width:100%;max-width:520px">';
        foreach ($manifest as $row) {
            $fname  = isset($row['file'])     ? $row['file']     : '';
            $dname  = isset($row['design'])   ? $row['design']   : '';
            $orig   = isset($row['original']) ? $row['original'] : '';
            $w      = isset($row['w'])        ? $row['w']        : '';
            $h      = isset($row['h'])        ? $row['h']        : '';
            $qty    = isset($row['qty'])      ? intval($row['qty']) : 1;
            $label  = $dname;
            if ($orig && $orig !== $dname) $label .= ' ("' . $orig . '")';
            $out .= '<tr style="border-bottom:1px solid #e2e0f0">';
            $out .= '<td style="padding:5px 8px;color:#555">' . esc_html($fname) . '</td>';
            $out .= '<td style="padding:5px 8px">' . esc_html($label) . '</td>';
            $out .= '<td style="padding:5px 8px;color:#777;white-space:nowrap">' . esc_html($w) . '" x ' . esc_html($h) . '"</td>';
            $out .= '<td style="padding:5px 8px;font-weight:bold;color:'.$accent.';white-space:nowrap">x' . $qty . '</td>';
            $out .= '</tr>';
        }
        $out .= '</table></div>';
    }

    $out .= '</div>';
    return $out;
}

function btdtf_ajax_save_sheet() {
    check_ajax_referer('btgsb_nonce', 'nonce');

    // -- New path: production ZIP (+ optional combined PNG) ----------
    // The ZIP is the file production needs and is always present. The
    // combined PNG is optional - it is absent when the sheet was too
    // large to render as a single image.
    if (!empty($_FILES['zip_file']['tmp_name'])) {

        $zip_upload = wp_handle_upload(
            $_FILES['zip_file'],
            ['test_form' => false, 'mimes' => ['zip' => 'application/zip']]
        );
        if (isset($zip_upload['error'])) wp_send_json_error($zip_upload['error']);

        $result = ['zip_url' => $zip_upload['url'], 'sheet_url' => ''];

        // Optional combined PNG - same alpha checks + DPI stamp as the
        // legacy path. If anything is wrong with it we simply drop it and
        // keep the ZIP, so the order still proceeds.
        if (!empty($_FILES['sheet_file']['tmp_name'])) {
            $tmp_data = @file_get_contents($_FILES['sheet_file']['tmp_name']);
            if ($tmp_data && substr($tmp_data, 0, 8) === "\x89PNG\r\n\x1a\n") {
                $upload_color_type = ord($tmp_data[25]);
                if ($upload_color_type === 4 || $upload_color_type === 6) {
                    $png_upload = wp_handle_upload(
                        $_FILES['sheet_file'],
                        ['test_form' => false, 'mimes' => ['png' => 'image/png']]
                    );
                    if (!isset($png_upload['error']) && !empty($png_upload['file']) && file_exists($png_upload['file'])) {
                        $saved_data = @file_get_contents($png_upload['file']);
                        $saved_color_type = ($saved_data && substr($saved_data, 0, 8) === "\x89PNG\r\n\x1a\n")
                            ? ord($saved_data[25]) : 0;
                        if ($saved_color_type === 4 || $saved_color_type === 6) {
                            $s = get_option(BTDTF_OPT, btdtf_defaults());
                            btdtf_ensure_png_dpi($png_upload['file'], (int)$s['export_dpi']);
                            $result['sheet_url'] = $png_upload['url'];
                        } else {
                            // Alpha stripped by an optimizer - drop the PNG,
                            // keep the ZIP.
                            @unlink($png_upload['file']);
                        }
                    }
                }
            }
        }

        wp_send_json_success($result);
    }

    // -- Legacy path: single PNG file upload -------------------------
    if (!empty($_FILES['sheet_file']['tmp_name'])) {

        // Pre-upload: alpha channel sanity check (PNG color type at byte 25)
        $tmp_data = @file_get_contents($_FILES['sheet_file']['tmp_name']);
        if ($tmp_data && substr($tmp_data, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $upload_color_type = ord($tmp_data[25]);
            if ($upload_color_type !== 4 && $upload_color_type !== 6) {
                wp_send_json_error('Browser sent PNG with no alpha channel (color type ' . $upload_color_type . '). This usually means the browser hit a canvas size limit and silently dropped transparency. Try fewer designs, a smaller sheet, or a different browser.');
            }
        }

        $upload = wp_handle_upload($_FILES['sheet_file'], ['test_form'=>false,'mimes'=>['png'=>'image/png','pdf'=>'application/pdf']]);
        if (isset($upload['error'])) wp_send_json_error($upload['error']);

        // Post-save: verify alpha survived
        if (!empty($upload['file']) && file_exists($upload['file'])) {
            $saved_data = @file_get_contents($upload['file']);
            if ($saved_data && substr($saved_data, 0, 8) === "\x89PNG\r\n\x1a\n") {
                $saved_color_type = ord($saved_data[25]);
                if ($saved_color_type !== 4 && $saved_color_type !== 6) {
                    @unlink($upload['file']);
                    wp_send_json_error('Server stripped alpha from the PNG during upload (color type became ' . $saved_color_type . '). An image optimization plugin is flattening transparency. Check Plugins for: Smush, Imagify, ShortPixel, EWWW, Optimole, TinyPNG, LiteSpeed Cache image optimization, Converter for Media, or WebP Express. Disable the culprit or configure it to skip /wp-content/uploads/gang-sheets/.');
                }
            }

            // Belt-and-suspenders: ensure the DPI metadata is present even if a
            // plugin or wp_handle_upload stripped it during processing.
            $s = get_option(BTDTF_OPT, btdtf_defaults());
            btdtf_ensure_png_dpi($upload['file'], (int)$s['export_dpi']);
        }

        wp_send_json_success(['url'=>$upload['url']]);
    } elseif (!empty($_POST['image_data'])) {
        $raw     = preg_replace('#^data:image/\w+;base64,#', '', $_POST['image_data']);
        $decoded = base64_decode(str_replace(' ', '+', $raw));
        if (!$decoded) wp_send_json_error('Invalid image data.');
        $ud  = wp_upload_dir();
        $sub = '/gang-sheets/' . date('Y/m');
        wp_mkdir_p($ud['basedir'] . $sub);
        $fn = 'gang-sheet-' . time() . '-' . wp_generate_password(8, false) . '.png';
        $full = $ud['basedir'] . $sub . '/' . $fn;
        file_put_contents($full, $decoded);
        $s = get_option(BTDTF_OPT, btdtf_defaults());
        btdtf_ensure_png_dpi($full, (int)$s['export_dpi']);
        wp_send_json_success(['url' => $ud['baseurl'] . $sub . '/' . $fn]);
    } else {
        wp_send_json_error('No file received.');
    }
}

function btdtf_ajax_add_to_cart() {
    check_ajax_referer('btgsb_nonce', 'nonce');
    if (!function_exists('WC')) wp_send_json_error('WooCommerce not active.');
    $pid = (int)get_option(BTDTF_PROD, 0);
    if (!$pid) wp_send_json_error('Gang sheet product missing — visit PS Studio settings to regenerate.');
    $sq_in     = floatval($_POST['sq_inches']     ?? 0);
    $price     = floatval($_POST['price']          ?? 0);
    $height_in = floatval($_POST['height_inches']  ?? 0);
    $width_in  = floatval($_POST['width_inches']   ?? 22);
    $sheet_url = esc_url_raw($_POST['sheet_url']   ?? '');
    $zip_url   = esc_url_raw($_POST['zip_url']     ?? '');
    $pieces    = intval($_POST['item_count']        ?? 0);
    $combined  = (isset($_POST['combined_rendered']) && $_POST['combined_rendered'] === '1') ? 1 : 0;
    // Validate the manifest is well-formed JSON before storing it.
    $manifest_raw = isset($_POST['manifest']) ? wp_unslash($_POST['manifest']) : '';
    $manifest_decoded = json_decode($manifest_raw, true);
    $manifest_json = is_array($manifest_decoded) ? wp_json_encode($manifest_decoded) : '';
    if ($sq_in <= 0 || $price <= 0) wp_send_json_error('Invalid order data.');
    WC()->cart->empty_cart();
    $key = WC()->cart->add_to_cart($pid, 1, 0, [], [
        'btgsb_price'      => $price,
        'btgsb_sq_inches'  => $sq_in,
        'btgsb_sheet_url'  => $sheet_url,
        'btgsb_zip_url'    => $zip_url,
        'btgsb_combined'   => $combined,
        'btgsb_manifest'   => $manifest_json,
        'btgsb_size'       => round($width_in,2).'" x '.round($height_in,2).'"',
        'btgsb_item_count' => $pieces,
    ]);
    if (!$key) wp_send_json_error('Could not add to cart — please try again.');
    wp_send_json_success(['cart_url' => wc_get_cart_url()]);
}

/* ── Force jquery-blockui + shim — required by WooCommerce cart AJAX ─── */

/* ── Cart button hard-submit fix ────────────────────────────────────── */

/* ── Frontend cart CSS fixes ──────────────────────────────────────── */

/* ── Shipping calculator — postcode only ─────────────────────────── */


/* ── Cart display — what the customer sees in their cart ────────── */
// Customer-safe metadata only: size, area, piece count. NO production URL.

/* ── Order line items — store production URLs as HIDDEN meta ───── */
// Underscore-prefixed keys are private/hidden from the customer-facing order
// summary. Production file URLs go in here so customers never see them.

/* ── Backward-compat: hide legacy public meta from customers ──── */
// Older orders (before this snippet update) stored "Sheet File" and
// "Design Files" as PUBLIC meta. Filter them out for non-admins so customers
// looking at their order history don't see production URLs.

/* ── Admin order page — production files block ──────────────────── */
// Shows the ZIP download link + the per-design list with x{qty} counts.
// Rendered once per order, tied to the first line item.

/* ── Admin order page — render legacy production file links ───── */

/* ── Admin "New Order" email — production files block ──────────── */
// Shows the ZIP download link + per-design list. Customer-facing emails
// (order confirmation, processing, completed, etc.) stay clean.

/* ── Admin "New Order" email — legacy combined PNG/PDF buttons ── */
// Adds PNG + PDF download buttons after the order table for orders that
// have a combined sheet PNG. ONLY in the admin new_order email.

/* ── Hidden admin page — generates PDF in the browser via jsPDF ── */
// The "Download PDF" button in the admin email points here. Page is admin-only
// (auth via WP login + manage_woocommerce capability), loads jsPDF, fetches
// the PNG, wraps it into a one-page 22"-wide PDF, triggers download. No
// server-side PDF library / Imagick / Ghostscript required.

function btdtf_render_pdf_download_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
    $sheet_url = isset($_GET['sheet']) ? esc_url_raw(wp_unslash($_GET['sheet'])) : '';
    $order_no  = isset($_GET['order']) ? preg_replace('/[^A-Za-z0-9_-]/', '', wp_unslash($_GET['order'])) : '';
    if (!$sheet_url) {
        echo '<div class="wrap"><h1>Generate PDF</h1><p>No sheet URL provided.</p></div>';
        return;
    }
    // Verify URL is inside the uploads directory before we hand it to JS
    $upload_dir = wp_upload_dir();
    $is_local = false;
    foreach ([$upload_dir['baseurl'], str_replace('http://', 'https://', $upload_dir['baseurl']), str_replace('https://', 'http://', $upload_dir['baseurl'])] as $base) {
        if (strpos($sheet_url, $base) === 0) { $is_local = true; break; }
    }
    if (!$is_local) {
        echo '<div class="wrap"><h1>Generate PDF</h1><p>Invalid sheet URL.</p></div>';
        return;
    }
    ?>
    <div class="wrap" style="max-width:600px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">
        <h1 style="color:#27267e">Generating PDF&hellip;</h1>
        <p id="btgsb-pdf-status" style="font-size:16px;line-height:1.6">&#x23F3; Loading PDF library&hellip;</p>
        <p style="font-size:13px;color:#666;margin-top:30px;line-height:1.5">The PDF is generated in your browser from the gang sheet PNG. Once the download starts, you can close this tab.</p>
    </div>
    <script>
    (function(){
        var pngUrl = '<?php echo esc_js($sheet_url); ?>';
        var orderNo = '<?php echo esc_js($order_no); ?>';
        var status = document.getElementById('btgsb-pdf-status');
        function setStatus(msg) { if (status) status.innerHTML = msg; }

        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        s.onload = function() {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                setStatus('&#x274C; jsPDF library failed to initialize.');
                return;
            }
            setStatus('&#x1F5BC;&#xFE0F; Loading sheet image&hellip;');
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
                try {
                    setStatus('&#x1F4C4; Building PDF&hellip;');
                    var inW = 22;
                    var inH = (img.naturalHeight / img.naturalWidth) * inW;
                    var pdf = new window.jspdf.jsPDF({
                        orientation: inH > inW ? 'portrait' : 'landscape',
                        unit: 'in',
                        format: [inW, inH],
                        compress: true
                    });
                    pdf.addImage(img, 'PNG', 0, 0, inW, inH, undefined, 'FAST');
                    var name = orderNo ? (orderNo + '.pdf') : (pngUrl.split('/').pop() || 'gang-sheet.png').replace(/\.png(\?.*)?$/i, '.pdf');
                    pdf.save(name);
                    setStatus('&#x2713; PDF downloaded! You can close this tab.');
                } catch (err) {
                    setStatus('&#x274C; Error: ' + err.message);
                }
            };
            img.onerror = function() {
                setStatus('&#x274C; Could not load sheet image. The file may have been moved or deleted.');
            };
            img.src = pngUrl;
        };
        s.onerror = function() {
            setStatus('&#x274C; Could not load PDF library from CDN. Check your internet connection.');
        };
        document.head.appendChild(s);
    })();
    </script>
    <?php
}

/* ── Admin order edit page — client-side PDF generation via jsPDF ───── */


/* ══ AWAITING ITEMS — in-house tracking flag ════════════════════════════
   Marks an order as waiting on blanks/supplies WITHOUT touching its
   WooCommerce status. A flagged order stays Processing / Completed /
   whatever it was, so it can never fall out of the Orders list.

   No email is ever sent — nothing here hooks a status transition.

   Use: an "Awaiting" column on the Orders list. Click the cell to toggle.
   Bulk actions and a filter link at the top are wired up too.
   ═══════════════════════════════════════════════════════════════════════ */

define('BTDTF_AWAIT_META', '_btgsb_awaiting');

function btdtf_await_is($order_id) {
    $o = wc_get_order($order_id);
    return $o ? ($o->get_meta(BTDTF_AWAIT_META) === 'yes') : false;
}

function btdtf_await_set($order_id, $on) {
    $o = wc_get_order($order_id);
    if (!$o) return;
    if ($on) {
        $o->update_meta_data(BTDTF_AWAIT_META, 'yes');
        $o->add_order_note('Flagged: Awaiting Items.');
    } else {
        $o->delete_meta_data(BTDTF_AWAIT_META);
        $o->add_order_note('Cleared: Awaiting Items.');
    }
    $o->save();   // status untouched — no customer email
}

function btdtf_await_badge($order_id) {
    $on  = btdtf_await_is($order_id);
    $url = wp_nonce_url(
        add_query_arg(['btgsb_await_toggle' => $order_id], admin_url('admin.php?page=wc-orders')),
        'btgsb_await_toggle'
    );
    return $on
        ? '<a href="' . esc_url($url) . '" title="Click to clear" style="display:inline-block;background:#b8860b;color:#fff;'
          . 'padding:3px 9px;border-radius:3px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap">AWAITING ITEMS</a>'
        : '<a href="' . esc_url($url) . '" title="Mark as Awaiting Items" style="color:#bbb;text-decoration:none;font-size:18px;line-height:1">&#9711;</a>';
}

/* ── Toggle handler ─────────────────────────────────────────────────── */

/* ── Column: HPOS ───────────────────────────────────────────────────── */

/* ── Column: classic orders screen ──────────────────────────────────── */

/* ── Bulk actions ───────────────────────────────────────────────────── */
function btdtf_await_bulk_actions($actions) {
    $actions['btgsb_await_on']  = 'Mark Awaiting Items';
    $actions['btgsb_await_off'] = 'Clear Awaiting Items';
    return $actions;
}

function btdtf_await_bulk_handle($redirect, $action, $ids) {
    if ($action !== 'btgsb_await_on' && $action !== 'btgsb_await_off') return $redirect;
    $on = ($action === 'btgsb_await_on');
    foreach ((array) $ids as $id) btdtf_await_set(intval($id), $on);
    return add_query_arg('btgsb_await_bulk', count((array) $ids), $redirect);
}


/* ── Filter link at the top of the Orders list ──────────────────────── */
function btdtf_await_count() {
    global $wpdb;
    $hpos = $wpdb->prefix . 'wc_orders_meta';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $hpos)) === $hpos) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $hpos WHERE meta_key=%s AND meta_value='yes'", BTDTF_AWAIT_META));
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value='yes'", BTDTF_AWAIT_META));
}

function btdtf_await_view_link($views) {
    $n = btdtf_await_count();
    if (!$n) return $views;
    $active = !empty($_GET['btgsb_awaiting']);
    $url    = add_query_arg('btgsb_awaiting', 1, admin_url('admin.php?page=wc-orders'));
    $views['btgsb_await'] = '<a href="' . esc_url($url) . '"' . ($active ? ' class="current"' : '') . '>'
        . 'Awaiting Items <span class="count">(' . $n . ')</span></a>';
    return $views;
}

// Apply the filter — HPOS
// Apply the filter — classic

/* ── Branded HTML email shell ─────────────────────────────────────────
   NOTE: the four custom WooCommerce order statuses (Art Approved, Printing,
   Ready for Pickup, Shipped) and their two customer notification emails were
   removed on Jul 31 2026 — unused, and they cluttered the status dropdown.
   The two helpers below are deliberately KEPT: they are declared here and may
   be called by the DTF Studio Save & Resume snippet. Removing them would
   fatal that snippet. ─────────────────────────────────────────────────── */
function btdtf_branded_email_html($title, $eyebrow, $intro_html, $body_html) {
    $logo_url = 'https://boomerts.com/wp-content/uploads/2024/01/BT-Logo-250px.png';
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . esc_html($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f0f0f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f0f5;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

  <tr>
    <td style="background:#1a1060;border-radius:10px 10px 0 0;padding:28px 40px;text-align:center;">
      <img src="' . esc_url($logo_url) . '" alt="Boomer T\'s Ink &amp; Thread" width="160" style="display:block;margin:0 auto 12px;max-width:160px;">
      <p style="margin:0;color:rgba(255,255,255,0.75);font-size:13px;letter-spacing:1px;text-transform:uppercase;">' . esc_html($eyebrow) . '</p>
    </td>
  </tr>

  <tr>
    <td style="background:#ffffff;padding:40px 40px 32px;">
      <h2 style="margin:0 0 16px;color:#1a1060;font-size:22px;font-weight:700;">' . esc_html($title) . '</h2>
      <p style="margin:0 0 8px;color:#444;font-size:15px;line-height:1.6;">' . $intro_html . '</p>
      ' . $body_html . '
      <p style="margin:24px 0 0;color:#888;font-size:13px;line-height:1.6;">
        Questions? Contact us at
        <a href="mailto:orders@boomerts.com" style="color:#27267e;">orders@boomerts.com</a>
        or (630) 851-0000.
      </p>
    </td>
  </tr>

  <tr>
    <td style="background:#1a1060;border-radius:0 0 10px 10px;padding:24px 40px;text-align:center;">
      <p style="margin:0 0 6px;color:rgba(255,255,255,0.9);font-size:14px;font-weight:700;">Boomer T\'s Ink &amp; Thread</p>
      <p style="margin:0 0 6px;color:rgba(255,255,255,0.6);font-size:12px;">1505 Mitchell Drive, Oswego, IL 60543 &nbsp;&middot;&nbsp; (630) 851-0000</p>
      <p style="margin:12px 0 0;color:rgba(255,255,255,0.4);font-size:11px;">
        You received this because you placed an order with us.<br>
        This is an automated message.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

/* ── Email sender ───────────────────────────────────────────────────── */
function btdtf_send_customer_email($to, $subject, $html) {
    $site_name  = get_bloginfo('name');
    $from_name  = get_option('woocommerce_email_from_name', $site_name);
    $from_email = 'orders@boomerts.com';
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
    ];
    return wp_mail($to, $subject, $html, $headers);
}


/* ══ BOOT ═══════════════════════════════════════════════════════════════
   Nothing registers at file load. Everything waits for plugins_loaded at
   priority 999, by which point WPCode has already evaluated its snippets —
   so the function_exists() test below is reliable rather than a guess.

   If the old DTF Studio snippet is still active, the plugin stays fully
   dormant: no menu, no AJAX handlers, no order columns, no duplicate line
   item meta. The site keeps running on the snippet exactly as before.

   This is a tidiness guard, NOT a crash guard. Every function here is
   prefixed btdtf_ and every constant BTDTF_, sharing no name at all with
   the snippet's btgsb_* set, so a fatal redeclare is impossible either way
   regardless of activation order.

   Deliberately NOT renamed: option names, order meta keys, AJAX actions,
   the nonce, admin page slugs and CSS classes all stay btgsb_*, so existing
   orders, saved settings and the DTF Studio Frontend snippet keep working
   untouched.
   ═══════════════════════════════════════════════════════════════════════ */
function btdtf_snippet_is_active() {
    return function_exists('btgsb_defaults')
        || function_exists('btgsb_ajax_save_sheet')
        || function_exists('btgsb_settings_page');
}

add_action('plugins_loaded', 'btdtf_boot', 999);
function btdtf_boot() {
    if (btdtf_snippet_is_active()) {
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) return;
            $sc = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$sc || $sc->id !== 'plugins') return;   // Plugins screen only
            echo '<div class="notice notice-warning"><p><strong>BT Transfers is dormant.</strong> '
               . 'The old <em>DTF Studio &mdash; Backend</em> WPCode snippet is still active, so the plugin '
               . 'stood down rather than run everything twice. Nothing is broken &mdash; the snippet is doing '
               . 'the work. Deactivate it in WPCode whenever you are ready to switch over.</p></div>';
        });
        return;
    }
    btdtf_register_hooks();
}

function btdtf_register_hooks() {
    add_action('admin_init', function () {
        if (!get_option(BTDTF_OPT)) update_option(BTDTF_OPT, btdtf_defaults());
        btdtf_ensure_product();
    });

    add_action('admin_notices', function () {
        if (!class_exists('WooCommerce'))
            echo '<div class="notice notice-error"><p><strong>BT Transfers</strong> requires WooCommerce to be active.</p></div>';
    });

    add_action('admin_menu', function () {
        add_menu_page('BT Transfers', 'BT Transfers', 'manage_options', 'btgsb-settings', 'btdtf_settings_page', 'dashicons-layout', '58.4');
    });

    add_action('wp_ajax_btgsb_save_sheet',        'btdtf_ajax_save_sheet');

    add_action('wp_ajax_nopriv_btgsb_save_sheet', 'btdtf_ajax_save_sheet');

    add_action('wp_ajax_btgsb_add_to_cart',        'btdtf_ajax_add_to_cart');

    add_action('wp_ajax_nopriv_btgsb_add_to_cart', 'btdtf_ajax_add_to_cart');

    add_action('wp_enqueue_scripts', function () {
        if (!is_cart() && !is_checkout()) return;
        wp_enqueue_script('jquery-blockui');
        $shim = '
            if (window.jQuery && !jQuery.fn.block) {
                jQuery.fn.block   = function() { return this; };
                jQuery.fn.unblock = function() { return this; };
                jQuery.blockUI    = function() {};
                jQuery.unblockUI  = function() {};
            }
        ';
        wp_add_inline_script('wc-cart',     $shim, 'before');
        wp_add_inline_script('wc-checkout', $shim, 'before');
    }, 20);

    add_action('wp_footer', function () {
        if (!is_cart()) return;
        ?>
        <script>
        (function trimShippingAddress() {
            function doTrim() {
                var dest = document.querySelector('.woocommerce-shipping-destination strong');
                if (!dest) return;
                var full = dest.textContent.trim();
                var zip = full.match(/\b(\d{5}(?:-\d{4})?)\b/);
                var state = full.match(/,\s*([A-Z]{2})\s+\d{5}/);
                if (zip) {
                    dest.textContent = (state ? state[1] + ' ' : '') + zip[1];
                }
            }
            doTrim();
            document.body && document.body.addEventListener('updated_shipping_method', doTrim);
            document.body && document.body.addEventListener('updated_cart_totals', doTrim);
            var attempts = 0;
            var poll = setInterval(function() {
                doTrim(); if (++attempts > 10) clearInterval(poll);
            }, 600);
        })();
    
        document.addEventListener('click', function(e) {
            var updateBtn = e.target.closest('[name="update_cart"]');
            if (updateBtn) {
                e.stopImmediatePropagation();
                updateBtn.disabled = false;
                updateBtn.form.submit();
                return;
            }
            var couponBtn = e.target.closest('.coupon [type="submit"], .coupon .button');
            if (couponBtn) {
                e.stopImmediatePropagation();
                couponBtn.form.submit();
                return;
            }
        }, true);
    
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('qty') || e.target.name && e.target.name.includes('[qty]')) {
                var btn = document.querySelector('[name="update_cart"]');
                if (btn) { btn.disabled = false; btn.removeAttribute('disabled'); }
            }
        }, true);
        </script>
        <?php
    });

    add_action('wp_head', function () {
        if (!is_cart() && !is_checkout()) return;
        echo '<style>
        .woocommerce-shipping-calculator { margin-top:8px; }
        .woocommerce-shipping-calculator .shipping-calculator-form { display:flex; align-items:center; gap:6px; flex-wrap:nowrap; }
        .woocommerce-shipping-calculator .shipping-calculator-form p { margin:0; flex:1; }
        .woocommerce-shipping-calculator .shipping-calculator-form p:last-child { flex:0 0 auto; }
        .woocommerce-shipping-calculator .shipping-calculator-form input { height:32px; padding:4px 8px; font-size:13px; border:1px solid #ccc; border-radius:4px; width:100%; box-sizing:border-box; }
        .woocommerce-shipping-calculator .shipping-calculator-form .button { height:32px; padding:0 12px; font-size:13px; white-space:nowrap; }
        .woocommerce-cart-form .quantity input.qty,
        .woocommerce-cart-form td.product-quantity input,
        .woocommerce td.product-quantity .qty {
            min-width:64px!important;width:64px!important;height:38px!important;
            font-size:16px!important;color:#333!important;text-align:center!important;
            padding:4px 8px!important;box-sizing:border-box!important;
            opacity:1!important;visibility:visible!important;display:inline-block!important;
        }
        .woocommerce-cart-form .actions .coupon .button,
        .woocommerce-cart-form .actions button[name="update_cart"],
        .woocommerce .cart .button {
            opacity:1!important;cursor:pointer!important;pointer-events:auto!important;
            transition:none!important;border-radius:4px!important;
        }
        .woocommerce-cart-form .actions .coupon .button:hover,
        .woocommerce-cart-form .actions button[name="update_cart"]:hover {
            opacity:.85!important;
        }
        .woocommerce-shipping-methods,
        ul#shipping_method,
        .woocommerce-shipping-methods li,
        ul#shipping_method li {
            display:block!important;
            visibility:visible!important;
            opacity:1!important;
            list-style:none!important;
            margin:4px 0!important;
            padding:0!important;
        }
        .woocommerce-checkout .shipping_method,
        .wc-block-components-radio-control__input[type=radio],
        #shipping_method .shipping_method {
            display:inline-block!important;
            visibility:visible!important;
            opacity:1!important;
            pointer-events:auto!important;
            position:static!important;
            width:16px!important;
            height:16px!important;
            margin:0 6px 0 0!important;
            cursor:pointer!important;
            clip:auto!important;
            clip-path:none!important;
            overflow:visible!important;
            appearance:auto!important;
            -webkit-appearance:radio!important;
        }
        #shipping_method li { list-style:none!important; margin:6px 0!important; cursor:pointer!important; }
        #shipping_method label { cursor:pointer!important; }
        </style>';
    });

    add_filter('woocommerce_shipping_calculator_enable_city',     '__return_false');

    add_filter('woocommerce_shipping_calculator_enable_state',    '__return_false');

    add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_true');

    add_filter('woocommerce_shipping_calculator_enable_country',  '__return_false');

    add_action('woocommerce_before_calculate_totals', function ($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ($cart->get_cart() as $item)
            if (!empty($item['btgsb_price'])) $item['data']->set_price(floatval($item['btgsb_price']));
    });

    add_filter('woocommerce_get_item_data', function ($data, $item) {
        if (!empty($item['btgsb_size']))       $data[] = ['name'=>'Sheet Size',   'value'=>esc_html($item['btgsb_size'])];
        if (!empty($item['btgsb_sq_inches']))  $data[] = ['name'=>'Total Area',   'value'=>round($item['btgsb_sq_inches'],1).' sq in'];
        if (!empty($item['btgsb_item_count'])) $data[] = ['name'=>'Total Pieces', 'value'=>intval($item['btgsb_item_count'])];
        return $data;
    }, 10, 2);

    add_action('woocommerce_checkout_create_order_line_item', function ($item, $ck, $values) {
        // Public — customer can see
        if (!empty($values['btgsb_size']))       $item->add_meta_data('Sheet Size',   $values['btgsb_size']);
        if (!empty($values['btgsb_sq_inches']))  $item->add_meta_data('Total Area',   round($values['btgsb_sq_inches'],1).' sq in');
        if (!empty($values['btgsb_item_count'])) $item->add_meta_data('Total Pieces', intval($values['btgsb_item_count']));
        // Hidden — admin only (rendered below via woocommerce_after_order_itemmeta)
        if (!empty($values['btgsb_sheet_url']))    $item->add_meta_data('_btgsb_sheet_url',    $values['btgsb_sheet_url']);
        if (!empty($values['btgsb_design_files'])) $item->add_meta_data('_btgsb_design_files', $values['btgsb_design_files']);
        if (!empty($values['btgsb_zip_url']))      $item->add_meta_data('_btgsb_zip_url',      $values['btgsb_zip_url']);
        if (!empty($values['btgsb_manifest']))     $item->add_meta_data('_btgsb_manifest',     $values['btgsb_manifest']);
        // combined flag: store '0' explicitly so we can tell "not rendered"
        // apart from "old order with no flag at all".
        $item->add_meta_data('_btgsb_combined', isset($values['btgsb_combined']) ? intval($values['btgsb_combined']) : 1);
    }, 10, 3);

    add_filter('woocommerce_order_item_get_formatted_meta_data', function ($meta_array, $item) {
        if (current_user_can('manage_woocommerce')) return $meta_array;
        foreach ($meta_array as $id => $meta) {
            if (in_array($meta->key, ['Sheet File', 'Design Files'], true)) {
                unset($meta_array[$id]);
            }
        }
        return $meta_array;
    }, 10, 2);

    add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
        if (!is_admin() || !current_user_can('manage_woocommerce')) return;
        if (!is_object($item) || !method_exists($item, 'get_order_id')) return;
        static $done = [];
        $oid = $item->get_order_id();
        if (!$oid || isset($done[$oid])) return;
        $done[$oid] = true;
        $order = wc_get_order($oid);
        if (!$order) return;
        echo btdtf_production_files_html($order, 'admin');
    }, 9, 3);

    add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
        if (!is_admin() || !current_user_can('manage_woocommerce')) return;
        if (!is_object($item) || !method_exists($item, 'get_meta')) return;
    
        // Read new hidden keys, fall back to legacy public keys for old orders
        $sheet_url    = $item->get_meta('_btgsb_sheet_url');
        if (!$sheet_url) $sheet_url = $item->get_meta('Sheet File');
        $design_files = $item->get_meta('_btgsb_design_files');
        if (!$design_files) $design_files = $item->get_meta('Design Files');
    
        if (!$sheet_url && !$design_files) return;
    
        // Resolve the WooCommerce order number for download filenames (e.g. 1042.png)
        $order_no = '';
        if (method_exists($item, 'get_order_id')) {
            $oid = $item->get_order_id();
            if ($oid) {
                $ord = wc_get_order($oid);
                if ($ord) $order_no = $ord->get_order_number();
            }
        }
        $dl_base = $order_no !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $order_no) : 'gang-sheet';
    
        echo '<div class="btgsb-admin-files" style="margin-top:10px;padding:10px;background:#f0eff8;border:1px solid #d0cff0;border-radius:5px;font-size:13px">';
        if ($sheet_url) {
            $u = esc_url($sheet_url);
            echo '<div style="margin-bottom:6px"><strong style="color:#27267e">Production Sheet</strong></div>';
            echo '<a href="'.$u.'" target="_blank" download="'.esc_attr($dl_base).'.png" style="font-weight:bold;margin-right:14px;text-decoration:none">&#x1F4E5; Download PNG</a>';
            echo '<button type="button" class="btgsb-pdf-dl" data-png="'.$u.'" data-fname="'.esc_attr($dl_base).'.pdf" style="font-weight:bold;color:#c0392b;background:none;border:none;cursor:pointer;padding:0;font-family:inherit;font-size:13px;text-decoration:underline">&#x1F4C4; Download PDF</button>';
        }
        if ($design_files) {
            echo '<div style="margin-top:8px"><strong style="color:#27267e">Original Design Files</strong><br>';
            foreach (explode("\n", $design_files) as $line) {
                $line = trim($line);
                if (!$line) continue;
                if (strpos($line, '|') !== false) {
                    [$fname, $furl] = explode('|', $line, 2);
                    echo '<a href="'.esc_url(trim($furl)).'" target="_blank" download style="display:block;margin:2px 0;color:#27267e">&#x1F4C5; '.esc_html(trim($fname)).'</a>';
                } else if (filter_var($line, FILTER_VALIDATE_URL)) {
                    echo '<a href="'.esc_url($line).'" target="_blank" download style="display:block;margin:2px 0;color:#27267e">&#x1F4C5; Download</a>';
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }, 10, 3);

    add_action('woocommerce_email_after_order_table', function ($order, $sent_to_admin, $plain_text, $email) {
        if (!$sent_to_admin) return;
        if (!isset($email->id) || $email->id !== 'new_order') return;
        if ($plain_text) return;
        echo btdtf_production_files_html($order, 'email');
    }, 9, 4);

    add_action('woocommerce_email_after_order_table', function ($order, $sent_to_admin, $plain_text, $email) {
        if (!$sent_to_admin) return;
        if (!isset($email->id) || $email->id !== 'new_order') return;
        if ($plain_text) return;
        if (!is_object($order) || !method_exists($order, 'get_items')) return;
    
        $entries = [];
        foreach ($order->get_items() as $item_id => $item) {
            $sheet_url = $item->get_meta('_btgsb_sheet_url');
            if (!$sheet_url) $sheet_url = $item->get_meta('Sheet File'); // legacy
            if ($sheet_url) $entries[] = ['name' => $item->get_name(), 'url' => $sheet_url];
        }
        if (empty($entries)) return;
    
        // Order number for download filenames (e.g. 1042.png)
        $order_no = $order->get_order_number();
        $dl_base  = $order_no !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $order_no) : 'gang-sheet';
    
        echo '<h2 style="color:#27267e;margin:24px 0 12px;font-size:18px;border-bottom:2px solid #27267e;padding-bottom:6px">&#x1F4E6; Combined Sheet</h2>';
        foreach ($entries as $e) {
            $png = esc_url($e['url']);
            $pdf = esc_url(add_query_arg(['page' => 'btgsb-pdf-download', 'sheet' => $e['url'], 'order' => $dl_base], admin_url('admin.php')));
            echo '<div style="background:#f0eff8;border:1px solid #d0cff0;border-radius:6px;padding:16px;margin-bottom:12px;font-family:Arial,sans-serif">';
            echo '<p style="margin:0 0 10px;font-weight:bold;color:#333">' . esc_html($e['name']) . '</p>';
            echo '<a href="' . $png . '" download="' . esc_attr($dl_base) . '.png" style="display:inline-block;margin:4px 8px 4px 0;background:#27267e;color:#fff;padding:10px 18px;text-decoration:none;border-radius:5px;font-weight:bold">&#x1F4E5; Download PNG</a>';
            echo '<a href="' . $pdf . '" style="display:inline-block;margin:4px 0;background:#c0392b;color:#fff;padding:10px 18px;text-decoration:none;border-radius:5px;font-weight:bold">&#x1F4C4; Download PDF</a>';
            echo '</div>';
        }
    }, 10, 4);

    add_action('admin_menu', function () {
        add_submenu_page(
            null,                       // parent_slug = null → page exists but doesn't show in any menu
            'Generate Gang Sheet PDF',
            'Generate Gang Sheet PDF',
            'manage_woocommerce',
            'btgsb-pdf-download',
            'btdtf_render_pdf_download_page'
        );
    });

    add_action('admin_footer', function () {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return;
        // Show on classic shop_order edit screen AND HPOS order screen
        $on_order_page = ($screen->post_type === 'shop_order')
                      || ($screen->id === 'woocommerce_page_wc-orders')
                      || (isset($_GET['page']) && $_GET['page'] === 'wc-orders');
        if (!$on_order_page) return;
        ?>
        <script>
        (function(){
            if (window.btgsbPdfDlBound) return; window.btgsbPdfDlBound = true;
            var jsPDFLoad = null;
            function loadJsPDF() {
                if (jsPDFLoad) return jsPDFLoad;
                jsPDFLoad = new Promise(function(resolve, reject){
                    if (window.jspdf && window.jspdf.jsPDF) { resolve(window.jspdf); return; }
                    var s = document.createElement('script');
                    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
                    s.onload  = function(){ window.jspdf ? resolve(window.jspdf) : reject(new Error('jsPDF loaded but namespace missing')); };
                    s.onerror = function(){ reject(new Error('Could not load jsPDF from CDN')); };
                    document.head.appendChild(s);
                });
                return jsPDFLoad;
            }
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.btgsb-pdf-dl');
                if (!btn) return;
                e.preventDefault();
                var pngUrl = btn.getAttribute('data-png');
                var fname  = btn.getAttribute('data-fname') || '';
                if (!pngUrl) return;
                var orig = btn.innerHTML;
                btn.innerHTML = '\u23F3 Building PDF\u2026';
                btn.disabled = true;
                loadJsPDF().then(function(ns){
                    var img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = function(){
                        try {
                            // Sheet is 22" wide by convention; height derived from aspect.
                            var inW = 22;
                            var inH = (img.naturalHeight / img.naturalWidth) * inW;
                            var pdf = new ns.jsPDF({
                                orientation: inH > inW ? 'portrait' : 'landscape',
                                unit: 'in',
                                format: [inW, inH],
                                compress: true
                            });
                            pdf.addImage(img, 'PNG', 0, 0, inW, inH, undefined, 'FAST');
                            var name = fname || (pngUrl.split('/').pop() || 'gang-sheet.png').replace(/\.png(\?.*)?$/i, '.pdf');
                            pdf.save(name);
                            btn.innerHTML = '\u2713 Downloaded';
                            setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; }, 1800);
                        } catch (err) {
                            alert('Could not generate PDF: ' + err.message);
                            btn.innerHTML = orig; btn.disabled = false;
                        }
                    };
                    img.onerror = function(){
                        alert('Could not load PNG for PDF conversion. Check that the file still exists on the server.');
                        btn.innerHTML = orig; btn.disabled = false;
                    };
                    img.src = pngUrl;
                }).catch(function(err){
                    alert(err.message);
                    btn.innerHTML = orig; btn.disabled = false;
                });
            });
        })();
        </script>
        <?php
    });

    add_action('admin_init', function () {
        if (empty($_GET['btgsb_await_toggle'])) return;
        if (!current_user_can('manage_woocommerce')) return;
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'btgsb_await_toggle')) return;
        $id = intval($_GET['btgsb_await_toggle']);
        btdtf_await_set($id, !btdtf_await_is($id));
        wp_safe_redirect(remove_query_arg(['btgsb_await_toggle', '_wpnonce'], wp_get_referer() ?: admin_url('admin.php?page=wc-orders')));
        exit;
    });

    add_filter('woocommerce_shop_order_list_table_columns', function ($cols) {
        $new = [];
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'order_status') $new['btgsb_await'] = 'Awaiting';
        }
        if (!isset($new['btgsb_await'])) $new['btgsb_await'] = 'Awaiting';
        return $new;
    });

    add_action('woocommerce_shop_order_list_table_custom_column', function ($col, $order) {
        if ($col === 'btgsb_await') echo btdtf_await_badge($order->get_id());
    }, 10, 2);

    add_filter('manage_edit-shop_order_columns', function ($cols) {
        $new = [];
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'order_status') $new['btgsb_await'] = 'Awaiting';
        }
        if (!isset($new['btgsb_await'])) $new['btgsb_await'] = 'Awaiting';
        return $new;
    });

    add_action('manage_shop_order_posts_custom_column', function ($col, $post_id) {
        if ($col === 'btgsb_await') echo btdtf_await_badge($post_id);
    }, 10, 2);

    add_filter('bulk_actions-woocommerce_page_wc-orders', 'btdtf_await_bulk_actions');

    add_filter('bulk_actions-edit-shop_order',            'btdtf_await_bulk_actions');

    add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'btdtf_await_bulk_handle', 10, 3);

    add_filter('handle_bulk_actions-edit-shop_order',            'btdtf_await_bulk_handle', 10, 3);

    add_action('admin_notices', function () {
        if (isset($_GET['btgsb_await_bulk']))
            echo '<div class="notice notice-success is-dismissible"><p>'
               . intval($_GET['btgsb_await_bulk']) . ' order(s) updated.</p></div>';
    });

    add_filter('views_woocommerce_page_wc-orders', 'btdtf_await_view_link');

    add_filter('views_edit-shop_order',            'btdtf_await_view_link');

    add_filter('woocommerce_order_list_table_prepare_items_query_args', function ($args) {
        if (empty($_GET['btgsb_awaiting'])) return $args;
        $args['meta_query'][] = ['key' => BTDTF_AWAIT_META, 'value' => 'yes'];
        return $args;
    });

    add_filter('request', function ($vars) {
        if (empty($_GET['btgsb_awaiting'])) return $vars;
        if (($vars['post_type'] ?? '') !== 'shop_order') return $vars;
        $vars['meta_key']   = BTDTF_AWAIT_META;
        $vars['meta_value'] = 'yes';
        return $vars;
    });
}
