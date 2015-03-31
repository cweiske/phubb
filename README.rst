*******************************
phubb - PHP PubSubHubbub server
*******************************

Work in progress.
Works basically.


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
- Clean up temp data after all pings are done
- Re-ping if ping was unsuccessful for a subscriber
- stats
- require domain registration before being able to publish
- do not allow subscriptions for urls that are not registered
