<?php

use STS\Phpinfo\Info;
use STS\Phpinfo\Support\Items;

if (! function_exists('items')) {
    function items(iterable $items = []): Items
    {
        return new Items($items);
    }
}

if (! function_exists('prettyphpinfo')) {
    /**
     * Display a pretty, searchable phpinfo() page.
     *
     * @param  int  $what  The INFO_* constants bitmask, same as native phpinfo().
     */
    function prettyphpinfo(int $what = INFO_ALL): void
    {
        Info::capture($what)->render();
    }
}
