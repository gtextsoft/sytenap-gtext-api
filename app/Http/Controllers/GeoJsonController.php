<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class GeoJsonController extends Controller
{
    public function getLayout(int $estateId){
        $rows = DB::table('plots')
            ->leftJoin('estate_plot_details', 'estate_plot_details.estate_id', '=', 'plots.estate_id')
            ->leftJoin('plot_purchases', function ($join) {
                $join->on('plot_purchases.estate_id', '=', 'plots.estate_id')
                    ->whereRaw("
                        CONCAT(',', REPLACE(REPLACE(plot_purchases.plots, '[', ''), ']', ''), ',')
                        LIKE CONCAT('%,', plots.id, ',%')
                    ");
            })
            ->where('plots.estate_id', $estateId)
            ->selectRaw("
                plots.*,
                plot_purchases.user_id AS owner_id,
                estate_plot_details.price_per_plot AS price,
                ST_AsGeoJSON(plots.geom) AS geom_geojson
            ")
            ->get();

        $features = $rows->map(function ($r) {
            $props = (array) $r;

            // remove geometry fields from properties
            unset($props['geom']);
            $geom = json_decode($props['geom_geojson'] ?? 'null', true);
            unset($props['geom_geojson']);

            return [
                "type" => "Feature",
                "geometry" => $geom,
                "properties" => $props,
            ];
        });

        return response()->json([
            "type" => "FeatureCollection",
            "name" => "Layout",
            "features" => $features,
        ]);
    }



    public function getBoundary(?int $estateId = null){
         // normal single-estate boundary
        $estateId = (int) $estateId;

        $row = DB::table('estates')
            ->where('id', $estateId)
            ->whereNotNull('geom') // only return if geometry exists
            ->selectRaw('ST_AsGeoJSON(geom) AS geom_geojson')
            ->first();

        if (!$row) {
            return response()->json([
                "type" => "FeatureCollection",
                "name" => "Boundary",
                "features" => [],
            ]);
        }

        return response()->json([
            "type" => "FeatureCollection",
            "name" => "Boundary",
            "features" => [[
                "type" => "Feature",
                "geometry" => json_decode($row->geom_geojson, true),
                "properties" => new \stdClass(), // {}
            ]],
        ]);
    }





    public function allLayout(){
        $rows = DB::table('plots')
            ->leftJoin('estate_plot_details', 'estate_plot_details.estate_id', '=', 'plots.estate_id')
            ->leftJoin('plot_purchases', function ($join) {
                $join->on('plot_purchases.estate_id', '=', 'plots.estate_id')
                    ->whereRaw("
                        CONCAT(',', REPLACE(REPLACE(plot_purchases.plots, '[', ''), ']', ''), ',')
                        LIKE CONCAT('%,', plots.id, ',%')
                    ");
            })
            ->selectRaw("
                plots.*,
                plot_purchases.user_id AS owner_id,
                estate_plot_details.price_per_plot AS price,
                ST_AsGeoJSON(plots.geom) AS geom_geojson
            ")
            ->get();

        $features = $rows->map(function ($r) {
            $props = (array) $r;

            // remove geometry fields from properties
            unset($props['geom']);
            $geom = json_decode($props['geom_geojson'] ?? 'null', true);
            unset($props['geom_geojson']);

            return [
                "type" => "Feature",
                "geometry" => $geom,
                "properties" => $props,
            ];
        });

        return response()->json([
            "type" => "FeatureCollection",
            "name" => "Layout",
            "features" => $features,
        ]);
    }



    public function allBoundary(){
        $rows = DB::table('estates')
            ->whereNotNull('geom')
            ->selectRaw('id, title,town_or_city,state, cordinates, ST_AsGeoJSON(geom) AS geom_geojson')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                "type" => "FeatureCollection",
                "name" => "Boundary",
                "features" => [],
            ]);
        }

        $features = $rows->map(function ($r) {
            return [
                "type" => "Feature",
                "geometry" => json_decode($r->geom_geojson ?? 'null', true),
                "properties" => [
                    "estate_id" => $r->id,
                    "title" => $r->title,
                    "town_or_city" => $r->town_or_city,
                    "state" => $r->state,
                    "coordinates" => $r->cordinates,
                ],
            ];
        });

        return response()->json([
            "type" => "FeatureCollection",
            "name" => "Boundary",
            "features" => $features,
        ]);
    }






    public function updateLayoutFeature(Request $request, $estate, $id){
        $data = $request->validate([
            'status' => ['required', 'string', 'in:available,allocated,sold'],
            'price' => ['nullable', 'numeric']
        ]);

        $estateId = (int) $estate;
        $plotId   = (int) $id;

        // Update the DB row
        $updated = DB::table('plots') // 
            ->where('estate_id', $estateId)
            ->where('id', $plotId)
            ->update([
                // match your existing GeoJSON properties naming
                'status' => $data['status'],
                'Price' => $data['price'], // numeric or null
                'updated_at' => now(), // remove if your table doesn't have timestamps
            ]);

        if ($updated === 0) {
            return response()->json(['message' => 'Plot not found'], 404);
        }

        return response()->json(['message' => 'Updated', 'id' => $plotId]);
    }
}
