<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Estate;
use App\Models\Plot;

class PlotController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/estate/{estateId}/generate-plots",
     *     tags={"Plot Management"},
     *     summary="Generate plots for an estate",
     *     description="Generate plots for a given estate based on its available plots. 
     *                  Each plot will be assigned a unique plot_id, coordinates, and availability status.",
     *     @OA\Parameter(
     *         name="estateId",
     *         in="path",
     *         required=true,
     *         description="The ID of the estate to generate plots for",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plots generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Generated 50 plots for estate: Beryl Estate Lagos"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estate_id", type="integer", example=1),
     *                     @OA\Property(property="plot_id", type="string", example="BERYL-001"),
     *                     @OA\Property(property="coordinate", type="string", example="6.5119104, 3.6348072"),
     *                     @OA\Property(property="status", type="string", enum={"available", "sold", "reserved"}, example="available"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No available plots to generate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No available plots to generate.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Estate not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [Estate] 99")
     *         )
     *     )
     * )
     */
    public function generatePlots($estateId)
    {
        $estate = Estate::with('plotDetail')->findOrFail($estateId);

        // Number of plots to generate comes from available_plot
        $availablePlots = $estate->plotDetail->available_plot ?? 0;

        if ($availablePlots <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No available plots to generate.'
            ], 400);
        }

        // Estate size (e.g. "500sqm") -> use to guide spacing
        $estateSize = (int) filter_var($estate->size, FILTER_SANITIZE_NUMBER_INT);
        $plotSize   = 500; // sqm per plot

        // how many meters per plot roughly (square root of sqm gives length of side)
        $plotSideMeters = sqrt($plotSize); // ~22.36m
        // convert to degrees offset (approx: 1 deg lat ~ 111,000m)
        $latOffset = $plotSideMeters / 111000; // north-south
        $lngOffset = $plotSideMeters / 111000; // east-west

        // base coordinate
        [$baseLat, $baseLng] = array_map('floatval', explode(',', $estate->cordinates));

        $plotsPerRow = ceil(sqrt($availablePlots)); // make grid square-ish
        $prefix = strtoupper(substr(str_replace(' ', '', $estate->title), 0, 5)); // e.g. BERYL

        $createdPlots = [];

        for ($i = 1; $i <= $availablePlots; $i++) {
            $row = floor(($i - 1) / $plotsPerRow);
            $col = ($i - 1) % $plotsPerRow;

            $lat = $baseLat + ($row * $latOffset);
            $lng = $baseLng + ($col * $lngOffset);

            $plotId = $prefix . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);

            $plot = Plot::create([
                'estate_id'  => $estate->id,
                'plot_id'    => $plotId,
                'coordinate' => "{$lat}, {$lng}",
                'status'     => 'available'
            ]);

            $createdPlots[] = $plot;
        }

        return response()->json([
            'success' => true,
            'message' => "Generated {$availablePlots} plots for estate: {$estate->title}",
            'data'    => $createdPlots
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/estate/{estateId}/plots",
     *     tags={"Plot Management"},
     *     summary="Get all plots for an estate",
     *     description="Retrieve all plots belonging to a specific estate by estate ID",
     *     @OA\Parameter(
     *         name="estateId",
     *         in="path",
     *         required=true,
     *         description="The ID of the estate",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of plots retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="estate", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Beryl Estate Lagos"),
     *                 @OA\Property(property="size", type="string", example="500sqm"),
     *                 @OA\Property(property="town_or_city", type="string", example="Ibeju-Lekki"),
     *                 @OA\Property(property="state", type="string", example="Lagos")
     *             ),
     *             @OA\Property(
     *                 property="plots",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="plot_id", type="string", example="BERYL-001"),
     *                     @OA\Property(property="coordinate", type="string", example="6.5119104, 3.6348072"),
     *                     @OA\Property(property="status", type="string", enum={"available", "sold", "reserved"}, example="available"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Estate not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Estate not found")
     *         )
     *     )
     * )
     */
    public function getPlotsByEstate($estateId)
    {
        $estate = Estate::find($estateId);

        if (!$estate) {
            return response()->json([
                'success' => false,
                'message' => 'Estate not found'
            ], 404);
        }

        $plots = $estate->plots; // relationship in Estate model: hasMany(Plot::class)

        return response()->json([
            'success' => true,
            'estate' => [
                'id' => $estate->id,
                'title' => $estate->title,
                'size' => $estate->size,
                'town_or_city' => $estate->town_or_city,
                'state' => $estate->state,
            ],
            'plots' => $plots
        ]);
    }

}
