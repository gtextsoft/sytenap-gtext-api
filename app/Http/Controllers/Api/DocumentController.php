<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Models\User;
use App\Notifications\LegalDocumentSentNotification;
use App\Notifications\ClientSignedDocumentNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;

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

    
        $document = Document::create([
            'uploaded_by' => $admin->id,
            'user_id' => $validated['user_id'],
            'plot_id' => $validated['plot_id'] ?? null,
            'estate_id' => $validated['estate_id'] ?? null,
            'title' => $validated['title'],
            'document_type' => $validated['document_type'] ?? 'pdf',
            'public_id' => $uploadResult['public_id'], 
            'extension'    => $request->file('file')->getClientOriginalExtension(), 
            'file_url'      => $uploadResult['secure_url'],
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

     $documents = Document::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('uploaded_by', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Documents retrieved successfully.',
            'data' => $documents
        ], 200);
    }

    
    /**
 * @OA\Get(
 *     path="/api/v1/documents",
 *     operationId="getDocumentsList",
 *     tags={"Admin - Documents"},
 *     summary="Get list of documents",
 *     description="Retrieve a paginated list of documents ordered by most recent first for both legal and admin.",
 *     security={{"sanctum": {}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Page number for pagination",
 *         @OA\Schema(
 *             type="integer",
 *             example=1
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Documents retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Documents retrieved successfully."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 description="Paginated documents response",
 *                 @OA\Property(property="current_page", type="integer", example=1),
 *                 @OA\Property(
 *                     property="data",
 *                     type="array",
 *                     @OA\Items(
 *                         @OA\Property(property="id", type="integer", example=3),
 *                         @OA\Property(property="title", type="string", example="Deed of Assignment"),
 *                         @OA\Property(property="document_type", type="string", example="pdf"),
 *                         @OA\Property(property="file_url", type="string", example="https://res.cloudinary.com/..."),
 *                         @OA\Property(property="user_id", type="integer", example=12),
 *                         @OA\Property(property="uploaded_by", type="integer", example=5),
 *                         @OA\Property(property="plot_id", type="integer", nullable=true, example=8),
 *                         @OA\Property(property="estate_id", type="integer", nullable=true, example=2),
 *                         @OA\Property(property="comment", type="string", nullable=true, example="Please review and sign."),
 *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-20T10:15:30Z")
 *                     )
 *                 ),
 *                 @OA\Property(property="per_page", type="integer", example=10),
 *                 @OA\Property(property="total", type="integer", example=45),
 *                 @OA\Property(property="last_page", type="integer", example=5)
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 */

    public function index(Request $request)
    {
        $documents = Document::orderBy('created_at', 'desc')
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


    
  
    /**
     * @OA\Get(
     *     path="/api/v1/documents/{document}/download",
     *     operationId="downloadClientDocument",
     *     tags={"Documents"},
     *     summary="Download a client document",
     *     description="Allows an authorized user (client or uploader or admin) to download a document that was uploaded by admin or legal. The document is streamed and downloaded with the correct filename.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="document",
     *         in="path",
     *         required=true,
     *         description="ID of the document to download",
     *         @OA\Schema(
     *             type="integer",
     *             example=3
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Document downloaded successfully",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access to document"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Document not found or unavailable"
     *     )
     * )
     */

    public function download(Document $document)
    {
        $user = auth()->user();

        // Authorization
        if ($user->id !== $document->user_id && $user->id !== $document->uploaded_by || $user->account_type === 'client') {
            abort(403, 'Unauthorized');
        }

        $response = Http::timeout(60)->get($document->file_url);

        if (!$response->successful()) {
            abort(404, 'Document not found');
        }

        $fileName = $document->title . '.' . $document->document_type;

        return response()->streamDownload(
            fn () => print($response->body()),
            $fileName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/document/{document}/signed",
     *     summary="Send signed copy of a legal document",
     *     description="Allows a client to upload and send a signed copy of a legal document that was originally sent by the legal/admin team.",
     *     operationId="sendSignedDocument",
     *     tags={"Documents"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="document",
     *         in="path",
     *         description="ID of the document sent by legal/admin",
     *         required=true,
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Signed document upload",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Signed PDF document (max 5MB)"
    *                 ),
    *                 @OA\Property(
    *                     property="comment",
    *                     type="string",
    *                     example="I have signed and reviewed the document."
    *                 )
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(
    *         response=201,
    *         description="Signed document sent successfully",
    *         @OA\JsonContent(
    *             @OA\Property(property="success", type="boolean", example=true),
    *             @OA\Property(property="message", type="string", example="Signed document sent successfully."),
    *             @OA\Property(
    *                 property="document",
    *                 type="object",
    *                 @OA\Property(property="id", type="integer", example=45),
    *                 @OA\Property(property="title", type="string", example="Contract Agreement (Signed)"),
    *                 @OA\Property(property="file_url", type="string", example="https://res.cloudinary.com/..."),
    *                 @OA\Property(property="uploaded_by", type="integer", example=8),
    *                 @OA\Property(property="user_id", type="integer", example=1),
    *                 @OA\Property(property="parent_document_id", type="integer", example=12),
    *                 @OA\Property(property="created_at", type="string", format="date-time")
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(
    *         response=403,
    *         description="Unauthorized action",
    *         @OA\JsonContent(
    *             @OA\Property(property="success", type="boolean", example=false),
    *             @OA\Property(property="message", type="string", example="Only clients can send signed documents.")
    *         )
    *     ),
    *
    *     @OA\Response(
    *         response=404,
    *         description="Document not found"
    *     ),
    *
    *     @OA\Response(
    *         response=422,
    *         description="Validation error"
    *     )
    * )
    */

    public function sendSignedDocument(Request $request, Document $document)
    {
        $client = $request->user();

        // Only client can reply
        if ($client->account_type !== 'client') {
            return response()->json([
                'success' => false,
                'message' => 'Only clients can send signed documents.'
            ], 403);
        }

        // Ensure document belongs to client
        if ($document->user_id !== $client->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'file'    => 'required|mimes:pdf|max:5120',
            'comment' => 'nullable|string',
        ]);

        // Upload signed copy
        $uploadResult = Cloudinary::uploadApi()->upload(
            $request->file('file')->getRealPath(),
            [
                'folder' => 'signed_documents',
                'resource_type' => 'raw',
            ]
        );

        // Create reply document
        $signedDocument = Document::create([
            'uploaded_by'        => $client->id,
            'user_id'            => $document->uploaded_by, // send back to legal
            'estate_id'          => $document->estate_id,
            'plot_id'            => $document->plot_id,
            'title'              => $document->title . ' (Signed)',
            'document_type'      => $document->document_type,
            'public_id'          => $uploadResult['public_id'],
            'extension'          => $request->file('file')->getClientOriginalExtension(),
            'file_url'           => $uploadResult['secure_url'],
            'comment'            => $validated['comment'],
            'parent_document_id' => $document->id,
        ]);

        // Notify legal
        Notification::send(
            $document->uploader,
            new ClientSignedDocumentNotification($signedDocument)
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Signed document sent successfully.',
            'document' => $signedDocument,
        ], 201);
    }



}
