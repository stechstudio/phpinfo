<?php

if (!function_exists('betterphpinfo')) {
    /**
     * Display a pretty, searchable phpinfo() page.
     *
     * @param int $what The INFO_* constants bitmask, same as native phpinfo().
     */
    function betterphpinfo(int $what = INFO_ALL): void
    {
        \STS\Phpinfo\Info::capture($what)->render();
    }
}
