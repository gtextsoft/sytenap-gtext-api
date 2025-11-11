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
use App\Models\Document;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class DocumentController extends Controller {


    // Admin uploads a PDF for a specific client
    /**
     * @OA\Post(
     *      path="/api/v1/admin/upload",
     *      operationId="uploadClientDocument",
     *      tags={"Admin - Documents"},
     *      summary="Upload a PDF document for a specific client",
     *      description="Allows an admin user to upload a PDF document to Cloudinary and link it to a specific client, plot, or estate.",
     *      security={{"sanctum": {}}},
     *      
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"user_id","title","file"},
     *                  @OA\Property(property="user_id", type="integer", example=5, description="The ID of the client the document belongs to"),
     *                  @OA\Property(property="title", type="string", maxLength=255, example="Property Allocation Letter", description="Document title or name"),
     *                  @OA\Property(property="document_type", type="string", maxLength=100, example="Contract", nullable=true, description="Type of the document (e.g. contract, receipt, etc.)"),
     *                  @OA\Property(property="plot_id", type="integer", example=12, nullable=true, description="Associated plot ID (optional)"),
     *                  @OA\Property(property="estate_id", type="integer", example=7, nullable=true, description="Associated estate ID (optional)"),
     *                  @OA\Property(property="file", type="string", format="binary", description="The PDF file to upload (max size: 5MB)")
     *              )
     *          )
     *      ),
     *      
     *      @OA\Response(
     *          response=201,
     *          description="Document uploaded successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Document uploaded successfully."),
     *              @OA\Property(property="document", type="object",
     *                  @OA\Property(property="id", type="integer", example=10),
     *                  @OA\Property(property="uploaded_by", type="integer", example=1),
     *                  @OA\Property(property="user_id", type="integer", example=5),
     *                  @OA\Property(property="plot_id", type="integer", example=12, nullable=true),
     *                  @OA\Property(property="estate_id", type="integer", example=7, nullable=true),
     *                  @OA\Property(property="title", type="string", example="Property Allocation Letter"),
     *                  @OA\Property(property="document_type", type="string", example="pdf"),
     *                  @OA\Property(property="file_url", type="string", example="https://res.cloudinary.com/demo/raw/upload/v1731261234/client_documents/file.pdf"),
     *                  @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-10T09:00:00Z"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-10T09:00:00Z")
     *              )
     *          )
     *      ),
     *      
     *      @OA\Response(
     *          response=403,
     *          description="Access denied. Only admins can upload documents.",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Access denied. Only admins can upload documents.")
     *          )
     *      ),
     *      
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The given data was invalid."),
     *              @OA\Property(property="errors", type="object",
     *                  @OA\Property(property="user_id", type="array", @OA\Items(type="string", example="The user_id field is required.")),
     *                  @OA\Property(property="file", type="array", @OA\Items(type="string", example="The file must be a PDF."))
     *              )
     *          )
     *      ),
     *      
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to upload document."),
     *              @OA\Property(property="errors", type="object",
     *                  @OA\Property(property="server", type="array", @OA\Items(type="string", example="An unexpected error occurred. Please try again later."))
     *              )
     *          )
     *      )
     * )
     */

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
            'user_id' => 'required|exists:users,id', 
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

       
        $document = Document::create( [
            'uploaded_by' => $admin->id,
            'user_id' => $validated[ 'user_id' ], 
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

    // Fetch documents for logged-in user with pagination

    /**
 * @OA\Get(
 *      path="/api/v1/document/my-document",
 *      operationId="getUserDocuments",
 *      tags={"User - Documents"},
 *      summary="Fetch documents for the logged-in user",
 *      description="Retrieves all documents uploaded for the currently authenticated user with pagination support.",
 *      security={{"sanctum": {}}},
 *      
 *      @OA\Parameter(
 *          name="page",
 *          in="query",
 *          required=false,
 *          description="Page number for pagination (default: 1)",
 *          @OA\Schema(type="integer", example=1)
 *      ),
 *      
 *      @OA\Response(
 *          response=200,
 *          description="Documents retrieved successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="status", type="string", example="success"),
 *              @OA\Property(property="message", type="string", example="Documents retrieved successfully."),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="current_page", type="integer", example=1),
 *                  @OA\Property(property="data", type="array",
 *                      @OA\Items(
 *                          type="object",
 *                          @OA\Property(property="id", type="integer", example=12),
 *                          @OA\Property(property="title", type="string", example="Property Contract"),
 *                          @OA\Property(property="document_type", type="string", example="pdf"),
 *                          @OA\Property(property="file_url", type="string", example="https://res.cloudinary.com/demo/raw/upload/v1731261234/client_documents/file.pdf"),
 *                          @OA\Property(property="plot_id", type="integer", example=3, nullable=true),
 *                          @OA\Property(property="estate_id", type="integer", example=2, nullable=true),
 *                          @OA\Property(property="uploaded_by", type="integer", example=1),
 *                          @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-10T09:00:00Z"),
 *                          @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-10T09:00:00Z")
 *                      )
 *                  ),
 *                  @OA\Property(property="first_page_url", type="string", example="http://example.com/api/user/documents?page=1"),
 *                  @OA\Property(property="from", type="integer", example=1),
 *                  @OA\Property(property="last_page", type="integer", example=5),
 *                  @OA\Property(property="last_page_url", type="string", example="http://example.com/api/user/documents?page=5"),
 *                  @OA\Property(property="next_page_url", type="string", example="http://example.com/api/user/documents?page=2"),
 *                  @OA\Property(property="path", type="string", example="http://example.com/api/user/documents"),
 *                  @OA\Property(property="per_page", type="integer", example=10),
 *                  @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
 *                  @OA\Property(property="to", type="integer", example=10),
 *                  @OA\Property(property="total", type="integer", example=50)
 *              )
 *          )
 *      ),
 *      
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized. User not logged in.",
 *          @OA\JsonContent(
 *              @OA\Property(property="errors", type="string", example="Unauthorized")
 *          )
 *      ),
 *      
 *      @OA\Response(
 *          response=500,
 *          description="Internal server error",
 *          @OA\JsonContent(
 *              @OA\Property(property="status", type="string", example="error"),
 *              @OA\Property(property="message", type="string", example="Failed to retrieve documents. Please try again later.")
 *          )
 *      )
 * )
 */

    public function getUserDocument(Request $request)
    {
        $user = $request->user();
        if(!$user)
        {
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
}
