<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Document;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class DocumentController extends Controller {
    // Admin uploads a PDF for a specific client

    public function store( Request $request ) {
        $request->validate( [
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'file' => 'required|mimes:pdf',
        ] );

        // Upload file to Cloudinary
        if ( $request->hasFile( 'file' ) ) {
            $uploadResult = Cloudinary::uploadApi()->upload(
                $request->file( 'file' )->getRealPath(),
                [
                    'folder' => 'client_documents',
                    'resource_type' => 'raw'
                ]
            );
            $data[ 'file_url' ] = $uploadResult[ 'secure_url' ];
        }

        // Save in DB
        $document = Document::create( [
            'client_id' => $data[ 'client_id' ],
            'title' => $data[ 'title' ],
            'file_url' => $data[ 'file_url' ],
        ] );

        return response()->json( [
            'message' => 'Document uploaded successfully.',
            'document' => $document,
        ], 201 );
    }
}
