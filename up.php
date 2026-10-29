<?php
// Web-Based Computer Stress Test - network upload endpoint.
// POST up.php with any body  ->  replies with the number of bytes received.
// The page compares that number with what it sent to confirm the upload arrived intact.
// Uploads are discarded, never written to disk. The page sends at most 6 MB per request,
// which fits PHP's default post_max_size (8 MB).
header('Access-Control-Allow-Origin: *');
header('Timing-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: text/plain');

$in = fopen('php://input', 'rb');
$n = 0;
while (!feof($in)) {
    $buf = fread($in, 65536);
    if ($buf === false) { break; }
    $n += strlen($buf);
}
fclose($in);
echo $n;
