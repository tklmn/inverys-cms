<?php

namespace App\Events;

use App\Models\Page;
use Illuminate\Foundation\Events\Dispatchable;

class PageCreated
{
    use Dispatchable;

    public function __construct(public Page $page) {}
}
