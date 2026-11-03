<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }
}
