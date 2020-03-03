*************************
phubb - PHP WebSub server
*************************

A WebSub__ server written in PHP, ready to be run on your own server.

Use it to instantly notify subscribers about updates on your blog's feed,
your website's HTML pages or any other HTTP-accessible resources.

Work in progress. Basic functionality is working.

WebSub was formerly called PubSubHubbub.

Implements PubSubHubbub 0.4.

__ https://www.w3.org/TR/websub/

.. contents::


What works / Features
=====================
- Subscribing to a topic
- Notifying the hub about a topic update

  - Wildcard URLs supported (with ``*``).
    All matching subscribed topics are then checked if they changed and
    notifications are sent out.
    Wildcards in domain or scheme are not allowed.
- Sending notifications to subscribers

  - As many worker-processes as you want to speed it up
  - Notifications get only sent when lease time is >= NOW()
  - Notifications get only sent when the content changed.
    phubb uses etag, last modified and a hash on the content to check that.
- Re-pinging a subscriber when it failed (exponential back-off)
- Logging
- Re-connecting to DB if we had a timeout
- Subscriber count image (``counter.php?topic=$url``)


Dependencies
============
* PHP
* PDO
* PHP Gearman extension
* Gearman job server, ``gearman-job-server``
* Monolog


Installation
============
#. Point the web server's document root to the ``www/`` directory.
#. Create a new MySQL database and import the schema from ``data/schema.sql``.
   Database host and username/password/db name can be adjusted by copying
   ``data/phubb.config.php.dist`` to ``data/phubb.config.php`` and
   adjusting it to your needs.
#. Install dependencies::

     $ composer install

#. Run the worker process ``bin/phubb-worker.php``
#. Let ``bin/phubb-cron.php`` be run by cron every minute.

The hub URL is at ``http://$domain/hub.php``.


System service
--------------
When using systemd, you can let it run multiple worker instances when
the system boots up:

#. Copy files ``data/systemd/phubb*.service`` into ``/etc/systemd/system/``
#. Adjust user and group names
#. Enable three worker processes::

     $ systemctl daemon-reload
     $ systemctl enable phubb@1
     $ systemctl enable phubb@2
     $ systemctl enable phubb@3
     $ systemctl enable phubb
     $ systemctl start phubb
#. Now three workers are running. Restarting the ``phubb`` service also
   restarts the workers.


Notifying the hub about an update
=================================

Send a POST request with the following data::

    hub.mode=publish
    hub.url=http://topic-url.example.org/

Example::

    $ curl -d hub.mode=publish -d "hub.url=http://blog.example.org/feed"\
        http://phubb.example.org/hub.php

or, to automatically publish all modified URLs in that path::

    $ curl -d hub.mode=publish -d "hub.url=http://blog.example.org/*"\
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
* https://www.w3.org/TR/websub/
* https://pubsubhubbub.googlecode.com/git/pubsubhubbub-core-0.4.html


TODO
====
- stats
- require domain registration before being able to publish

  - check if URL topic URL has hub link (and self link)
- do not allow subscriptions for urls that are not registered
- custom user agent when fetching URLs


Source code
===========
phubb's source code is available from http://git.cweiske.de/phubb.git
or the `mirror on github`__.

__ https://github.com/cweiske/phubb


License
=======
phubb is licensed under the `AGPL v3 or later`__.

__ http://www.gnu.org/licenses/agpl.html


Author
======
phubb was written by `Christian Weiske`__.

__ http://cweiske.de/
