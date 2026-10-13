<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlockTypeResource;
use App\Models\BlockType;

class BlockTypeController extends Controller
{
    public function index()
    {
        return BlockTypeResource::collection(BlockType::all());
    }
}
