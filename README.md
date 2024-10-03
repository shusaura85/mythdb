MythDB
=========

By Shu Saura

[MythDB](https://github.com/shusaura85/mythdb) is a simple database abstraction layer for PHP providing a consistent interface for multiple database drivers.

Supported databases
--------
* mySQL and MariaDB
* PostreSQL
* SQLite3


Installation
--------------------

* Using **Composer**

    ``` shell
    composer require shusaura85/mythdb
    ```
* Manually

    ``` php
    require '/path/to/src/autoload.php'
    ```


Usage
--------------------

``` php
require 'vendor/autoload.php'; // or '/path/to/src/autoload.php'  

$db_driver = 'mysqli'; // available drivers: mysqli, pgsql, sqlite3  
$db_persistent = false; // set to true to use a persistent connection  
$db_utf8names = 'UTF-8'; // if set, automatically calls query "SET NAMES <value>". not supported in sqlite  
$db = new \MythDB\Database($db_driver, 'host', 'username', 'password', 'database', $db_persistent, $db_utf8names);
```




Requirements
-------------
MythDB requires at least `PHP 8.1` to work. MythDB only depends on the internal PHP modules for database connection (mysqli / postresql / sqlite3).


Licence
-------

MythDB is published under the MIT Licence, see `LICENSE` file for details.

