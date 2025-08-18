<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protect with auth if needed
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'town_or_city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'cordinates' => 'nullable|string|max:255',
            'zoning' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
            'direction' => 'nullable|string|max:255',
            'description' => 'nullable|string',

            // Images (uploaded files, not URLs)
            'map_background_image' => 'nullable|image|mimes:jpeg,png,jpg',
            'preview_display_image' => 'nullable|image|mimes:jpeg,png,jpg',

            'has_cerificate_of_occupancy' => 'boolean',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'rating' => 'nullable|integer|min:1|max:5',
            'status' => ['nullable', Rule::in(['draft', 'publish', 'unpublish'])],
        ];
    }
}
