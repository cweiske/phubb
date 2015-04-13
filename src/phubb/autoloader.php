<?php
spl_autoload_register('phubb_autoload');

function phubb_autoload($class)
{
    $file = __DIR__ . '/../'
        . str_replace(array('\\', '_'), '/', $class)
        . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
?>
