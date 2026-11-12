<?php
/**
 * The one-click address fix must only ever fire on the one thing it is for:
 * the same address written http instead of https. Everything else - a
 * subdirectory install, a deliberately different host, an already-correct
 * site - must be left alone, because this writes a site-wide option.
 *
 *     php tests/siteurl-fix.test.php
 */
$src = file_get_contents(__DIR__ . '/../bt-dtf/bt-dtf.php');
if ($src === false) { fwrite(STDERR, "cannot read bt-dtf.php\n"); exit(1); }
function untrailingslashit($s) { return rtrim($s, '/\\'); }
if (!preg_match('/\nfunction btdtf_siteurl_fix_target\(.*?\n\}\n/s', $src, $m)) {
    fwrite(STDERR, "could not lift btdtf_siteurl_fix_target out of bt-dtf.php\n"); exit(1);
}
eval($m[0]);

$fails = 0;
function is($label, $got, $want) {
    global $fails;
    $pass = $got === $want;
    echo ($pass ? '  PASS  ' : '  FAIL  ') . $label
       . ($pass ? '' : "   got " . var_export($got, true) . ", wanted " . var_export($want, true)) . "\n";
    if (!$pass) $fails++;
}

// the case this exists for - boomerts.com as found
is('http siteurl + https home -> fix it',
   btdtf_siteurl_fix_target('http://boomerts.com', 'https://boomerts.com'), 'https://boomerts.com');
is('trailing slashes do not confuse it',
   btdtf_siteurl_fix_target('http://boomerts.com/', 'https://boomerts.com/'), 'https://boomerts.com');
is('www is matched on both sides',
   btdtf_siteurl_fix_target('http://www.boomerts.com', 'https://www.boomerts.com'), 'https://www.boomerts.com');

// everything it must not touch
is('already matching -> leave alone',
   btdtf_siteurl_fix_target('https://boomerts.com', 'https://boomerts.com'), '');
is('both on http -> leave alone (nothing to move to)',
   btdtf_siteurl_fix_target('http://boomerts.com', 'http://boomerts.com'), '');
is('home still http -> never move the site TO http',
   btdtf_siteurl_fix_target('https://boomerts.com', 'http://boomerts.com'), '');
is('a WordPress in a subdirectory -> leave alone',
   btdtf_siteurl_fix_target('http://boomerts.com/wp', 'https://boomerts.com'), '');
is('a genuinely different host -> leave alone',
   btdtf_siteurl_fix_target('http://old-server.net', 'https://boomerts.com'), '');
is('www vs non-www is a real difference -> leave alone',
   btdtf_siteurl_fix_target('http://boomerts.com', 'https://www.boomerts.com'), '');
is('empty siteurl -> leave alone', btdtf_siteurl_fix_target('', 'https://boomerts.com'), '');
is('empty home -> leave alone',    btdtf_siteurl_fix_target('http://boomerts.com', ''), '');
is('both empty -> leave alone',    btdtf_siteurl_fix_target('', ''), '');

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit($fails ? 1 : 0);
