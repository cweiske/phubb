*******************************
phubb - PHP PubSubHubbub server
*******************************

Work in progress.


Dependencies
============
* PHP
* PDO
* PHP Gearman extension
* Gearman job server, ``gearman-job-server``


Installation
============
#. Point the web server's document root to the ``www/`` directory.
#. Create a new MySQL database and import the schema from ``data/schema.sql``
#. Run the worker process ``bin/worker.php``


Testing
=======
Verify a subscription::

  $ ./bin/test-task.php verify http://phubb.bogo/client-callback.php http://www.bogo/tagebuch/feed/ subscribe 3600 mysecret

Publish an update::

  $ ./bin/test-task.php publish http://www.bogo/tagebuch/feed/

Notify subscriber::

  $ ./bin/test-task.php notifysubscriber http://www.bogo/tagebuch/feed/ 1 55140a8d865a9


References
==========
* https://pubsubhubbub.googlecode.com/git/pubsubhubbub-core-0.4.html
