<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\CommissionSetting;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CommissionSettingController extends Controller {


/**
 * @OA\Get(
 *      path="/api/v1/admin/commission-settings",
 *      operationId="getCommissionSettings",
 *      tags={"Admin - Commission Settings"},
 *      summary="List all commission settings",
 *      description="Returns all commission settings in the system. Only accessible by admin users.",
 *      security={{"sanctum":{}}},
 *
 *      @OA\Response(
 *          response=200,
 *          description="Successful retrieval",
 *          @OA\JsonContent(
 *              type="array",
 *              @OA\Items(
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="value", type="number", format="float", example=10),
 *                  @OA\Property(property="type", type="string", example="percentage"),
 *                  @OA\Property(property="status", type="boolean", example=true)
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=403,
 *          description="Access denied",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=false),
 *              @OA\Property(property="message", type="string", example="Access denied.")
 *          )
 *      )
 * )
 */
public function index(Request $request)
{
    $admin = $request->user();
    Log::error('Authenticated user: ', ['user' => $admin]);

    // if ($admin->account_type !== 'admin') {
    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Access denied.'
    //     ], 403);
    // }

    $settings = CommissionSetting::orderBy('id', 'desc')->get();

    return response()->json($settings);
}


/**
 * @OA\Post(
 *      path="/api/v1/admin/commission-settings",
 *      operationId="createCommissionSetting",
 *      tags={"Admin - Commission Settings"},
 *      summary="Create commission setting",
 *      description="Creates a new commission configuration for agents. 
 *                   Commission can be defined as percentage or flat value 
 *                   and optionally restricted by transaction amount range 
 *                   and agent role.",
 *      security={{"sanctum": {}}},
 *
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"value","type"},
 *
 *              @OA\Property(
 *                  property="value",
 *                  type="number",
 *                  format="float",
 *                  example=10,
 *                  description="Commission value. Represents percentage or flat value depending on the type."
 *              ),
 *
 *              @OA\Property(
 *                  property="type",
 *                  type="string",
 *                  enum={"percentage","flat"},
 *                  example="percentage",
 *                  description="Commission calculation type"
 *              ),
 *
 *              @OA\Property(
 *                  property="status",
 *                  type="boolean",
 *                  example=true,
 *                  description="Whether this commission rule is active"
 *              ),
 *
 *              @OA\Property(
 *                  property="min",
 *                  type="number",
 *                  format="float",
 *                  example=100000,
 *                  description="Minimum transaction amount this commission applies to"
 *              ),
 *
 *              @OA\Property(
 *                  property="max",
 *                  type="number",
 *                  format="float",
 *                  example=5000000,
 *                  description="Maximum transaction amount this commission applies to"
 *              ),
 *
 *              @OA\Property(
 *                  property="agent_role",
 *                  type="string",
 *                  enum={"associate","staff","all"},
 *                  example="associate",
 *                  description="Agent role this commission applies to"
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=200,
 *          description="Commission setting created successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Commission setting added successfully."),
 *
 *              @OA\Property(
 *                  property="data",
 *                  type="object",
 *
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="value", type="number", format="float", example=10),
 *                  @OA\Property(property="type", type="string", example="percentage"),
 *                  @OA\Property(property="status", type="boolean", example=true),
 *                  @OA\Property(property="min", type="number", example=100000),
 *                  @OA\Property(property="max", type="number", example=5000000),
 *                  @OA\Property(property="agent_role", type="string", example="associate"),
 *                  @OA\Property(property="created_at", type="string", format="date-time"),
 *                  @OA\Property(property="updated_at", type="string", format="date-time")
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=403,
 *          description="Access denied. Only admins can create commission settings.",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=false),
 *              @OA\Property(property="message", type="string", example="Access denied.")
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=422,
 *          description="Validation error",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=false),
 *              @OA\Property(property="errors", type="object",
 *                  example={
 *                      "value": {"The value field is required."},
 *                      "type": {"The selected type is invalid."}
 *                  }
 *              )
 *          )
 *      )
 * )
 */

public function store(Request $request) {
    $admin = Auth::user();
    if ($admin->account_type !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Access denied.'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'value' => 'required|numeric|min:0',
        'type' => 'required|in:percentage,flat',
        'status' => 'nullable|boolean',
        'min' => 'nullable|numeric|min:0',
        'max' => 'nullable|numeric|min:0',
        'agent_role' => 'nullable|string|in:associate,staff,all'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $setting = CommissionSetting::create([
        'value' => $request->value,
        'type' => $request->type,
        'status' => $request->status ?? false,
        'min' => $request->min ?? 0,
        'max' => $request->max ?? 0,
        'agent_role' => $request->agent_role ?? 'all'
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Commission setting added successfully.',
        'data' => $setting
    ]);
}

/**
 * @OA\Patch(
 *      path="/api/v1/admin/commission-settings/{id}/toggle",
 *      operationId="toggleCommissionStatus",
 *      tags={"Admin - Commission Settings"},
 *      summary="Toggle commission setting status",
 *      description="Allows an admin to activate or deactivate a commission setting by toggling its status.",
 *      security={{"sanctum": {}}},
 *
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="ID of the commission setting",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *
 *      @OA\Response(
 *          response=200,
 *          description="Commission status updated successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Commission status updated."),
 *              @OA\Property(
 *                  property="data",
 *                  type="object",
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="value", type="number", format="float", example=10),
 *                  @OA\Property(property="type", type="string", example="percentage"),
 *                  @OA\Property(property="status", type="boolean", example=true)
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=403,
 *          description="Access denied. Only admin can toggle commission status.",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=false),
 *              @OA\Property(property="message", type="string", example="Access denied.")
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=404,
 *          description="Commission setting not found",
 *          @OA\JsonContent(
 *              @OA\Property(property="message", type="string", example="Commission setting not found.")
 *          )
 *      )
 * )
 */
public function toggleStatus($id)
{
    $admin = Auth::user();
    if ($admin->account_type !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Access denied.'
        ], 403);
    }

    $setting = CommissionSetting::find($id);
    if (!$setting) {
        return response()->json(['message' => 'Commission setting not found.'], 404);
    }

    $setting->status = !$setting->status;
    $setting->save();

    return response()->json([
        'success' => true,
        'message' => 'Commission status updated.',
        'data' => $setting
    ]);
}


}
 