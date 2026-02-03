<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PersonalCollection extends ResourceCollection
{
    public $collects = PersonalResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
