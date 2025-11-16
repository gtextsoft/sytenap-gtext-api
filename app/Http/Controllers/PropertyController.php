<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Property;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        // === Search ===
        $search = $request->input('search');

        // === Sort ===
        $sortBy = $request->input('sort_by', 'created_at'); // default sorting field
        $sortOrder = $request->input('sort_order', 'desc'); // asc or desc

        // === Pagination ===
        $perPage = $request->input('per_page', 10);

        // === Filtering === (optional)
        $type = $request->input('type');        // e.g. "rent"
        $status = $request->input('status');    // e.g. "available"
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        $query = Property::query();

        // --- Search by title or description ---
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }

        // --- Filters ---
        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }

        // --- Sorting ---
        $query->orderBy($sortBy, $sortOrder);

        // --- Pagination ---
        $properties = $query->paginate($perPage);

        // === RESPONSE ===
        return response()->json([
            'success' => true,
            'data' => $properties
        ]);
    }
}
