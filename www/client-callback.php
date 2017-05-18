<?php
/**
 * Test script that can be used as a subscriber callback URL
 *
 * @param GET:failcode HTTP status code to fail with
 */
header('HTTP/1.0 500 Internal Server Error');

require_once __DIR__ . '/../data/phubb.config.php';
if (!$devMode) {
    header('HTTP/1.0 403 Forbidden');
    echo "devMode is disabled\n";
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['hub_challenge'])) {
        //TODO: verify subscription request is open
        header('HTTP/1.0 200 OK');
        echo $_GET['hub_challenge'];
        exit();
    }
    echo "Huh? I did not get a hub.challenge parameter.\n";
} else {
    //POST
    //notification that a topic URL was updated
    if (isset($_GET['failcode']) && is_numeric($_GET['failcode'])) {
        header('HTTP/1.0 ' . intval($_GET['failcode']) . ' Fail..');
        echo "I am failing, I am failing, through the dark net, across the bits\n";
        exit(1);
    }
    //FIXME: parse hub + topic URL
    //FIXME: check secret
    $secret = 'mysecret';
    $data = file_get_contents('php://input');

    if (isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
        list($type, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE']);
        if ($type !== 'sha1') {
            header('HTTP/1.0 400 Bad Request');
            echo "Unsupported signature type\n";
            exit(1);
        }
        $hashverification = hash_hmac('sha1', $data, $secret);
        if ($hash != $hashverification) {
            header('HTTP/1.0 202 Accepted');
            echo "Thanks, but the hash does not match\n";
            exit();
        }
    }
    //var_dump($_SERVER);die();

    file_put_contents('/tmp/foo', $data);
    header('HTTP/1.0 202 Accepted');
    echo "thanks for notifying\n";
    exit();
}
?>
