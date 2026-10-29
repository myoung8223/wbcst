<?php
// Web-Based Computer Stress Test - network download/latency endpoint.
// GET down.php?bytes=N  ->  N bytes of random data (bytes=0 is used for latency).
// Place next to index.html on any web server with PHP; the page finds it automatically.
header('Access-Control-Allow-Origin: *');
header('Timing-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/octet-stream');

// Never compress or buffer: we're measuring raw transfer speed.
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
while (ob_get_level() > 0) { ob_end_clean(); }

$n = isset($_GET['bytes']) ? (int)$_GET['bytes'] : 0;
$n = max(0, min($n, 100000000));          // cap at 100 MB per request
header('Content-Length: ' . $n);

$chunk = random_bytes(65536);              // random data can't be compressed
while ($n > 0) {
    $k = min($n, 65536);
    echo ($k === 65536) ? $chunk : substr($chunk, 0, $k);
    $n -= $k;
}
