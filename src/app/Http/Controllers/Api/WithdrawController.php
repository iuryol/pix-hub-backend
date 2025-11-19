<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateWithdrawRequest;
use App\Http\Resources\WithdrawalResource;
use App\Services\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawController extends Controller
{
    public function __construct(
        private WithdrawService $withdrawService
    ) {}

    /**
     * @OA\Post(
     *     path="/withdraw",
     *     summary="Criar um novo saque",
     *     description="Cria uma nova solicitação de saque via PIX ou dados bancários",
     *     operationId="createWithdraw",
     *     tags={"Withdraw"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=500.00, description="Valor do saque"),
     *             @OA\Property(property="pix_key", type="string", example="12345678901", description="Chave PIX (CPF, email, telefone ou aleatória)"),
     *             @OA\Property(property="pix_key_type", type="string", enum={"cpf", "cnpj", "email", "phone", "random"}, example="cpf", description="Tipo da chave PIX"),
     *             @OA\Property(property="bank_code", type="string", example="001", description="Código do banco"),
     *             @OA\Property(property="agency", type="string", example="1234", description="Agência"),
     *             @OA\Property(property="account", type="string", example="123456-7", description="Número da conta"),
     *             @OA\Property(property="account_type", type="string", enum={"checking", "savings"}, example="checking", description="Tipo da conta")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Saque criado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Saque criado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="external_id", type="string", example="WD_xyz789"),
     *                 @OA\Property(property="amount", type="number", example=500.00),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="pix_key", type="string", example="12345678901"),
     *                 @OA\Property(property="pix_key_type", type="string", example="cpf"),
     *                 @OA\Property(property="created_at", type="string", format="datetime", example="2025-11-19T04:00:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="O campo amount é obrigatório.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Muitas requisições (Rate Limit: 30 req/min)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Too Many Attempts.")
     *         )
     *     )
     * )
     */
    public function store(CreateWithdrawRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $withdrawal = $this->withdrawService->createWithdraw(
                user: $user,
                amount: $validated['amount'],
                pixKey: $validated['pix_key'] ?? null,
                pixKeyType: $validated['pix_key_type'] ?? null,
                bankData: [
                    'bank_code' => $validated['bank_code'] ?? null,
                    'agency' => $validated['agency'] ?? null,
                    'account' => $validated['account'] ?? null,
                    'account_type' => $validated['account_type'] ?? null,
                ],
            );

            return response()->json([
                'message' => 'Saque criado com sucesso.',
                'data' => new WithdrawalResource($withdrawal),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar saque.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/withdraw/{id}",
     *     summary="Obter detalhes de um saque",
     *     description="Retorna os detalhes de uma solicitação de saque específica",
     *     operationId="getWithdraw",
     *     tags={"Withdraw"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID do saque",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalhes do saque",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="external_id", type="string", example="WD_xyz789"),
     *                 @OA\Property(property="amount", type="number", example=500.00),
     *                 @OA\Property(property="status", type="string", example="success"),
     *                 @OA\Property(property="pix_key", type="string"),
     *                 @OA\Property(property="pix_key_type", type="string"),
     *                 @OA\Property(property="completed_at", type="string", format="datetime"),
     *                 @OA\Property(property="created_at", type="string", format="datetime")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Saque não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Saque não encontrado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Acesso negado.")
     *         )
     *     )
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $withdrawal = $this->withdrawService->findById($id);

        if (!$withdrawal) {
            return response()->json([
                'message' => 'Saque não encontrado.',
            ], 404);
        }

        // Check if user owns this withdrawal
        if ($withdrawal->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Acesso negado.',
            ], 403);
        }

        return response()->json([
            'data' => new WithdrawalResource($withdrawal),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/withdraw",
     *     summary="Listar saques",
     *     description="Lista todos os saques do usuário autenticado",
     *     operationId="listWithdraw",
     *     tags={"Withdraw"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Número máximo de registros",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de saques",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="external_id", type="string"),
     *                     @OA\Property(property="amount", type="number"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $withdrawals = $this->withdrawService->getUserWithdrawals($request->user(), $limit);

        return response()->json([
            'data' => WithdrawalResource::collection($withdrawals),
        ]);
    }
}
