<?php
/**
 * BT DTF Studio — self-updater (GitHub API, no CDN delay).
 * Same mechanism as BT Catalog: manifest.json read through api.github.com
 * (instant on push), releases ship uniquely-named zips so URLs are never stale.
 */
if (!defined('ABSPATH')) exit;

function btdtf_gh_repo() {
    return 'strummer95/bt-dtf';
}

/** Read the manifest through the GitHub API (instant; reflects latest push). */
function btdtf_update_manifest() {
    $cached = get_transient('btdtf_manifest');
    if ($cached !== false) return $cached;

    $url  = 'https://api.github.com/repos/' . btdtf_gh_repo() . '/contents/manifest.json';
    $resp = wp_remote_get($url, array(
        'timeout' => 10,
        'headers' => array(
            'Accept'     => 'application/vnd.github.raw',
            'User-Agent' => 'BT-DTF-Updater',
        ),
    ));

    $info = array();
    if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
        $j = json_decode(wp_remote_retrieve_body($resp), true);
        if (is_array($j)) $info = $j;
    }
    set_transient('btdtf_manifest', $info, 30 * MINUTE_IN_SECONDS);
    return $info;
}

add_filter('pre_set_site_transient_update_plugins', 'btdtf_push_update');
function btdtf_push_update($transient) {
    if (!is_object($transient) || empty($transient->checked)) return $transient;
    $info = btdtf_update_manifest();
    if (empty($info['version']) || empty($info['download_url'])) return $transient;

    $file = plugin_basename(BTDTF_FILE);
    if (version_compare($info['version'], BTDTF_VERSION, '>')) {
        $transient->response[$file] = (object) array(
            'slug'        => 'bt-dtf',
            'plugin'      => $file,
            'new_version' => $info['version'],
            'package'     => $info['download_url'],
            'url'         => isset($info['homepage']) ? $info['homepage'] : '',
            'tested'      => isset($info['tested']) ? $info['tested'] : '',
        );
    }
    return $transient;
}

function btdtf_force_update_check() {
    delete_transient('btdtf_manifest');
    delete_site_transient('update_plugins');
}

add_filter('plugins_api', 'btdtf_update_info', 20, 3);
function btdtf_update_info($res, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'bt-dtf') return $res;
    $info = btdtf_update_manifest();
    if (empty($info)) return $res;
    $o = new stdClass();
    $o->name          = 'BT DTF Studio';
    $o->slug          = 'bt-dtf';
    $o->version       = isset($info['version']) ? $info['version'] : BTDTF_VERSION;
    $o->author        = 'Duck and Rabbit Co.';
    $o->homepage      = isset($info['homepage']) ? $info['homepage'] : '';
    $o->download_link = isset($info['download_url']) ? $info['download_url'] : '';
    $o->tested        = isset($info['tested']) ? $info['tested'] : '';
    $o->sections      = array('changelog' => isset($info['changelog']) ? $info['changelog'] : '');
    return $o;
}
