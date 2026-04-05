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
                COALESCE(NULLIF(plots.price, 0), estate_plot_details.price_per_plot) AS Price,
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

        $r = DB::table('estates')
            ->where('estates.id', $estateId)
            ->whereNotNull('estates.geom') // only return if geometry exists
            ->leftJoin('estate_plot_details', 'estate_plot_details.estate_id', '=', 'estates.id')
            ->selectRaw('
            estates.id,
            estates.title,
            estates.size,
            estates.cordinates AS coordinates,
            estate_plot_details.available_plot,
            estate_plot_details.price_per_plot,
            ST_AsGeoJSON(estates.geom) AS geom_geojson
        ')
            ->first();

        if (!$r) {
            return response()->json([
                "type" => "FeatureCollection",
                "name" => "Boundary",
                "features" => [],
            ]);
        }


        $features =[
                "type" => "Feature",
                "geometry" => json_decode($r->geom_geojson ?? 'null', true),
                "properties" => [
                "id" => $r->id,
                "title" => $r->title,
                "size" => $r->size,
                "available_plot" => $r->available_plot,
                "price_per_plot" => $r->price_per_plot,

                "coordinates" => $r->coordinates,
            ],
            ];


        return response()->json([
            "type" => "FeatureCollection",
            "name" => "Boundary",
            "features" => [$features]
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
            ->whereNotNull('estates.geom')
        ->leftJoin('estate_plot_details', 'estate_plot_details.estate_id', '=', 'estates.id')
        ->selectRaw('
            estates.id,
            estates.title,
            estates.town_or_city,
            estates.state,
            estates.size,
            estates.direction,
            estates.description,
            estates.cordinates AS coordinates,
            estate_plot_details.available_plot,
            estate_plot_details.price_per_plot,
            ST_AsGeoJSON(estates.geom) AS geom_geojson
        ')
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

                "size" => $r->size,
                "direction" => $r->direction,
                "description" => $r->description,

                "available_plot" => $r->available_plot,
                "price_per_plot" => $r->price_per_plot,

                "coordinates" => $r->coordinates,
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
            'price' => ['nullable', 'numeric'],
            'block'  => ['nullable', 'string', 'max:50'],
            'plot'   => ['nullable', 'string', 'max:50'],
        ]);

        $estateId = (int) $estate;
        $plotId   = (int) $id;

        // Update the DB row
        $updated = DB::table('plots') // 
            ->where('estate_id', $estateId)
            ->where('id', $plotId)
            ->update([
                'status' => $data['status'],
                'Price' => $data['price'] ?? null,
                'Block'  => $data['block'] ?? null,
                'Plot'   => $data['plot'] ?? null,

                'plot_id' => DB::raw('CONCAT(
                    SUBSTRING_INDEX(plot_id, ":", 1),
                    ": ",
                    IF(`Block` IS NULL OR `Block` = "", "", CONCAT(`Block`, "-")),
                    `Plot`
                )'),


                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['message' => 'Plot not found'], 404);
        }

        return response()->json(['message' => 'Updated', 'id' => $plotId]);
    }
}
