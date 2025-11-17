<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/admin/documents/upload",
     *     summary="Upload a new document",
     *     description="Upload a PDF document (admin only)",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Sample Document"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Document uploaded successfully"),
     *             @OA\Property(property="document", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Sample Document"),
     *                 @OA\Property(property="file_path", type="string", example="documents/file.pdf"),
     *                 @OA\Property(property="uploaded_by", type="integer", example=1),
     *                 @OA\Property(property="published", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function upload(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|mimes:pdf|max:5120',
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
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Document published successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Document not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function publish($id)
    {
        $document = Document::findOrFail($id);
        $document->published = true;
        $document->save();

        return response()->json(['status' => true, 'message' => 'Document published successfully']);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/documents/{id}/unpublish",
     *     summary="Unpublish a document",
     *     description="Mark a document as unpublished",
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
     *         description="Document unpublished successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Document unpublished successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Document not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function unpublish($id)
    {
        $document = Document::findOrFail($id);
        $document->published = false;
        $document->save();

        return response()->json(['status' => true, 'message' => 'Document unpublished successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/documents",
     *     summary="Get published documents",
     *     description="List all published documents (public)",
     *     tags={"Documents"},
     *     @OA\Response(
     *         response=200,
     *         description="List of published documents",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="documents", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Sample Document"),
     *                     @OA\Property(property="file_path", type="string", example="documents/file.pdf"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="url", type="string", example="http://example.com/storage/documents/file.pdf")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/v1/documents/{id}/download",
     *     summary="Download a document",
     *     description="Download a published document",
     *     tags={"Documents"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Document ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="File download"),
     *     @OA\Response(response=403, description="Document not available"),
     *     @OA\Response(response=404, description="File not found")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/documents/{id}",
     *     summary="Delete a document",
     *     description="Delete a document (admin only)",
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
     *         description="Document deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Document deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Document not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['status' => true, 'message' => 'Document deleted successfully']);
    }
}
