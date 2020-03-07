<?php
/**
 * Publish an update to the hub
 */
$hub      = 'http://phubb.bogo/hub.php';
$topic    = 'http://www.bogo/tagebuch/feed/';

$params = array(
    'hub.mode' => 'publish',
    'hub.url'  => $topic,
);
$enc = array();
foreach ($params as $key => $val) {
    $enc[] = urlencode($key) . '=' . urlencode($val);
}
$postMsg = implode('&', $enc);

$ctx = stream_context_create(
    array(
        'http' => array(
            'method' => 'POST',
            'header' => array(
                'User-Agent: phubb/bot',
                'Content-type: application/x-www-form-urlencoded',
            ),
            'content' => $postMsg,
            'ignore_errors' => true,
        )
    )
);

$res = file_get_contents($hub, false, $ctx);
list($http, $code, $rest) = explode(' ', $http_response_header[0]);
if (intval($code / 100) === 2) {
    echo "all fine: " . rtrim($res) . "\n";
    exit();
}

echo "Error: HTTP status was not 2xx; got $code\n";
var_dump(
    $http_response_header,
    $res
);
?>
