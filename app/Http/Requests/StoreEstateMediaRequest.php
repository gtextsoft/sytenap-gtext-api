<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estate_id' => 'required|exists:estates,id',

            // Multiple photos
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpg,jpeg,png|max:5120',

            // Multiple 3D model images
            'third_dimension_model_images' => 'nullable|array',
            'third_dimension_model_images.*' => 'image|mimes:jpg,jpeg,png|max:5120',

            // Single video
            'third_dimension_model_video' => 'nullable|mimetypes:video/mp4,video/quicktime|max:20480',

            // Virtual tour (single video url upload or link)
            'virtual_tour_video_url' => 'nullable|url',

            'status' => ['nullable', Rule::in(['draft', 'publish', 'unpublish'])],
        ];
    }
}
