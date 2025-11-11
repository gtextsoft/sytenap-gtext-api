<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    // Fetch documents for logged-in user with pagination
    public function index(Request $request)
    {
        $user = Auth::user();

    
        $documents = Document::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        

        return response()->json($documents);
    }
}
