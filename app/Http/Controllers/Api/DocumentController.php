<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Models\User;
use App\Notifications\LegalDocumentSentNotification;
use Illuminate\Support\Facades\Notification;

use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/admin/documents/upload",
     *     summary="Upload a PDF document for a client",
     *     description="Admin uploads a PDF document to Cloudinary for a specific client",
     *     tags={"Admin - Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"user_id","title","file"},
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="title", type="string", example="Property Contract"),
     *                 @OA\Property(property="document_type", type="string", nullable=true, example="pdf"),
     *                 @OA\Property(property="plot_id", type="integer", nullable=true, example=12),
     *                 @OA\Property(property="estate_id", type="integer", nullable=true, example=7),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Document uploaded successfully"),
     *     @OA\Response(response=403, description="Access denied. Only admins can upload documents."),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        if ($admin->account_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only admins can upload documents.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id', 
            'title' => 'required|string|max:255',
            'document_type' => 'nullable|string|max:100',
            'file' => 'required|mimes:pdf|max:5120',
            'plot_id' => 'nullable|exists:plots,id',
            'estate_id' => 'nullable|exists:estates,id',
        ]);

        $uploadResult = Cloudinary::uploadApi()->upload(
            $request->file('file')->getRealPath(),
            [
                'folder' => 'client_documents',
                'resource_type' => 'raw',
            ]
        );


        // Access properties like array
        $publicId = $uploadResult['public_id'];
        $format   = $uploadResult['format'];

        // Generate download URL
        $fileUrl = "https://res.cloudinary.com/dhfmwhhbc/raw/upload/fl_attachment/{$publicId}.{$format}";

        $document = Document::create([
            'uploaded_by' => $admin->id,
            'user_id' => $validated['user_id'],
            'plot_id' => $validated['plot_id'] ?? null,
            'estate_id' => $validated['estate_id'] ?? null,
            'title' => $validated['title'],
            'document_type' => $validated['document_type'] ?? 'pdf',
            //'file_url' => //$uploadResult['secure_url'],
            'file_url' => $fileUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'document' => $document,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/document/my-document",
     *     summary="Fetch documents for logged-in user",
     *     tags={"User - Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Documents retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getUserDocument(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['errors' => 'Unauthorized'], 401);
        }

        $documents = Document::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Documents retrieved successfully.',
            'data' => $documents
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/documents/{id}/publish",
     *     summary="Publish a document",
     *     tags={"Admin - Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document published successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Document not found")
     * )
     */
    public function publish(Request $request, $id)
    {
        $admin = $request->user();
        if ($admin->account_type !== 'admin') {
            return response()->json(['status' => false, 'message' => 'Access denied.'], 403);
        }

        $document = Document::findOrFail($id);
        $document->published = true;
        $document->save();

        return response()->json(['status' => true, 'message' => 'Document published successfully']);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/documents/{id}/unpublish",
     *     summary="Unpublish a document",
     *     tags={"Admin - Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document unpublished successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Document not found")
     * )
     */
    public function unpublish(Request $request, $id)
    {
        $admin = $request->user();
        if ($admin->account_type !== 'admin') {
            return response()->json(['status' => false, 'message' => 'Access denied.'], 403);
        }

        $document = Document::findOrFail($id);
        $document->published = false;
        $document->save();

        return response()->json(['status' => true, 'message' => 'Document unpublished successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/documents/{id}/download",
     *     summary="Download a published document",
     *     tags={"Documents"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Download URL returned"),
     *     @OA\Response(response=403, description="Document not available"),
     *     @OA\Response(response=404, description="Document not found")
     * )
     */
    // public function download($id)
    // {
    //     $document = Document::findOrFail($id);

    //     if (!$document->published) {
    //         return response()->json(['status' => false, 'message' => 'Document not available'], 403);
    //     }

    //     return response()->json(['status' => true, 'url' => $document->file_url]);
    // }


 //  Add the new index() method here
    public function index(Request $request)
    {
        $documents = Document::where('published', true)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Documents retrieved successfully.',
            'data' => $documents
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/documents/{id}",
     *     summary="Delete a document",
     *     tags={"Admin - Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document deleted successfully"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Document not found")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $admin = $request->user();
        if ($admin->account_type !== 'admin') {
            return response()->json(['status' => false, 'message' => 'Access denied.'], 403);
        }

        $document = Document::findOrFail($id);
        $document->delete();

        return response()->json(['status' => true, 'message' => 'Document deleted successfully']);
    }


    public function getAllUserDocument(Request $request)
    {
        

        $documents = Document::all();


         return response()->json([
                    'status' => 'success',
                    'message' => 'Documents retrieved successfully.',
                    'data' => $documents
                ], 200);
    }

   
    /**
     * @OA\Post(
     *      path="/api/v1/legal/send-document",
     *      operationId="legalSendDocumentToClient",
     *      tags={"Legal - Documents"},
     *      summary="Send legal document to client",
     *      description="Allows a legal user to upload and send a document to a client with an optional explanatory comment. Client is notified via email.",
     *      security={{"sanctum": {}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"user_id","title","file"},
     *
     *                  @OA\Property(
     *                      property="user_id",
     *                      type="integer",
     *                      example=12,
     *                      description="Client user ID"
     *                  ),
     *
     *                  @OA\Property(
     *                      property="title",
     *                      type="string",
     *                      example="Deed of Assignment",
     *                      description="Document title"
     *                  ),
     *
     *                  @OA\Property(
     *                      property="comment",
     *                      type="string",
     *                      example="Please review clause 4 before signing.",
     *                      description="Optional explanation from legal"
     *                  ),
     *
     *                  @OA\Property(
     *                      property="document_type",
     *                      type="string",
     *                      example="pdf"
     *                  ),
     *
     *                  @OA\Property(
     *                      property="file",
     *                      type="string",
     *                      format="binary",
     *                      description="PDF document file"
     *                  ),
     *
     *                  @OA\Property(
     *                      property="plot_id",
     *                      type="integer",
     *                      example=7,
     *                      nullable=true
     *                  ),
     *
     *                  @OA\Property(
     *                      property="estate_id",
     *                      type="integer",
     *                      example=3,
     *                      nullable=true
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Document sent successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Document sent to client successfully."),
     *              @OA\Property(
     *                  property="document",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=15),
     *                  @OA\Property(property="title", type="string", example="Deed of Assignment"),
     *                  @OA\Property(property="file_url", type="string"),
     *                  @OA\Property(property="comment", type="string")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Access denied"
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated"
     *      )
     * )
     */

    public function sendDocumentToClient(Request $request)
    {
        $legal = $request->user();

        if ($legal->account_type !== 'legal') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only legal users can send documents.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'title'         => 'required|string|max:255',
            'comment'       => 'nullable|string',
            'document_type' => 'nullable|string|max:100',
            'file'          => 'required|mimes:pdf|max:5120',
            'plot_id'       => 'nullable|exists:plots,id',
            'estate_id'     => 'nullable|exists:estates,id',
        ]);

        // Upload to Cloudinary
        $uploadResult = Cloudinary::uploadApi()->upload(
            $request->file('file')->getRealPath(),
            [
                'folder' => 'legal_documents',
                'resource_type' => 'raw',
            ]
        );  // Access properties like array
        //Log::error($uploadResult);
        $publicId = $uploadResult['public_id'];
        $format   =  $request->file('file')->getClientOriginalExtension();

        

        $document = Document::create([
            'uploaded_by'   => $legal->id,
            'user_id'       => $validated['user_id'],
            'plot_id'       => $validated['plot_id'] ?? null,
            'estate_id'     => $validated['estate_id'] ?? null,
            'title'         => $validated['title'],
            'document_type' => $validated['document_type'] ?? 'pdf',
            'public_id' => $uploadResult['public_id'], 
            'extension'    => $request->file('file')->getClientOriginalExtension(), 
            'file_url'      => $uploadResult['secure_url'],
            'comment'       => $validated['comment'] ?? null,
        ]);

        // Notify Client
        $client = User::findOrFail($validated['user_id']);

        Notification::send(
            $client,
            new LegalDocumentSentNotification($document)
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Document sent to client successfully.',
            'document' => $document,
        ], 201);
    }


    public function download(Document $document)
    {
        // Generate signed URL for raw file
        $fileUrl = Cloudinary::downloadApi()->download(
            $document->public_id,
            ['resource_type' => 'raw']
        );

        return response()->streamDownload(function () use ($fileUrl) {
            echo file_get_contents($fileUrl);
        }, $document->title . '.' . $document->extension);
    }

    



}
