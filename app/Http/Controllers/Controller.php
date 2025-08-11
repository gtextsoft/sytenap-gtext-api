<?php

namespace App\Http\Controllers;


/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Sytemap API Documentation",
 *      description="API documentation for Sytemap application with OTP verification and user management",
 *      @OA\Contact(
 *          email="admin@sytemap.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="sanctum",
 *      type="apiKey",
 *      in="header",
 *      name="Authorization",
 *      description="Enter token in format: Bearer {token}"
 * )
 */

abstract class Controller
{
    //
}
