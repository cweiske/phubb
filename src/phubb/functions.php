<?php
namespace phubb;
require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function getHubIndex()
{
    require __DIR__ . '/../../data/phubb.config.php';
    return $baseUrl;
}

function getHubUrl()
{
    return getHubIndex() . 'hub.php';
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
    require __DIR__ . '/../../data/phubb.config.php';
    if (!is_array($topicBlacklist) || !count($topicBlacklist)) {
        return true;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (array_search($host, $topicBlacklist) !== false) {
        return false;
    }

    return true;
}
?>
