<?php
namespace phubb;
require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function getHubUrl()
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
        $prot = 'https';
    } else {
        $prot = 'http';
    }
    return $prot . '://' . $_SERVER['HTTP_HOST'] . '/';
}

function isValidUrl($url)
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    if (substr($url, 0, 7) == 'http://'
        || substr($url, 0, 8) == 'https://'
    ) {
        return true;
    }
    return false;
}
?>
