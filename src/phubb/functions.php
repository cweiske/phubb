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

function isValidTopic($url)
{
    $host = parse_url($url, PHP_URL_HOST);

    require __DIR__ . '/../../data/phubb.config.php';

    if (is_array($topicWhitelist) && count($topicWhitelist)) {
        if (array_search($host, $topicWhitelist) !== false) {
            return true;
        }
        return false;
    }

    if (!is_array($topicBlacklist) || !count($topicBlacklist)) {
        return true;
    }

    if (array_search($host, $topicBlacklist) !== false) {
        return false;
    }

    return true;
}
?>
