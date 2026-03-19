<?php

if (!function_exists('prettyphpinfo')) {
    /**
     * Display a pretty, searchable phpinfo() page.
     *
     * @param int $what The INFO_* constants bitmask, same as native phpinfo().
     */
    function prettyphpinfo(int $what = INFO_ALL): void
    {
        \STS\Phpinfo\Info::capture($what)->render();
    }
}
