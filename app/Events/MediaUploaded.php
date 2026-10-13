<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;

class MediaUploaded
{
    use Dispatchable;

    public function __construct(public Media $media) {}
}
