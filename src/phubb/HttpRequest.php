<?php
namespace phubb;

class HttpRequest extends \HTTP_Request2
{
    /**
     * @param string $url    URL to fetch
     * @param string $method HTTP method name
     */
    public function __construct($url = null, $method = 'GET')
    {
        parent::__construct($url, $method);
        $this->setConfig('follow_redirects', true);
        $this->setConfig('connect_timeout', 5);
        $this->setConfig('timeout', 10);
        $this->setHeader('user-agent', 'phubb/bot');
    }

    public function send()
    {
        echo "SEND: " . $this->method . ' ' . (string) $this->url . "\n";
        return parent::send();
    }

}
?>
