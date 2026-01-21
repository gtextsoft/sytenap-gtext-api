<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class GeoJsonController extends Controller
{
    public function getLayout(int $estateId)
    {
        $rows = DB::table('plots')   // <-- change to your real table name
            ->where('estate_id', $estateId)
            ->selectRaw("*, ST_AsGeoJSON(geom) AS geom_geojson")
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



    public function getBoundary(int $estateId)
    {
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






    public function updateLayoutFeature(Request $request, $estate, $id)
    {
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
