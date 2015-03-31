<?php
namespace phubb;

class Helper
{
    public static function getTmpFilePaths($id)
    {
        return array(
            __DIR__ . '/../../tmp/ping-' . $id . '-headers',
            __DIR__ . '/../../tmp/ping-' . $id . '-content'
        );
    }
}
?>
