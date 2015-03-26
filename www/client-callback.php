<?php
header('HTTP/1.0 500 Internal Server Error');
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['hub_challenge'])) {
        //TODO: verify subscription request is open
        header('HTTP/1.0 200 OK');
        echo $_GET['hub_challenge'];
        exit();
    }
} else {
    //POST
    //notification that a topic URL was updated
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
