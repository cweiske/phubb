<?php
namespace phubb;

function getHubIndex(): string
{
    return Config::load()->baseUrl;
}

function getHubUrl(): string
{
    return getHubIndex() . 'hub.php';
}

function isValidUrl(string $url): bool
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

function isValidTopic(string $url): bool
{
    $topicBlacklist = Config::load()->topicBlacklist;
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
