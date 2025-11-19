<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="PIX Hub API",
 *     description="API para integração com subadquirentes de pagamento (PIX e Saques)",
 *     @OA\Contact(
 *         email="dev@pixhub.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de autenticação via Laravel Sanctum"
 * )
 *
 * @OA\Tag(
 *     name="Health",
 *     description="Endpoints de verificação de saúde"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Endpoints de autenticação"
 * )
 *
 * @OA\Tag(
 *     name="PIX",
 *     description="Endpoints para operações de PIX"
 * )
 *
 * @OA\Tag(
 *     name="Withdraw",
 *     description="Endpoints para operações de Saque"
 * )
 */
abstract class Controller
{
    //
}
