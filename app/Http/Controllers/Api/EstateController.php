<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Estate;
use App\Http\Requests\StoreEstateMediaRequest;
use App\Models\EstateMedia;
use App\Models\EstatePlotDetail;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\JsonResponse;



/**
 * @OA\Tag(
 *     name="Estate Management",
 *     description="API Endpoints for managing estates, media, and plot details"
 * )
 */
class EstateController extends Controller
{

   /**
     * @OA\Post(
     *     path="/api/v1/estate/new",
     *     tags={"Estate Management"},
     *     summary="Create a new estate",
     *     description="Create a new estate with basic information and images",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "town_or_city", "state"},
     *                 @OA\Property(property="title", type="string", maxLength=255, example="Luxury Estate Gardens"),
     *                 @OA\Property(property="town_or_city", type="string", maxLength=255, example="Lekki"),
     *                 @OA\Property(property="state", type="string", maxLength=255, example="Lagos"),
     *                 @OA\Property(property="cordinates", type="string", maxLength=255, example="6.4281,3.4219", description="Latitude,Longitude coordinates"),
     *                 @OA\Property(property="zoning", type="string", maxLength=255, example="Residential"),
     *                 @OA\Property(property="size", type="string", maxLength=255, example="500 sqm"),
     *                 @OA\Property(property="direction", type="string", maxLength=255, example="North-facing"),
     *                 @OA\Property(property="description", type="string", example="Beautiful estate with modern amenities"),
     *                 @OA\Property(property="map_background_image", type="string", format="binary", description="Map background image file"),
     *                 @OA\Property(property="preview_display_image", type="string", format="binary", description="Preview display image file"),
     *                 @OA\Property(property="has_cerificate_of_occupancy", type="boolean", example=true),
     *                 @OA\Property(property="amenities", type="array", @OA\Items(type="string"), example={"Swimming Pool", "Gym", "Security"}),
     *                 @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4),
     *                 @OA\Property(property="status", type="string", enum={"draft", "publish", "unpublish"}, example="publish")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Estate created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Estate created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Luxury Estate Gardens"),
     *                 @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                 @OA\Property(property="state", type="string", example="Lagos"),
     *                 @OA\Property(property="status", type="string", example="publish"),
     *                 @OA\Property(property="created_at", type="string", format="datetime"),
     *                 @OA\Property(property="updated_at", type="string", format="datetime")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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


    
    /**
     * @OA\Post(
     *     path="/api/v1/estate/media",
     *     tags={"Estate Management"},
     *     summary="Upload estate media files",
     *     description="Upload photos, 3D model images, videos, and virtual tour URLs for an estate",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"estate_id"},
     *                 @OA\Property(property="estate_id", type="integer", example=1, description="ID of the estate"),
     *                 @OA\Property(property="photos", type="array", @OA\Items(type="string", format="binary"), description="Multiple photo files"),
     *                 @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", format="binary"), description="Multiple 3D model image files"),
     *                 @OA\Property(property="third_dimension_model_video", type="string", format="binary", description="3D model video file"),
     *                 @OA\Property(property="virtual_tour_video_url", type="string", format="uri", example="https://youtube.com/watch?v=xyz"),
     *                 @OA\Property(property="status", type="string", enum={"draft", "publish", "unpublish"}, example="publish")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Estate media uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Estate media created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="estate_id", type="integer", example=1),
     *                 @OA\Property(property="photos", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="third_dimension_model_video", type="string"),
     *                 @OA\Property(property="virtual_tour_video_url", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

     /**
     * @OA\Get(
     *     path="/api/v1/estate/estates/top-rated",
     *     tags={"Estate Management"},
     *     summary="Get top rated estates",
     *     description="Retrieve top 10 highest rated estates with media and plot details",
     *     @OA\Response(
     *         response=200,
     *         description="Top rated estates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Top rated estates retrieved successfully"),
     *             @OA\Property(property="total_count", type="integer", example=10),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Luxury Estate Gardens"),
     *                     @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                     @OA\Property(property="state", type="string", example="Lagos"),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="status", type="string", example="publish"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="amenities", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="media", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="photos", type="array", @OA\Items(type="string")),
     *                         @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string")),
     *                         @OA\Property(property="virtual_tour_video_url", type="string")
     *                     ),
     *                     @OA\Property(property="plot_detail", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="available_plot", type="integer", example=25),
     *                         @OA\Property(property="available_acre", type="number", format="float", example=12.5),
     *                         @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *                         @OA\Property(property="promotion_price", type="number", format="float", example=135000.00),
     *                         @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *                         @OA\Property(property="has_promotion", type="boolean", example=true),
     *                         @OA\Property(property="total_plot_value", type="number", format="float", example=3375000.00),
     *                         @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"))
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getTopRatedEstates()
    {
        try {
            // Get top 10 estates with highest ratings, including their media and plot details
            $topRatedEstates = Estate::with(['media', 'plotDetail'])
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

            // Transform the data to include media and plot detail information
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

                // Safely access plot detail relationship
                $plotDetail = null;
                if ($estate->relationLoaded('plotDetail') && $estate->plotDetail) {
                    $plotDetail = [
                        'id' => $estate->plotDetail->id,
                        'available_plot' => $estate->plotDetail->available_plot,
                        'available_acre' => $estate->plotDetail->available_acre,
                        'price_per_plot' => $estate->plotDetail->price_per_plot,
                        'percentage_increase' => $estate->plotDetail->percentage_increase,
                        'installment_plan' => $estate->plotDetail->installment_plan,
                        'promotion_price' => $estate->plotDetail->promotion_price,
                        'effective_price' => $estate->plotDetail->effective_price,
                        'has_promotion' => $estate->plotDetail->has_promotion,
                        'savings_amount' => $estate->plotDetail->savings_amount,
                        'total_plot_value' => $estate->plotDetail->total_plot_value,
                        'formatted_price' => $estate->plotDetail->formatted_price,
                        'formatted_promotion_price' => $estate->plotDetail->formatted_promotion_price,
                        'created_at' => $estate->plotDetail->created_at,
                        'updated_at' => $estate->plotDetail->updated_at,
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
                    'media' => $media,
                    'plot_detail' => $plotDetail
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

    /**
     * @OA\Get(
     *     path="/api/v1/estate/estates/top-rated-alt",
     *     tags={"Estate Management"},
     *     summary="Get top rated estates (Alternative method)",
     *     description="Alternative approach using joins for better performance with large datasets",
     *     @OA\Response(
     *         response=200,
     *         description="Top rated estates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Top rated estates retrieved successfully"),
     *             @OA\Property(property="total_count", type="integer", example=10),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Luxury Estate Gardens"),
     *                     @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                     @OA\Property(property="state", type="string", example="Lagos"),
     *                     @OA\Property(property="coordinates", type="string", example="6.4281,3.4219"),
     *                     @OA\Property(property="zoning", type="string", example="Residential"),
     *                     @OA\Property(property="size", type="string", example="500 sqm"),
     *                     @OA\Property(property="direction", type="string", example="North-facing"),
     *                     @OA\Property(property="description", type="string", example="Beautiful estate with modern amenities"),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="status", type="string", example="publish"),
     *                     @OA\Property(property="has_certificate_of_occupancy", type="boolean", example=true),
     *                     @OA\Property(property="amenities", type="array", @OA\Items(type="string"), example={"Swimming Pool", "Gym"}),
     *                     @OA\Property(property="map_background_image", type="string", format="uri", example="https://res.cloudinary.com/estates/map.jpg"),
     *                     @OA\Property(property="preview_display_image", type="string", format="uri", example="https://res.cloudinary.com/estates/preview.jpg"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="media",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="photos", type="array", @OA\Items(type="string", format="uri")),
     *                         @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", format="uri")),
     *                         @OA\Property(property="third_dimension_model_video", type="string", format="uri", nullable=true),
     *                         @OA\Property(property="virtual_tour_video_url", type="string", format="uri", nullable=true),
     *                         @OA\Property(property="status", type="string", example="publish"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     ),
     *                     @OA\Property(
     *                         property="plot_detail",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="available_plot", type="integer", example=25),
     *                         @OA\Property(property="available_acre", type="number", format="float", example=12.5),
     *                         @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *                         @OA\Property(property="percentage_increase", type="number", format="float", example=5.0),
     *                         @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *                         @OA\Property(property="promotion_price", type="number", format="float", nullable=true, example=135000.00),
     *                         @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *                         @OA\Property(property="has_promotion", type="boolean", example=true),
     *                         @OA\Property(property="savings_amount", type="number", format="float", example=15000.00),
     *                         @OA\Property(property="total_plot_value", type="number", format="float", example=3375000.00),
     *                         @OA\Property(property="formatted_price", type="string", example="150000.00"),
     *                         @OA\Property(property="formatted_promotion_price", type="string", nullable=true, example="135000.00"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

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
                'estate_media.updated_at as media_updated_at',
                'estate_plot_details.id as plot_detail_id',
                'estate_plot_details.available_plot',
                'estate_plot_details.available_acre',
                'estate_plot_details.price_per_plot',
                'estate_plot_details.percentage_increase',
                'estate_plot_details.installment_plan',
                'estate_plot_details.promotion_price',
                'estate_plot_details.created_at as plot_detail_created_at',
                'estate_plot_details.updated_at as plot_detail_updated_at'
            ])
            ->leftJoin('estate_media', 'estates.id', '=', 'estate_media.estate_id')
            ->leftJoin('estate_plot_details', 'estates.id', '=', 'estate_plot_details.estate_id')
            ->whereNotNull('estates.rating')
            ->where('estates.rating', '>', 0)
            ->where('estates.status', 'publish')
            ->orderBy('estates.rating', 'desc')
            ->orderBy('estates.created_at', 'desc')
            ->limit(10)
            ->get();

            $formattedEstates = $topRatedEstates->map(function ($estate) {
                // Calculate plot detail derived values
                $effectivePrice = $estate->promotion_price ?? $estate->price_per_plot;
                $hasPromotion = !is_null($estate->promotion_price) && $estate->promotion_price < $estate->price_per_plot;
                $savingsAmount = $hasPromotion ? ($estate->price_per_plot - $estate->promotion_price) : 0;
                $totalPlotValue = $estate->available_plot ? ($estate->available_plot * $effectivePrice) : 0;

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
                    ] : null,
                    'plot_detail' => $estate->plot_detail_id ? [
                        'id' => $estate->plot_detail_id,
                        'available_plot' => $estate->available_plot,
                        'available_acre' => $estate->available_acre,
                        'price_per_plot' => $estate->price_per_plot,
                        'percentage_increase' => $estate->percentage_increase,
                        'installment_plan' => $estate->installment_plan,
                        'promotion_price' => $estate->promotion_price,
                        'effective_price' => $effectivePrice,
                        'has_promotion' => $hasPromotion,
                        'savings_amount' => $savingsAmount,
                        'total_plot_value' => $totalPlotValue,
                        'formatted_price' => number_format($estate->price_per_plot, 2),
                        'formatted_promotion_price' => $estate->promotion_price ? number_format($estate->promotion_price, 2) : null,
                        'created_at' => $estate->plot_detail_created_at,
                        'updated_at' => $estate->plot_detail_updated_at,
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

   /**
     * @OA\Schema(
     *     schema="EstateWithAvailability",
     *     type="object",
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="title", type="string", example="Luxury Estate Gardens"),
     *     @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *     @OA\Property(property="state", type="string", example="Lagos"),
     *     @OA\Property(property="rating", type="integer", example=5),
     *     @OA\Property(property="status", type="string", example="publish"),
     *     @OA\Property(property="description", type="string", example="Beautiful estate with modern amenities"),
     *     @OA\Property(property="amenities", type="array", @OA\Items(type="string"), example={"Swimming Pool", "Gym"}),
     *     @OA\Property(property="preview_display_image", type="string", format="uri", example="https://res.cloudinary.com/estates/preview.jpg"),
     *     @OA\Property(
     *         property="media",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="photos", type="array", @OA\Items(type="string", format="uri")),
     *         @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", format="uri")),
     *         @OA\Property(property="third_dimension_model_video", type="string", format="uri", nullable=true),
     *         @OA\Property(property="virtual_tour_video_url", type="string", format="uri", nullable=true),
     *         @OA\Property(property="status", type="string", example="publish")
     *     ),
     *     @OA\Property(
     *         property="plot_detail",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="available_plot", type="integer", example=25),
     *         @OA\Property(property="available_acre", type="number", format="float", example=12.5),
     *         @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *         @OA\Property(property="percentage_increase", type="number", format="float", example=5.0),
     *         @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *         @OA\Property(property="promotion_price", type="number", format="float", nullable=true, example=135000.00),
     *         @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *         @OA\Property(property="has_promotion", type="boolean", example=true),
     *         @OA\Property(property="savings_amount", type="number", format="float", example=15000.00),
     *         @OA\Property(property="total_plot_value", type="number", format="float", example=3375000.00),
     *         @OA\Property(property="is_available", type="boolean", example=true),
     *         @OA\Property(property="availability_status", type="string", example="High")
     *     )
     * )
     */

    public function getTopRatedEstatesWithAvailability()
    {
        try {
            // Get top rated estates that have available plots
            $topRatedEstates = Estate::with(['media', 'plotDetail'])
                ->whereNotNull('rating')
                ->where('rating', '>', 0)
                ->where('status', 'publish')
                ->whereHas('plotDetail', function ($query) {
                    $query->where('available_plot', '>', 0);
                })
                ->orderBy('rating', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            if ($topRatedEstates->isEmpty()) {
                return response()->json([
                    'message' => 'No rated estates with available plots found',
                    'data' => [],
                    'total_count' => 0
                ], 200);
            }

            $formattedEstates = $topRatedEstates->map(function ($estate) {
                // Media data
                $media = null;
                if ($estate->relationLoaded('media') && $estate->media) {
                    $media = [
                        'id' => $estate->media->id,
                        'photos' => $estate->media->photos,
                        'third_dimension_model_images' => $estate->media->third_dimension_model_images,
                        'third_dimension_model_video' => $estate->media->third_dimension_model_video,
                        'virtual_tour_video_url' => $estate->media->virtual_tour_video_url,
                        'status' => $estate->media->status,
                    ];
                }

                // Plot detail data with availability focus
                $plotDetail = null;
                if ($estate->relationLoaded('plotDetail') && $estate->plotDetail) {
                    $plotDetail = [
                        'id' => $estate->plotDetail->id,
                        'available_plot' => $estate->plotDetail->available_plot,
                        'available_acre' => $estate->plotDetail->available_acre,
                        'price_per_plot' => $estate->plotDetail->price_per_plot,
                        'percentage_increase' => $estate->plotDetail->percentage_increase,
                        'installment_plan' => $estate->plotDetail->installment_plan,
                        'promotion_price' => $estate->plotDetail->promotion_price,
                        'effective_price' => $estate->plotDetail->effective_price,
                        'has_promotion' => $estate->plotDetail->has_promotion,
                        'savings_amount' => $estate->plotDetail->savings_amount,
                        'total_plot_value' => $estate->plotDetail->total_plot_value,
                        'is_available' => $estate->plotDetail->available_plot > 0,
                        'availability_status' => $estate->plotDetail->available_plot > 10 ? 'High' : 
                                            ($estate->plotDetail->available_plot > 5 ? 'Medium' : 'Limited'),
                    ];
                }

                return [
                    'id' => $estate->id,
                    'title' => $estate->title,
                    'town_or_city' => $estate->town_or_city,
                    'state' => $estate->state,
                    'rating' => $estate->rating,
                    'status' => $estate->status,
                    'description' => $estate->description,
                    'amenities' => $estate->amenities,
                    'preview_display_image' => $estate->preview_display_image,
                    'media' => $media,
                    'plot_detail' => $plotDetail
                ];
            });

            return response()->json([
                'message' => 'Top rated estates with available plots retrieved successfully',
                'data' => $formattedEstates,
                'total_count' => $formattedEstates->count(),
                'summary' => [
                    'total_available_plots' => $formattedEstates->sum('plot_detail.available_plot'),
                    'estates_with_promotions' => $formattedEstates->where('plot_detail.has_promotion', true)->count(),
                    'average_rating' => $formattedEstates->avg('rating'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving top rated estates with availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //ESTATE DETAIL API

    /**
     * @OA\Post(
     *     path="/api/v1/estate-plot-details/plot-detail",
     *     tags={"Plot Details"},
     *     summary="Create estate plot detail",
     *     description="Create detailed plot information for an estate",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"estate_id", "available_plot", "available_acre", "price_per_plot"},
     *             @OA\Property(property="estate_id", type="integer", example=1),
     *             @OA\Property(property="available_plot", type="integer", minimum=0, example=50),
     *             @OA\Property(property="available_acre", type="number", format="float", minimum=0, example=25.5),
     *             @OA\Property(property="price_per_plot", type="number", format="float", minimum=0, example=150000.00),
     *             @OA\Property(property="percentage_increase", type="number", format="float", minimum=0, maximum=100, example=5.5),
     *             @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *             @OA\Property(property="promotion_price", type="number", format="float", minimum=0, example=135000.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Estate plot detail created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Estate plot detail created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="plot_detail",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estate_id", type="integer", example=1),
     *                     @OA\Property(property="available_plot", type="integer", example=50),
     *                     @OA\Property(property="available_acre", type="number", format="float", example=25.5),
     *                     @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *                     @OA\Property(property="percentage_increase", type="number", format="float", example=5.5),
     *                     @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *                     @OA\Property(property="promotion_price", type="number", format="float", nullable=true, example=135000.00),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-19T11:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-19T11:00:00Z")
     *                 ),
     *                 @OA\Property(property="estate_name", type="string", example="Luxury Estate Gardens"),
     *                 @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *                 @OA\Property(property="has_promotion", type="boolean", example=true),
     *                 @OA\Property(property="total_plot_value", type="number", format="float", example=6750000.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    public function plot_detail(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'estate_id' => 'required|integer|exists:estates,id',
                'available_plot' => 'required|integer|min:0',
                'available_acre' => 'required|numeric|min:0',
                'price_per_plot' => 'required|numeric|min:0',
                'percentage_increase' => 'nullable|numeric|min:0|max:100',
                'installment_plan' => 'nullable|array',
                'installment_plan.*' => 'string',
                'promotion_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Additional business logic validation
            $validatedData = $validator->validated();
            
            // Check if promotion price is not higher than regular price
            if (isset($validatedData['promotion_price']) && 
                $validatedData['promotion_price'] >= $validatedData['price_per_plot']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promotion price must be lower than regular price',
                ], 422);
            }

            // Check if estate already has plot details (if you want only one per estate)
            $existingPlotDetail = EstatePlotDetail::where('estate_id', $validatedData['estate_id'])->first();
            if ($existingPlotDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plot details already exist for this estate. Use update instead.',
                ], 422);
            }

            DB::beginTransaction();

            // Create the plot detail
            $plotDetail = EstatePlotDetail::create([
                'estate_id' => $validatedData['estate_id'],
                'available_plot' => $validatedData['available_plot'],
                'available_acre' => $validatedData['available_acre'],
                'price_per_plot' => $validatedData['price_per_plot'],
                'percentage_increase' => $validatedData['percentage_increase'] ?? 0.00,
                'installment_plan' => $validatedData['installment_plan'] ?? null,
                'promotion_price' => $validatedData['promotion_price'] ?? null,
            ]);

            DB::commit();

            // Load the estate relationship
            $plotDetail->load('estate');

            return response()->json([
                'success' => true,
                'message' => 'Estate plot detail created successfully',
                'data' => [
                    'plot_detail' => $plotDetail,
                    'estate_name' => $plotDetail->estate->name,
                    'effective_price' => $plotDetail->effective_price,
                    'has_promotion' => $plotDetail->has_promotion,
                    'total_plot_value' => $plotDetail->total_plot_value,
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create estate plot detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     
   
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Find the plot detail
            $plotDetail = EstatePlotDetail::findOrFail($id);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'available_plot' => 'sometimes|integer|min:0',
                'available_acre' => 'sometimes|numeric|min:0',
                'price_per_plot' => 'sometimes|numeric|min:0',
                'percentage_increase' => 'sometimes|nullable|numeric|min:0|max:100',
                'installment_plan' => 'sometimes|nullable|array',
                'installment_plan.*' => 'string',
                'promotion_price' => 'sometimes|nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validatedData = $validator->validated();

            // Check promotion price validation if provided
            $pricePerPlot = $validatedData['price_per_plot'] ?? $plotDetail->price_per_plot;
            if (isset($validatedData['promotion_price']) && 
                $validatedData['promotion_price'] >= $pricePerPlot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promotion price must be lower than regular price',
                ], 422);
            }

            DB::beginTransaction();

            // Update the plot detail
            $plotDetail->update($validatedData);

            DB::commit();

            // Reload the model with relationships
            $plotDetail->load('estate');

            return response()->json([
                'success' => true,
                'message' => 'Estate plot detail updated successfully',
                'data' => [
                    'plot_detail' => $plotDetail,
                    'estate_name' => $plotDetail->estate->name,
                    'effective_price' => $plotDetail->effective_price,
                    'has_promotion' => $plotDetail->has_promotion,
                    'total_plot_value' => $plotDetail->total_plot_value,
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update estate plot detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
   
    public function getByEstate($estateId): JsonResponse
    {
        try {
            $plotDetail = EstatePlotDetail::with('estate')
                ->where('estate_id', $estateId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'plot_detail' => $plotDetail,
                    'estate_name' => $plotDetail->estate->name,
                    'effective_price' => $plotDetail->effective_price,
                    'has_promotion' => $plotDetail->has_promotion,
                    'total_plot_value' => $plotDetail->total_plot_value,
                    'savings_amount' => $plotDetail->savings_amount,
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Estate plot detail not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

   
    

    /**
     * @OA\Get(
     *     path="/api/v1/estate-plot-details/all",
     *     tags={"Plot Details"},
     *     summary="Get all plot details with filtering",
     *     description="Retrieve paginated list of all plot details with optional filtering",
     *     @OA\Parameter(
     *         name="has_available_plots",
     *         in="query",
     *         @OA\Schema(type="boolean"),
     *         description="Filter by availability"
     *     ),
     *     @OA\Parameter(
     *         name="estate_id",
     *         in="query",
     *         @OA\Schema(type="integer"),
     *         description="Filter by estate ID"
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         @OA\Schema(type="number", format="float"),
     *         description="Minimum price filter"
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         @OA\Schema(type="number", format="float"),
     *         description="Maximum price filter"
     *     ),
     *     @OA\Parameter(
     *         name="has_promotion",
     *         in="query",
     *         @OA\Schema(type="boolean"),
     *         description="Filter by promotion availability"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         @OA\Schema(type="integer", default=15),
     *         description="Number of items per page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plot details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="estate_id", type="integer", example=1),
     *                         @OA\Property(property="estate_name", type="string", example="Luxury Estate Gardens"),
     *                         @OA\Property(property="available_plot", type="integer", example=50),
     *                         @OA\Property(property="available_acre", type="number", format="float", example=25.5),
     *                         @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *                         @OA\Property(property="percentage_increase", type="number", format="float", example=5.5),
     *                         @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *                         @OA\Property(property="promotion_price", type="number", format="float", nullable=true, example=135000.00),
     *                         @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *                         @OA\Property(property="has_promotion", type="boolean", example=true),
     *                         @OA\Property(property="total_plot_value", type="number", format="float", example=6750000.00),
     *                         @OA\Property(property="savings_amount", type="number", format="float", example=15000.00),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-19T11:00:00Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-19T11:00:00Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="last_page", type="integer", example=4)
     *             )
     *         )
     *     )
     * )
     */

    public function index(Request $request): JsonResponse
    {
        try {
            $query = EstatePlotDetail::with('estate');

            // Apply filters
            if ($request->has('has_available_plots') && $request->has_available_plots) {
                $query->hasAvailablePlots();
            }

            if ($request->has('estate_id')) {
                $query->byEstate($request->estate_id);
            }

            if ($request->has('min_price')) {
                $query->where('price_per_plot', '>=', $request->min_price);
            }

            if ($request->has('max_price')) {
                $query->where('price_per_plot', '<=', $request->max_price);
            }

            if ($request->has('has_promotion') && $request->has_promotion) {
                $query->whereNotNull('promotion_price');
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $plotDetails = $query->paginate($perPage);

            // Transform the data
            $plotDetails->getCollection()->transform(function ($plotDetail) {
                return [
                    'id' => $plotDetail->id,
                    'estate_id' => $plotDetail->estate_id,
                    'estate_name' => $plotDetail->estate->name,
                    'available_plot' => $plotDetail->available_plot,
                    'available_acre' => $plotDetail->available_acre,
                    'price_per_plot' => $plotDetail->price_per_plot,
                    'percentage_increase' => $plotDetail->percentage_increase,
                    'installment_plan' => $plotDetail->installment_plan,
                    'promotion_price' => $plotDetail->promotion_price,
                    'effective_price' => $plotDetail->effective_price,
                    'has_promotion' => $plotDetail->has_promotion,
                    'total_plot_value' => $plotDetail->total_plot_value,
                    'savings_amount' => $plotDetail->savings_amount,
                    'created_at' => $plotDetail->created_at,
                    'updated_at' => $plotDetail->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $plotDetails
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve estate plot details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     
    public function destroy($id): JsonResponse
    {
        try {
            $plotDetail = EstatePlotDetail::findOrFail($id);
            
            DB::beginTransaction();
            
            $plotDetail->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estate plot detail deleted successfully'
            ], 200);

        } catch (Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete estate plot detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  
    /**
     * @OA\Post(
     *     path="/api/v1/estate/estates/nearby",
     *     tags={"Estate Management"},
     *     summary="Get nearby estates based on user coordinates",
     *     description="Retrieve a list of estates within a specified radius of the user's latitude and longitude",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "latitude", "longitude"},
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID of the user"),
     *             @OA\Property(property="latitude", type="number", format="float", example=6.4281, description="User's latitude"),
     *             @OA\Property(property="longitude", type="number", format="float", example=3.4219, description="User's longitude"),
     *             @OA\Property(property="radius", type="number", format="float", example=10, description="Search radius in kilometers", minimum=1, maximum=100)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Nearby estates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Nearby estates retrieved successfully"),
     *             @OA\Property(property="total_count", type="integer", example=5),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Luxury Estate Gardens"),
     *                     @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                     @OA\Property(property="state", type="string", example="Lagos"),
     *                     @OA\Property(property="coordinates", type="string", example="6.4281,3.4219"),
     *                     @OA\Property(property="distance_km", type="number", format="float", example=2.5, description="Distance from user location in kilometers"),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="status", type="string", example="publish"),
     *                     @OA\Property(property="description", type="string", example="Beautiful estate with modern amenities"),
     *                     @OA\Property(property="amenities", type="array", @OA\Items(type="string"), example={"Swimming Pool", "Gym"}),
     *                     @OA\Property(property="map_background_image", type="string", format="uri", example="https://res.cloudinary.com/estates/map.jpg"),
     *                     @OA\Property(property="preview_display_image", type="string", format="uri", example="https://res.cloudinary.com/estates/preview.jpg"),
     *                     @OA\Property(
     *                         property="media",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="photos", type="array", @OA\Items(type="string", format="uri")),
     *                         @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", format="uri")),
     *                         @OA\Property(property="third_dimension_model_video", type="string", format="uri", nullable=true),
     *                         @OA\Property(property="virtual_tour_video_url", type="string", format="uri", nullable=true),
     *                         @OA\Property(property="status", type="string", example="publish"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     ),
     *                     @OA\Property(
     *                         property="plot_detail",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="available_plot", type="integer", example=25),
     *                         @OA\Property(property="available_acre", type="number", format="float", example=12.5),
     *                         @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *                         @OA\Property(property="percentage_increase", type="number", format="float", example=5.0),
     *                         @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *                         @OA\Property(property="promotion_price", type="number", format="float", nullable=true, example=135000.00),
     *                         @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *                         @OA\Property(property="has_promotion", type="boolean", example=true),
     *                         @OA\Property(property="savings_amount", type="number", format="float", example=15000.00),
     *                         @OA\Property(property="total_plot_value", type="number", format="float", example=3375000.00),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getNearbyEstates(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = \Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:1|max:100', // Radius in kilometers
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = $request->input('user_id');
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radius = $request->input('radius', 10); // Default radius: 10km

            // Query estates with their media and plot details
            $estates = Estate::with(['media', 'plotDetail'])
                ->where('status', 'publish')
                ->get()
                ->filter(function ($estate) use ($latitude, $longitude, $radius) {
                    // Parse estate coordinates
                    if (!$estate->cordinates) {
                        return false;
                    }

                    [$estateLat, $estateLng] = explode(',', $estate->cordinates);

                    // Calculate distance using Haversine formula
                    $distance = $this->haversineDistance(
                        $latitude,
                        $longitude,
                        (float) $estateLat,
                        (float) $estateLng
                    );

                    // Include estates within the specified radius
                    return $distance <= $radius;
                })
                ->map(function ($estate) use ($latitude, $longitude) {
                    // Parse estate coordinates for distance calculation
                    [$estateLat, $estateLng] = explode(',', $estate->cordinates);

                    // Calculate distance
                    $distance = $this->haversineDistance(
                        $latitude,
                        $longitude,
                        (float) $estateLat,
                        (float) $estateLng
                    );

                    // Format media data
                    $media = $estate->media ? [
                        'id' => $estate->media->id,
                        'photos' => $estate->media->photos,
                        'third_dimension_model_images' => $estate->media->third_dimension_model_images,
                        'third_dimension_model_video' => $estate->media->third_dimension_model_video,
                        'virtual_tour_video_url' => $estate->media->virtual_tour_video_url,
                        'status' => $estate->media->status,
                        'created_at' => $estate->media->created_at,
                        'updated_at' => $estate->media->updated_at,
                    ] : null;

                    // Format plot detail data
                    $plotDetail = $estate->plotDetail ? [
                        'id' => $estate->plotDetail->id,
                        'available_plot' => $estate->plotDetail->available_plot,
                        'available_acre' => $estate->plotDetail->available_acre,
                        'price_per_plot' => $estate->plotDetail->price_per_plot,
                        'percentage_increase' => $estate->plotDetail->percentage_increase,
                        'installment_plan' => $estate->plotDetail->installment_plan,
                        'promotion_price' => $estate->plotDetail->promotion_price,
                        'effective_price' => $estate->plotDetail->effective_price,
                        'has_promotion' => $estate->plotDetail->has_promotion,
                        'savings_amount' => $estate->plotDetail->savings_amount,
                        'total_plot_value' => $estate->plotDetail->total_plot_value,
                        'created_at' => $estate->plotDetail->created_at,
                        'updated_at' => $estate->plotDetail->updated_at,
                    ] : null;

                    return [
                        'id' => $estate->id,
                        'title' => $estate->title,
                        'town_or_city' => $estate->town_or_city,
                        'state' => $estate->state,
                        'coordinates' => $estate->cordinates,
                        'distance_km' => round($distance, 2),
                        'rating' => $estate->rating,
                        'status' => $estate->status,
                        'description' => $estate->description,
                        'amenities' => $estate->amenities,
                        'map_background_image' => $estate->map_background_image,
                        'preview_display_image' => $estate->preview_display_image,
                        'media' => $media,
                        'plot_detail' => $plotDetail,
                    ];
                })
                ->sortBy('distance_km') // Sort by distance (closest first)
                ->values(); // Reset collection keys

            if ($estates->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No estates found within the specified radius',
                    'total_count' => 0,
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nearby estates retrieved successfully',
                'total_count' => $estates->count(),
                'data' => $estates,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving nearby estates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate the distance between two coordinates using the Haversine formula.
     *
     * @param float $lat1 User's latitude
     * @param float $lon1 User's longitude
     * @param float $lat2 Estate's latitude
     * @param float $lon2 Estate's longitude
     * @return float Distance in kilometers
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Distance in kilometers
    }
    /**
     * @OA\Post(
     *     path="/api/v1/estate/estates/search",
     *     tags={"Estate Management"},
     *     summary="Search and filter estates based on specified criteria",
     *     description="Retrieve a paginated list of estates filtered by minimum price, maximum price, state, town or city, and amenities",
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="min_price", type="number", format="float", example=100000, description="Minimum price per plot", nullable=true),
     *             @OA\Property(property="max_price", type="number", format="float", example=500000, description="Maximum price per plot", nullable=true),
     *             @OA\Property(property="state", type="string", example="Lagos", description="State where the estate is located", nullable=true),
     *             @OA\Property(property="town_or_city", type="string", example="Lekki", description="Town or city where the estate is located", nullable=true),
     *             @OA\Property(property="amenities", type="string", example="Swimming Pool,Gym", description="Comma-separated list of amenities", nullable=true),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Number of results per page", nullable=true, minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estates filtered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Estates filtered successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Luxury Estate Gardens"),
     *                     @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                     @OA\Property(property="state", type="string", example="Lagos"),
     *                     @OA\Property(property="coordinates", type="string", example="6.4281,3.4219"),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="status", type="string", example="publish"),
     *                     @OA\Property(property="description", type="string", example="Beautiful estate with modern amenities"),
     *                     @OA\Property(property="amenities", type="array", @OA\Items(type="string"), example={"Swimming Pool", "Gym"}),
     *                     @OA\Property(property="map_background_image", type="string", format="uri", example="https://res.cloudinary.com/estates/map.jpg"),
     *                     @OA\Property(property="preview_display_image", type="string", format="uri", example="https://res.cloudinary.com/estates/preview.jpg"),
     *                     @OA\Property(
     *                         property="media",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="photos", type="array", @OA\Items(type="string", format="uri")),
     *                         @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", format="uri")),
     *                         @OA\Property(property="third_dimension_model_video", type="string", format="uri", nullable=true),
     *                         @OA\Property(property="virtual_tour_video_url", type="string", format="uri", nullable=true),
     *                         @OA\Property(property="status", type="string", example="publish")
     *                     ),
     *                     @OA\Property(
     *                         property="plot_detail",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="available_plot", type="integer", example=25),
     *                         @OA\Property(property="available_acre", type="number", format="float", example=12.5),
     *                         @OA\Property(property="price_per_plot", type="number", format="float", example=150000.00),
     *                         @OA\Property(property="percentage_increase", type="number", format="float", example=5.0),
     *                         @OA\Property(property="installment_plan", type="array", @OA\Items(type="string"), example={"12 months", "6 months"}),
     *                         @OA\Property(property="promotion_price", type="number", format="float", nullable=true, example=135000.00),
     *                         @OA\Property(property="effective_price", type="number", format="float", example=135000.00),
     *                         @OA\Property(property="has_promotion", type="boolean", example=true),
     *                         @OA\Property(property="savings_amount", type="number", format="float", example=15000.00),
     *                         @OA\Property(property="total_plot_value", type="number", format="float", example=3375000.00),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 )),
     *                 @OA\Property(property="first_page_url", type="string", example="https://api.stephenakintayotv.com/api/v1/estate/estates/search?page=1"),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="last_page_url", type="string", example="https://api.stephenakintayotv.com/api/v1/estate/estates/search?page=5"),
     *                 @OA\Property(property="links", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true, example="https://api.stephenakintayotv.com/api/v1/estate/estates/search?page=2"),
     *                 @OA\Property(property="path", type="string", example="https://api.stephenakintayotv.com/api/v1/estate/estates/search"),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="to", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=75)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to filter and search estates"),
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */

    public function filterSearch(Request $request): JsonResponse
    {
        try {
            $query = Estate::with(['media', 'plotDetail'])->where('status', 'publish');

            // Apply filters based on request parameters
            if ($request->has('min_price')) {
                $query->whereHas('plotDetail', function ($q) use ($request) {
                    $q->where('price_per_plot', '>=', $request->min_price);
                });
            }

            if ($request->has('max_price')) {
                $query->whereHas('plotDetail', function ($q) use ($request) {
                    $q->where('price_per_plot', '<=', $request->max_price);
                });
            }

            if ($request->has('state')) {
                $query->where('state', $request->state);
            }

            if ($request->has('town_or_city')) {
                $query->where('town_or_city', $request->town_or_city);
            }

            if ($request->has('amenities')) {
                $amenities = explode(',', $request->amenities);
                foreach ($amenities as $amenity) {
                    $query->whereJsonContains('amenities', trim($amenity));
                }
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $estates = $query->paginate($perPage);

            // Transform the data
            $estates->getCollection()->transform(function ($estate) {
                return [
                    'id' => $estate->id,
                    'title' => $estate->title,
                    'town_or_city' => $estate->town_or_city,
                    'state' => $estate->state,
                    'coordinates' => $estate->coordinates, // fixed typo
                    'rating' => $estate->rating,
                    'status' => $estate->status,
                    'description' => $estate->description,
                    'amenities' => $estate->amenities,
                    'map_background_image' => $estate->map_background_image,
                    'preview_display_image' => $estate->preview_display_image,
                    'media' => $estate->media ? [
                        'id' => $estate->media->id,
                        'photos' => $estate->media->photos,
                        'third_dimension_model_images' => $estate->media->third_dimension_model_images,
                        'third_dimension_model_video' => $estate->media->third_dimension_model_video,
                        'virtual_tour_video_url' => $estate->media->virtual_tour_video_url,
                        'status' => $estate->media->status,
                    ] : null,
                    'plot_detail' => $estate->plotDetail,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Estates filtered successfully',
                'data' => $estates
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to filter and search estates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
