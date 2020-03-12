<?php
namespace phubb;

class Helper
{
    /**
     * @return string[]
     */
    public static function getTmpFilePaths(int $id)
    {
        return array(
            __DIR__ . '/../../tmp/ping-' . $id . '-headers',
            __DIR__ . '/../../tmp/ping-' . $id . '-content'
        );
    }
}
?>
