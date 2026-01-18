<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class GeoJsonController extends Controller
{
    public function updateLayoutFeature(Request $request,$estate, $id)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:SOLD,NOT SOLD'],
            'price' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ]);
        $estateSafe = rawurldecode($estate);
        $absPath = storage_path("app/private/geojson/{$estateSafe}/layout.geojson");

        if (!File::exists($absPath)) {
            return response()->json([
                'message' => 'Layout file not found',
                'checked' => $absPath,
            ], 404);
        }

        $json = json_decode(File::get($absPath), true);

        if (!$json || !isset($json['features']) || !is_array($json['features'])) {
            return response()->json(['message' => 'Invalid layout GeoJSON'], 500);
        }

        $found = false;

        foreach ($json['features'] as &$feature) {
            $fid = $feature['properties']['Id'] ?? null;

            if ($fid !== null && (int)$fid === (int)$id) {
                $feature['properties']['Status'] = $data['status'];
                $feature['properties']['Price'] = $data['price']; // number or null
                $feature['properties']['Description'] = $data['description'] ?? null;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json(['message' => 'Plot not found'], 404);
        }

        File::put($absPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json(['message' => 'Updated', 'id' => (int)$id]);
    }
}
