<?php
header('HTTP/1.0 500 Internal Server Error');
$db = new PDO('mysql:dbname=phubb;host=127.0.0.1', 'phubb', 'phubb');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = 'SELECT * FROM requests ORDER BY req_created DESC';
foreach ($db->query($sql) as $row) {
    verifySubscription($row);
}

function verifySubscription($row)
{
    $challenge = mt_rand();
    $url = $row->req_callback;
    $sep = strpos($url, '?') === false ? '?' : ':';
    $url .= $sep . 'hub.mode=' . urlencode($row->req_mode)
        . '&hub.topic=' . urlencode($row->req_topic)
        . '&hub.challenge=' . urlencode($challenge)
        . '&hub.lease_seconds=' . urlencode($row->req_lease_seconds);
    echo $url . "\n";

    $res = file_get_contents($url);
    list(, $status, ) = explode(' ', $http_response_header[0]);
    if ($status != 200) {
        //subscription error
        failSubscription(
            $row->req_id,
            'verification response status not 200 but ' . (int) $status
        );
    } else if ($res != $challenge) {
        //challenge does not match
        failSubscription(
            $row->req_id,
            'verification response does not match challenge but is '
            . gettype($res) . '(' . strlen($res) . '): '
            . '"' . str_replace("\n", '\\n', substr($res, 0, 128)) . '"'
        );
    } else {
        //subscription validated
        acceptSubscription($row);
    }
}

function failSubscription($id, $reason)
{
    echo "fail: $reason\n";return;
    deleteRequest($id);
}

function acceptSubscription($rowRequest)
{
    global $db;
    if ($rowRequest->req_mode == 'unsubscribe') {
        $db->prepare(
            'DELETE FROM subscriptions'
            . ' WHERE sub_callback = :callback AND sub_topic = :topic'
        )->execute(
            array(
                ':callback' => $rowRequest->req_callback,
                ':topic'    => $rowRequest->req_topic
            )
        );
        deleteRequest($rowRequest->req_id);
        return;
    }
    
    $stmt = $db->prepare(
        'SELECT sub_id FROM subscriptions'
        . ' WHERE sub_callback = :callback AND sub_topic = :topic'
    );
    $stmt->execute(
        array(
            ':callback' => $rowRequest->req_callback,
            ':topic'    => $rowRequest->req_topic
        )
    );
    $rowSub = $stmt->fetch();
    if ($rowSub === false) {
        //new subscription
        $db->prepare(
            'INSERT INTO subscriptions'
            . '(sub_created, sub_updated, sub_callback, sub_topic, sub_secret'
            . ', sub_lease_seconds, sub_lease_end)'
            . ' VALUES(NOW(), NOW(), :callback, :topic, :secret'
            . ', :leaseSeconds, :leaseEnd)'
        )->execute(
            array(
                ':callback' => $rowRequest->req_callback,
                ':topic'    => $rowRequest->req_topic,
                ':secret'   => $rowRequest->req_secret,
                ':leaseSeconds' => $rowRequest->req_lease_seconds,
                ':leaseEnd' => date(
                    'Y-m-d H:i:s', time() + $rowRequest->req_lease_seconds
                )
            )
        );
        deleteRequest($rowRequest->req_id);
        return;
    }

    //existing subscription
    $db->prepare(
        'UPDATE subscriptions SET'
        . ' sub_updated = NOW()'
        . ', sub_secret = :secret'
        . ', sub_lease_seconds = :leaseSeconds'
        . ', sub_lease_end = :leaseEnd'
        . ' WHERE sub_id = :id'
    )->execute(
        array(
            ':secret'       => $rowRequest->req_secret,
            ':leaseSeconds' => $rowRequest->req_lease_seconds,
            ':leaseEnd'     => date(
                'Y-m-d H:i:s', time() + $rowRequest->req_lease_seconds
            ),
            ':id'           => $rowSub->sub_id
        )
    );
    deleteRequest($rowRequest->req_id);
}

function deleteRequest($id)
{
    global $db;
    $db->prepare('DELETE FROM requests WHERE req_id = :id')
        ->execute(array(':id' => $id));
}
?>
