<?php
//TODO: verify subscription request is open
if (isset($_GET['hub_challenge'])) {
    echo $_GET['hub_challenge'];
    exit();
}
print_r($_REQUEST);
?>
