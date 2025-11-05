<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class DocumentController extends Controller {
    // Admin uploads a PDF for a specific client

    public function store( Request $request ) {
        $admin = $request->user();

        // Check admin privilege
        if ( $admin->account_type !== 'admin' ) {
            return response()->json( [
                'success' => false,
                'message' => 'Access denied. Only admins can upload documents.'
            ], 403 );
        }

        $validated = $request->validate( [
            'user_id' => 'required|exists:users,id', // client
            'title' => 'required|string|max:255',
            'document_type' => 'nullable|string|max:100',
            'file' => 'required|mimes:pdf|max:5120',
            'plot_id' => 'nullable|exists:plots,id',
            'estate_id' => 'nullable|exists:estates,id',
        ] );

        // Upload file to Cloudinary
        $uploadResult = Cloudinary::uploadApi()->upload(
            $request->file( 'file' )->getRealPath(),
            [
                'folder' => 'client_documents',
                'resource_type' => 'raw',
            ]
        );

        // Save in DB
        $document = Document::create( [
            'uploaded_by' => $admin->id,
            'user_id' => $validated[ 'user_id' ], // client
            'plot_id' => $validated[ 'plot_id' ] ?? null,
            'estate_id' => $validated[ 'estate_id' ] ?? null,
            'title' => $validated[ 'title' ],
            'document_type' => $validated[ 'document_type' ] ?? 'pdf',
            'file_url' => $uploadResult[ 'secure_url' ],
        ] );

        return response()->json( [
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'document' => $document,
        ], 201 );
    }
}
