*******************************
phubb - PHP PubSubHubbub server
*******************************

Work in progress. Basic functionality is working.

Implements PubSubHubbub 0.4.


What works / Features
=====================
- Subscribing to a topic
- Notifying the hub about a topic update
- Sending notifications to subscribers

  - As many worker-processes as you want to speed it up
  - Notifications get only sent when lease time is >= NOW()
  - Notifications get only sent when the content changed.
    phubb uses etag, last modified and a hash on the content to check that.
- Re-pinging a subscriber when it failed (exponential back-off)


Dependencies
============
* PHP
* PDO
* PHP Gearman extension
* Gearman job server, ``gearman-job-server``


Installation
============
#. Point the web server's document root to the ``www/`` directory.
#. Create a new MySQL database and import the schema from ``data/schema.sql``.
   Database host and username/password/db name are currently harcoded to
   ``127.0.0.1`` and ``pubb``.
#. Run the worker process ``bin/worker.php``
#. Let ``bin/phubb-cron.php`` be run by cron every minute.

The hub URL is at ``http://$domain/hub.php``.


Notifying the hub about an update
=================================

Send a POST request with the following data::

    hub.mode=publish
    hub.url=http://topic-url.example.org/

Example::
    $ curl -d hub.mode=publish -d "hub.url=http://blog.example.org/feed"\
        http://phubb.example.org/hub.php


Testing
=======
Tasks can be sent via the ``test-task.php`` script to the worker.

Verify a subscription::

  $ ./bin/test-task.php verify http://phubb.bogo/client-callback.php http://www.bogo/tagebuch/feed/ subscribe 3600 mysecret

Publish an update::

  $ ./bin/test-task.php publish http://www.bogo/tagebuch/feed/

Notify subscriber::

  $ ./bin/test-task.php notifysubscriber http://www.bogo/tagebuch/feed/ 1 55140a8d865a9


References
==========
* https://pubsubhubbub.googlecode.com/git/pubsubhubbub-core-0.4.html


TODO
====
- logging
- stats
- require domain registration before being able to publish

  - check if URL topic URL has hub link (and self link)
- do not allow subscriptions for urls that are not registered
- maybe: give phubb a base url, and it figures out by itself what actually
  changed
