<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExternalUserController extends Controller
{
    public function index(Request $request)
    {
        // collect any filters or pagination passed from frontend
        $query = $request->all();

        // automatically create full URL like: http://localhost:8000/api/users.php
        $apiUrl = url('/users.php');

        // make the GET request to your PHP API
        $response = Http::get($apiUrl, $query);

        if ($response->failed()) {
            abort(502, 'Failed to fetch users from external API.');
        }

        $data = $response->json();

        // pass the API data to the view
        return view('external-users', [
            'users' => $data['data'] ?? [],
            'meta'  => $data['meta'] ?? []
        ]);
    }
}
