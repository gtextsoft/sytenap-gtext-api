<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Estate;
use App\Http\Requests\StoreEstateMediaRequest;
use App\Models\EstateMedia;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;



class EstateController extends Controller
{

   
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Upload map background image (corrected method)
        if ($request->hasFile('map_background_image')) {
            $uploadResult = Cloudinary::uploadApi()->upload(
                $request->file('map_background_image')->getRealPath(),
                ['folder' => 'estates']
            );
            $data['map_background_image'] = $uploadResult['secure_url'];
        }

        // Upload preview display image (corrected method)
        if ($request->hasFile('preview_display_image')) {
            $uploadResult = Cloudinary::uploadApi()->upload(
                $request->file('preview_display_image')->getRealPath(),
                ['folder' => 'estates']
            );
            $data['preview_display_image'] = $uploadResult['secure_url'];
        }

        $estate = Estate::create($data);

        return response()->json([
            'message' => 'Estate created successfully',
            'data' => $estate
        ], 201);
    }


    

    public function media_store(Request $request)
    {
         $validator = Validator::make($request->all(), [
           'estate_id' => 'required|exists:estates,id',
            // Multiple photos
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpg,jpeg,png|max:5120',

            // Multiple 3D model images
            'third_dimension_model_images' => 'nullable|array',
            'third_dimension_model_images.*' => 'image|mimes:jpg,jpeg,png|max:5120',

            // Single video   'nullable|mimetypes:video/mp4,video/quicktime|max:20480',
            'third_dimension_model_video' => 'nullable|mimes:jpg,jpeg,png|max:5120',
           

            // Virtual tour (single video url upload or link)
            'virtual_tour_video_url' => 'nullable|url',

            'status' => ['nullable', Rule::in(['draft', 'publish', 'unpublish'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
      

        $photos = [];
        $modelImages = null;
        $videoUrl = null;

        // Upload photos (multiple) - corrected method
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $uploadResult = Cloudinary::uploadApi()->upload(
                    $photo->getRealPath(),
                    ['folder' => 'estates/photos']
                );
                $photos[] = $uploadResult['secure_url'];
            }
        }

        // Upload 3D model images (multiple) - corrected method
        if ($request->hasFile('third_dimension_model_images')) {
            $modelImages = [];
            foreach ($request->file('third_dimension_model_images') as $image) {
                $uploadResult = Cloudinary::uploadApi()->upload(
                    $image->getRealPath(),
                    ['folder' => 'estates/models']
                );
                $modelImages[] = $uploadResult['secure_url'];
            }
        }

        // Upload 3D model video (single) - corrected method
        if ($request->hasFile('third_dimension_model_video')) {
            $uploadResult = Cloudinary::uploadApi()->upload(
                $request->file('third_dimension_model_video')->getRealPath(),
                [
                    'folder' => 'estates/videos',
                    'resource_type' => 'video'
                ]
            );
            $videoUrl = $uploadResult['secure_url'];
        }

        $estateMedia = EstateMedia::create([
            'estate_id' => $data['estate_id'],
            'photos' => $photos ?: null,
            'third_dimension_model_images' => $modelImages,
            'third_dimension_model_video' => $videoUrl,
            'virtual_tour_video_url' => $data['virtual_tour_video_url'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        return response()->json([
            'message' => 'Estate media created successfully',
            'data' => $estateMedia
        ], 201);
    }

     
    public function getTopRatedEstates()
    {
        try {
            // Get top 10 estates with highest ratings, including their media
            $topRatedEstates = Estate::with('media')
                ->whereNotNull('rating')
                ->where('rating', '>', 0)
                ->where('status', 'publish') // Only show published estates
                ->orderBy('rating', 'desc')
                ->orderBy('created_at', 'desc') // Secondary sort for estates with same rating
                ->limit(10)
                ->get();

            // Check if we got any results
            if ($topRatedEstates->isEmpty()) {
                return response()->json([
                    'message' => 'No rated estates found',
                    'data' => [],
                    'total_count' => 0
                ], 200);
            }

            // Transform the data to include media information
            $formattedEstates = $topRatedEstates->map(function ($estate) {
                // Safely access media relationship
                $media = null;
                if ($estate->relationLoaded('media') && $estate->media) {
                    $media = [
                        'id' => $estate->media->id,
                        'photos' => $estate->media->photos,
                        'third_dimension_model_images' => $estate->media->third_dimension_model_images,
                        'third_dimension_model_video' => $estate->media->third_dimension_model_video,
                        'virtual_tour_video_url' => $estate->media->virtual_tour_video_url,
                        'status' => $estate->media->status,
                        'created_at' => $estate->media->created_at,
                        'updated_at' => $estate->media->updated_at,
                    ];
                }

                return [
                    'id' => $estate->id,
                    'title' => $estate->title,
                    'town_or_city' => $estate->town_or_city,
                    'state' => $estate->state,
                    'coordinates' => $estate->cordinates,
                    'zoning' => $estate->zoning,
                    'size' => $estate->size,
                    'direction' => $estate->direction,
                    'description' => $estate->description,
                    'rating' => $estate->rating,
                    'status' => $estate->status,
                    'has_certificate_of_occupancy' => $estate->has_cerificate_of_occupancy,
                    'amenities' => $estate->amenities,
                    'map_background_image' => $estate->map_background_image,
                    'preview_display_image' => $estate->preview_display_image,
                    'created_at' => $estate->created_at,
                    'updated_at' => $estate->updated_at,
                    'media' => $media
                ];
            });

            return response()->json([
                'message' => 'Top rated estates retrieved successfully',
                'data' => $formattedEstates,
                'total_count' => $formattedEstates->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving top rated estates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    public function getTopRatedEstatesAlternative()
    {
        try {
            // Alternative approach using join for better performance with large datasets
            $topRatedEstates = Estate::select([
                'estates.*',
                'estate_media.id as media_id',
                'estate_media.photos',
                'estate_media.third_dimension_model_images',
                'estate_media.third_dimension_model_video',
                'estate_media.virtual_tour_video_url',
                'estate_media.status as media_status',
                'estate_media.created_at as media_created_at',
                'estate_media.updated_at as media_updated_at'
            ])
            ->leftJoin('estate_media', 'estates.id', '=', 'estate_media.estate_id')
            ->whereNotNull('estates.rating')
            ->where('estates.rating', '>', 0)
            ->where('estates.status', 'publish')
            ->orderBy('estates.rating', 'desc')
            ->orderBy('estates.created_at', 'desc')
            ->limit(10)
            ->get();

            $formattedEstates = $topRatedEstates->map(function ($estate) {
                return [
                    'id' => $estate->id,
                    'title' => $estate->title,
                    'town_or_city' => $estate->town_or_city,
                    'state' => $estate->state,
                    'coordinates' => $estate->cordinates,
                    'zoning' => $estate->zoning,
                    'size' => $estate->size,
                    'direction' => $estate->direction,
                    'description' => $estate->description,
                    'rating' => $estate->rating,
                    'status' => $estate->status,
                    'has_certificate_of_occupancy' => $estate->has_cerificate_of_occupancy,
                    'amenities' => $estate->amenities,
                    'map_background_image' => $estate->map_background_image,
                    'preview_display_image' => $estate->preview_display_image,
                    'created_at' => $estate->created_at,
                    'updated_at' => $estate->updated_at,
                    'media' => $estate->media_id ? [
                        'id' => $estate->media_id,
                        'photos' => $estate->photos,
                        'third_dimension_model_images' => $estate->third_dimension_model_images,
                        'third_dimension_model_video' => $estate->third_dimension_model_video,
                        'virtual_tour_video_url' => $estate->virtual_tour_video_url,
                        'status' => $estate->media_status,
                        'created_at' => $estate->media_created_at,
                        'updated_at' => $estate->media_updated_at,
                    ] : null
                ];
            });

            return response()->json([
                'message' => 'Top rated estates retrieved successfully',
                'data' => $formattedEstates,
                'total_count' => $formattedEstates->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving top rated estates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
