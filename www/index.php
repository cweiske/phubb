<?php
namespace phubb;

require_once __DIR__ . '/../vendor/autoload.php';
$hub_index = getHubIndex();
$hub_url   = getHubUrl();
?>
<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <title>phubb - PHP WebSub server</title>
  <style type="text/css">
body {
    max-width: 80ex;
    margin-left: auto;
    margin-right: auto;
}
pre {
    margin-left: 2ex;
    background-color: #DDD;
    padding: 1ex;
    border-radius: 1ex;
}
tt {
    background-color: #DDD;
    padding: 0.2ex 0.5ex;
}
  </style>
 </head>
 <body>
  <h1>phubb - PHP WebSub server</h1>
  <p>
   This is a
   <a href="https://www.w3.org/TR/websub/">WebSub</a>
   server.
   You can use it to instantly notify feed subscribers about updates.
  </p>


  <h2 id="howto">HowTo use phubb on your site</h2>

  <h3 id="notify">Notify the hub about an update</h3>
  <p>
   To notify this hub that one of your sites changed,
   send a HTTP POST request to
   <tt><a href="<?php echo htmlspecialchars($hub_url); ?>"><?php echo htmlspecialchars($hub_url); ?></a></tt>:
  </p>
  <pre>$ curl -d hub.mode=publish -d "hub.url=http://blog.example.org/feed" <?php echo htmlspecialchars($hub_url); ?></pre>
  <p>
   This hub will then notify all subscribers about the update.
  </p>


  <h4 id="modes">Additional notification modes</h4>
  <p>
   This hub supports additional notification modes.
  </p>
  <p>
   If you do not know which of your pages did change - only that some of them
   did change - you can pass a wildcard URL (with <tt>*</tt>).
   phubb then checks all subscribed URLs that match the wildcard for changes
   and then notifies the subscribers:
  </p>
  <pre>hub.url=http://blog.example.org/*</pre>


  <h3 id="headers">HTTP headers for your feeds/files</h3>
  <p>
   To enable feed readers to detect that they can get updates via PubSubHubbub,
   you have to send two HTTP <tt>Link</tt> headers along with your files.
  </p>
  <p>
   The first is a <tt>Link</tt> header pointing to this hub:
  </p>
  <pre>Link: &lt;<?php echo htmlspecialchars($hub_url); ?>&gt;; rel="hub"</pre>
  <p>
   The second is a <tt>Link</tt> header containing the full URL of the site
   itself:
  </p>
  <pre>Link: &lt;http://blog.example.org/feed&gt;; rel="self"</pre>
  <p>
   Only if both HTTP headers are available, feed readers and other subscribers
   will be able to get updates via this hub.
  </p>


  <h3 id="counter">Counter</h3>
  <p>
   You can show the number of feed subscribers with an image on your site.
   The URL is <tt>counter.php?topic=$url</tt>:
  </p>
  <pre>&lt;img width="55" height="20" alt="Subscriber counter"
     src="<?= htmlspecialchars($hub_index) ?>counter.php?topic=http://example.org/feed" /></pre>


  <h2 id="phubb">phubb</h2>
  <p>
   <a href="http://cweiske.de/phubb.htm">phubb</a> is a WebSub server
   written by
   <a href="http://cweiske.de/">Christian Weiske</a>
   and licensed under the
   <a href="http://www.gnu.org/licenses/agpl.html">AGPL v3 or later</a>.
  </p>
  <p>
   You can get the source code from
   <a href="http://git.cweiske.de/phubb.git">git.cweiske.de/phubb.git</a>
   or the
   <a href="https://github.com/cweiske/phubb">mirror on Github</a>.
  </p>
 </body>
</html>
