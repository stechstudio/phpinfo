<?php

namespace STS\Phpinfo\Traits;

trait Slugifies
{
    protected function slugify($text): string
    {
        return strtolower(
            str_replace([' ', '-', '/', '.'], '_',
                str_replace(['"',"'"], "", $text)
            )
        );
    }
}