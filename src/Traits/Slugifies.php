<?php

namespace STS\Phpinfo\Traits;

trait Slugifies
{
    protected function slugify($text): string
    {
        return strtolower(
            trim(
                str_replace([' ', '-', '/', '.', '[', ']'], '_',
                    str_replace(['"', "'", '$'], "", $text)
                ), '_'
            )
        );
    }
}