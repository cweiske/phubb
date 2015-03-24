<?php
$hub      = 'http://phubb.bogo/hub-subscribe.php';
$callback = 'http://phubb.bogo/client-callback.php';
$topic    = 'http://www.bogo/tagebuch/feed/';
$secret   = 'mysecret';//TODO: make random

$params = array(
    'hub.callback'      => $callback,
    'hub.mode'          => 'subscribe',
    'hub.topic'         => $topic,
    'hub.lease_seconds' => 3600,
    'hub.secret'        => $secret,
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
                'Content-type: application/x-www-form-urlencoded',
            ),
            'content' => $postMsg,
            'ignore_errors' => true,
        )
    )
);

$res = file_get_contents($hub, false, $ctx);
list($http, $code, $rest) = explode(' ', $http_response_header[0]);
if (intval($code) === 202) {
    echo "all fine\n";
    exit();
}

echo "Error: HTTP status 202 expected; got $code\n";
var_dump(
    $http_response_header,
    $res
);
?>
