namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    // GET /api/faqs
    public function index()
    {
        $faqs = Faq::where('is_active', true)->get();
        return response()->json([
            'status' => 'success',
            'data' => $faqs
        ]);
    }

    // POST /api/faqs
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $faq = Faq::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'FAQ created successfully',
            'data' => $faq
        ], 201);
    }

    // GET /api/faqs/{id}
    public function show(Faq $faq)
    {
        return response()->json([
            'status' => 'success',
            'data' => $faq
        ]);
    }

    // PUT /api/faqs/{id}
    public function update(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'is_active' => 'boolean',
        ]);

        $faq->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'FAQ updated successfully',
            'data' => $faq
        ]);
    }

    // DELETE /api/faqs/{id}
    public function destroy(Faq $faq)
    {
        $faq->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'FAQ deleted successfully'
        ]);
    }
}
