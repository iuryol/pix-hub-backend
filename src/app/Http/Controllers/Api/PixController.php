<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePixRequest;
use App\Http\Resources\PixTransactionResource;
use App\Services\PixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PixController extends Controller
{
    public function __construct(
        private PixService $pixService
    ) {}

    /**
     * @OA\Post(
     *     path="/pix",
     *     summary="Criar um novo PIX",
     *     description="Cria uma nova transação PIX e retorna o QR Code para pagamento",
     *     operationId="createPix",
     *     tags={"PIX"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=100.50, description="Valor do PIX"),
     *             @OA\Property(property="description", type="string", example="Pagamento teste", description="Descrição do pagamento")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PIX criado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="PIX criado com sucesso."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="external_id", type="string", example="PIX_abc123"),
     *                 @OA\Property(property="amount", type="number", example=100.50),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="qr_code", type="string", example="00020126580014br.gov.bcb.pix..."),
     *                 @OA\Property(property="qr_code_base64", type="string", example="data:image/png;base64,..."),
     *                 @OA\Property(property="expires_at", type="string", format="datetime", example="2025-11-19T04:30:00.000000Z"),
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
     *         description="Muitas requisições (Rate Limit: 60 req/min)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Too Many Attempts.")
     *         )
     *     )
     * )
     */
    public function store(CreatePixRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $pix = $this->pixService->createPix(
                user: $user,
                amount: $request->validated('amount'),
                description: $request->validated('description'),
            );

            return response()->json([
                'message' => 'PIX criado com sucesso.',
                'data' => new PixTransactionResource($pix),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar PIX.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/pix/{id}",
     *     summary="Obter detalhes de um PIX",
     *     description="Retorna os detalhes de uma transação PIX específica",
     *     operationId="getPix",
     *     tags={"PIX"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID da transação PIX",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalhes do PIX",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="external_id", type="string", example="PIX_abc123"),
     *                 @OA\Property(property="amount", type="number", example=100.50),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="qr_code", type="string"),
     *                 @OA\Property(property="paid_at", type="string", format="datetime"),
     *                 @OA\Property(property="expires_at", type="string", format="datetime"),
     *                 @OA\Property(property="created_at", type="string", format="datetime")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PIX não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="PIX não encontrado.")
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
        $pix = $this->pixService->findById($id);

        if (!$pix) {
            return response()->json([
                'message' => 'PIX não encontrado.',
            ], 404);
        }

        // Check if user owns this transaction
        if ($pix->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Acesso negado.',
            ], 403);
        }

        return response()->json([
            'data' => new PixTransactionResource($pix),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/pix",
     *     summary="Listar transações PIX",
     *     description="Lista todas as transações PIX do usuário autenticado",
     *     operationId="listPix",
     *     tags={"PIX"},
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
     *         description="Lista de transações PIX",
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
        $transactions = $this->pixService->getUserTransactions($request->user(), $limit);

        return response()->json([
            'data' => PixTransactionResource::collection($transactions),
        ]);
    }
}
