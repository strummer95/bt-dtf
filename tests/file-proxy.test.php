<?php
/**
 * The file proxy serves exactly the order's own production files and nothing
 * else. It is the one place the plugin turns a stored URL into a path on
 * disk, so it is the one place a traversal would matter.
 *
 *     php tests/file-proxy.test.php
 *
 * No WordPress, no database. The two functions under test are lifted out of
 * backend.php and run against stubs.
 */

$src = file_get_contents(__DIR__ . '/../bt-dtf/includes/backend.php');
if ($src === false) { fwrite(STDERR, "cannot read backend.php\n"); exit(1); }

$UP = ['basedir' => '', 'baseurl' => 'https://example.com/wp-content/uploads'];

function wp_upload_dir()            { global $UP; return $UP; }
function wp_parse_url($u, $c = -1)  { return $c === -1 ? parse_url($u) : parse_url($u, $c); }
function admin_url($p = '')         { return 'http://example.com/wp-admin/' . $p; }
function wp_create_nonce($a = '')   { return 'NONCE'; }

foreach (['btdtf_upload_path_for_url', 'btdtf_file_proxy_url'] as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', $src, $m)) {
        fwrite(STDERR, "could not lift $fn out of backend.php\n"); exit(1);
    }
    eval($m[0]);
}

$fails = 0;
function ok($label, $cond, $extra = '') {
    global $fails;
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $label . ($extra !== '' ? "   $extra" : '') . "\n";
    if (!$cond) $fails++;
}

// a real uploads tree
$root = sys_get_temp_dir() . '/btdtf-proxy-' . getmypid();
@mkdir($root . '/2026/11', 0777, true);
file_put_contents($root . '/2026/11/sheet.png', 'PNGDATA');
file_put_contents(dirname($root) . '/outside-' . getmypid() . '.txt', 'SECRET');
$outside = realpath(dirname($root) . '/outside-' . getmypid() . '.txt');
$UP['basedir'] = $root;

$wanted = realpath($root . '/2026/11/sheet.png');

/* ---- it finds the order's own file ------------------------------------ */
ok('a normal uploads URL resolves to the file',
   btdtf_upload_path_for_url('https://example.com/wp-content/uploads/2026/11/sheet.png') === $wanted);

/* ---- and does so no matter what scheme or host the URL was saved with,
        which is the entire reason this exists -------------------------- */
ok('the same path over http still resolves',
   btdtf_upload_path_for_url('http://example.com/wp-content/uploads/2026/11/sheet.png') === $wanted);
ok('a URL saved under the OLD host still resolves',
   btdtf_upload_path_for_url('https://old-server.example.net/wp-content/uploads/2026/11/sheet.png') === $wanted);
ok('a protocol-relative URL still resolves',
   btdtf_upload_path_for_url('//example.com/wp-content/uploads/2026/11/sheet.png') === $wanted);

/* ---- and refuses everything else -------------------------------------- */
ok('a file outside uploads is refused',
   btdtf_upload_path_for_url('https://example.com/wp-config.php') === '');
ok('a traversal out of uploads is refused',
   btdtf_upload_path_for_url('https://example.com/wp-content/uploads/../../wp-config.php') === '',
   var_export(btdtf_upload_path_for_url('https://example.com/wp-content/uploads/../../wp-config.php'), true));
ok('an ENCODED traversal is refused',
   btdtf_upload_path_for_url('https://example.com/wp-content/uploads/%2e%2e/%2e%2e/wp-config.php') === '');
ok('a traversal to a real file outside uploads is refused',
   btdtf_upload_path_for_url('https://example.com/wp-content/uploads/../' . basename($outside)) === '');
ok('an empty URL is refused',        btdtf_upload_path_for_url('') === '');
ok('a path-less URL is refused',     btdtf_upload_path_for_url('https://example.com') === '');
ok('a missing file under uploads is refused',
   btdtf_upload_path_for_url('https://example.com/wp-content/uploads/2026/11/not-here.png') === '');

/* ---- the URL handed to the browser is same-origin by construction ------ */
$u = btdtf_file_proxy_url(5679, 'sheet');
ok('proxy URL is root-relative, so it cannot be cross-origin',
   $u[0] === '/' && strpos($u, '://') === false, $u);
ok('proxy URL carries the order and a nonce',
   strpos($u, 'oid=5679') !== false && strpos($u, 'nonce=') !== false);
ok('an unknown "which" falls back to sheet, never passes through',
   strpos(btdtf_file_proxy_url(1, '../evil'), 'which=sheet') !== false);

@unlink($root . '/2026/11/sheet.png'); @rmdir($root . '/2026/11'); @rmdir($root . '/2026'); @rmdir($root);
@unlink($outside);

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit($fails ? 1 : 0);
