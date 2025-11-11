<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    // Admin: upload a PDF document
    public function upload(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|mimes:pdf|max:5120', // 5MB limit
        ]);

        $file = $request->file('file');
        $filename = Str::random(12) . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents', $filename, 'public');

        $uploadedBy = $request->user() ? $request->user()->id : null;

        $document = Document::create([
            'title' => $request->input('title'),
            'file_path' => $path,
            'uploaded_by' => $uploadedBy,
            'published' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Document uploaded successfully',
            'document' => $document,
        ], 201);
    }

    // Admin: publish document
    public function publish($id)
    {
        $document = Document::findOrFail($id);
        $document->published = true;
        $document->save();

        return response()->json(['status' => true, 'message' => 'Document published successfully']);
    }

    // Admin: unpublish document
    public function unpublish($id)
    {
        $document = Document::findOrFail($id);
        $document->published = false;
        $document->save();

        return response()->json(['status' => true, 'message' => 'Document unpublished successfully']);
    }

    // Public: view published documents

    /**
 * @OA\Put(
 *     path="/api/v1/admin/documents/{id}/publish",
 *     summary="Publish a document",
 *     description="Mark a document as published",
 *     tags={"Documents"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Document ID",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Document published successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property
 * **/

    public function index()
    {
        $documents = Document::where('published', true)
            ->select('id', 'title', 'file_path', 'created_at')
            ->latest()
            ->get();

        $documents->transform(function ($doc) {
            $doc->url = asset('storage/' . $doc->file_path);
            return $doc;
        });

        return response()->json([
            'status' => true,
            'documents' => $documents,
        ]);
    }

    // Public: download document
    public function download($id)
    {
        $document = Document::findOrFail($id);

        if (! $document->published) {
            return response()->json(['status' => false, 'message' => 'Document not available'], 403);
        }

        $path = storage_path('app/public/' . $document->file_path);

        if (! file_exists($path)) {
            return response()->json(['status' => false, 'message' => 'File not found'], 404);
        }

        return response()->download($path, $document->title . '.pdf');
    }

    // Admin: delete document
    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['status' => true, 'message' => 'Document deleted successfully']);
    }
}
