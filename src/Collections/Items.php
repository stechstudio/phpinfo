<?php

namespace STS\Phpinfo\Collections;

use Illuminate\Support\Collection;

class Items extends Collection
{
    public function localValue()
    {
        return $this->get(1);
    }

    public function appendLocalValue($text)
    {
        $this->put(1, $this->get(1) . $text);
    }
}