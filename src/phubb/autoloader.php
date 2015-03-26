<?php
function __autoload($class)
{
    $file = __DIR__ . '/../'
        . str_replace(array('\\', '_'), '/', $class)
        . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
?>
