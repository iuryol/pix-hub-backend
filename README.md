# PIX Hub - Gateway de Pagamentos

Modulo Laravel para integracao com subadquirentes de pagamento (PIX e Saques).

## Requisitos

- Docker e Docker Compose
- Git

## Instalacao

### 1. Clone o repositorio

```bash
git clone <repository-url>
cd pix-hub
```

### 2. Inicie os containers Docker

```bash
docker compose up -d
```

Isso ira criar:
- **app**: PHP 8.2-FPM com Laravel
- **mysql**: MySQL 8.0
- **nginx**: Servidor web na porta 8080
- **worker**: Queue worker com Supervisor (8 processos)

### 3. Instale as dependencias

```bash
docker compose exec app composer install
```

### 4. Configure o ambiente

```bash
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
```

### 5. Execute as migrations e seeders

```bash
docker compose exec app php artisan migrate --seed
```

Isso criara:
- Tabelas do banco de dados
- Subadquirentes de exemplo (SubadqA e SubadqB)
- Usuarios de teste

### 6. Gere a documentacao Swagger

```bash
docker compose exec app php artisan l5-swagger:generate
```

## Documentacao da API

A documentacao Swagger esta disponivel em: `http://localhost:8080/api/documentation`

A URL raiz (`http://localhost:8080/`) redireciona automaticamente para a documentacao.

### Usuarios de Teste

- **user-a@example.com** / password (SubadqA)
- **user-b@example.com** / password (SubadqB)

## Executando os Testes

### Todos os testes

```bash
docker compose exec app php artisan test
```

### Testes especificos

```bash
# Apenas testes unitarios
docker compose exec app php artisan test --testsuite=Unit

# Apenas testes de feature
docker compose exec app php artisan test --testsuite=Feature

# Teste especifico
docker compose exec app php artisan test --filter=PixControllerTest
```

### Com cobertura de codigo

```bash
docker compose exec app php artisan test --coverage
```

## Estrutura do Projeto

```
src/
├── app/
│   ├── Contracts/PaymentGateway/    # Interfaces
│   ├── DTOs/                        # Data Transfer Objects
│   │   ├── WebhookData.php          # DTO tipado para webhooks
│   │   ├── PixRequestDTO.php
│   │   └── ...
│   ├── Enums/                       # Enums de status
│   ├── Events/                      # Eventos do sistema
│   ├── Exceptions/                  # Excecoes customizadas
│   │   ├── Gateway/                 # Excecoes especificas de gateway
│   │   │   ├── GatewayConnectionException.php
│   │   │   ├── GatewayTimeoutException.php
│   │   │   ├── GatewayAuthenticationException.php
│   │   │   ├── GatewayRateLimitException.php
│   │   │   ├── GatewayValidationException.php
│   │   │   └── InvalidWebhookPayloadException.php
│   │   └── GatewayException.php
│   ├── Http/
│   │   ├── Controllers/Api/         # Controllers da API
│   │   ├── Middleware/              # Middlewares
│   │   ├── Requests/                # Form Requests
│   │   └── Resources/               # API Resources
│   ├── Jobs/                        # Jobs assincronos
│   ├── Models/                      # Eloquent Models
│   └── Services/
│       ├── PaymentGateway/          # Implementacoes dos gateways
│       │   ├── Gateways/
│       │   │   ├── AbstractGateway.php
│       │   │   ├── SubadqAGateway.php
│       │   │   └── SubadqBGateway.php
│       │   ├── MockGateway.php
│       │   └── PaymentGatewayFactory.php
│       ├── PixService.php           # Logica de negocio PIX
│       └── WithdrawService.php      # Logica de negocio Saque
├── database/
│   ├── migrations/                  # Migrations
│   └── seeders/                     # Seeders
├── routes/
│   └── api.php                      # Rotas da API
└── tests/
    ├── Feature/Api/                 # Testes de integracao
    └── Unit/                        # Testes unitarios
```

## API Endpoints

Base URL: `http://localhost:8080/api`

### Autenticacao

```bash
# Gerar token
POST /auth/token
Content-Type: application/json
{
    "email": "user-a@example.com",
    "password": "password"
}
```

**Resposta:**
```json
{
    "token": "1|abc123xyz...",
    "token_type": "Bearer"
}
```

Todas as rotas protegidas requerem autenticacao via Bearer Token:
```bash
Authorization: Bearer <token>
```

### Rotas Disponiveis

#### Health Check
```
GET /health
```

#### PIX

```bash
# Criar PIX
POST /pix
Content-Type: application/json
{
    "amount": 100.50,
    "description": "Pagamento teste"
}

# Listar PIX do usuario
GET /pix

# Detalhes de um PIX
GET /pix/{id}
```

#### Saques

```bash
# Criar Saque (via PIX)
POST /withdraw
Content-Type: application/json
{
    "amount": 500.00,
    "pix_key": "12345678901",
    "pix_key_type": "cpf"
}

# Criar Saque (via dados bancarios)
POST /withdraw
Content-Type: application/json
{
    "amount": 500.00,
    "bank_code": "001",
    "agency": "1234",
    "account": "123456-7",
    "account_type": "checking"
}

# Listar Saques do usuario
GET /withdraw

# Detalhes de um Saque
GET /withdraw/{id}
```

## Rate Limiting

A API possui limites de requisicoes por endpoint:

| Endpoint | Limite |
|----------|--------|
| POST /auth/token | 5 req/min |
| POST /pix | 60 req/min |
| POST /withdraw | 30 req/min |

Quando o limite e excedido, a API retorna status 429 (Too Many Requests).

## Exemplos de Uso

### Obter Token

```bash
curl -X POST http://localhost:8080/api/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email": "user-a@example.com", "password": "password"}'
```

### Criar um PIX

```bash
curl -X POST http://localhost:8080/api/pix \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"amount": 100.50, "description": "Teste PIX"}'
```

**Resposta:**
```json
{
    "message": "PIX criado com sucesso.",
    "data": {
        "id": 1,
        "external_id": "PIX_abc123",
        "amount": 100.50,
        "status": "pending",
        "qr_code": "00020126580014br.gov.bcb.pix...",
        "qr_code_base64": "data:image/png;base64,...",
        "expires_at": "2025-11-19T04:30:00.000000Z",
        "created_at": "2025-11-19T04:00:00.000000Z"
    }
}
```

### Criar um Saque

```bash
curl -X POST http://localhost:8080/api/withdraw \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"amount": 500.00, "pix_key": "12345678901", "pix_key_type": "cpf"}'
```

**Resposta:**
```json
{
    "message": "Saque criado com sucesso.",
    "data": {
        "id": 1,
        "external_id": "WD_xyz789",
        "amount": 500.00,
        "status": "pending",
        "pix_key": "12345678901",
        "pix_key_type": "cpf",
        "created_at": "2025-11-19T04:00:00.000000Z"
    }
}
```

## Arquitetura

### Padroes Utilizados

- **Strategy Pattern**: Diferentes implementacoes de gateway (SubadqA, SubadqB, Mock)
- **Factory Pattern**: `PaymentGatewayFactory` para instanciar gateways
- **Repository/Service Layer**: Separacao de logica de negocio
- **DTOs**: Transferencia de dados tipados entre camadas
- **Exception Handling**: Excecoes especificas por tipo de erro

### Sistema de Excecoes

O sistema possui excecoes tipadas para cada cenario de erro:

| Excecao | Descricao | HTTP Status |
|---------|-----------|-------------|
| GatewayConnectionException | Falha de conexao com gateway | - |
| GatewayTimeoutException | Timeout na requisicao | - |
| GatewayAuthenticationException | Credenciais invalidas | 401 |
| GatewayValidationException | Erro de validacao | 422 |
| GatewayRateLimitException | Rate limit excedido | 429 |
| InvalidWebhookPayloadException | Payload de webhook invalido | - |

### WebhookData DTO

O processamento de webhooks utiliza um DTO tipado:

```php
readonly class WebhookData
{
    public function __construct(
        public string $externalId,
        public string $status,
        public ?float $amount = null,
        public ?Carbon $paidAt = null,
        public ?Carbon $completedAt = null,
        public ?string $failureReason = null,
        public array $rawPayload = [],
    ) {}

    // Metodos auxiliares
    public function isPaid(): bool;
    public function isFailed(): bool;
    public function isPending(): bool;
}
```

### Fluxo de Processamento

1. **Request** - Controller valida e chama Service
2. **Service** - Cria registro no DB e chama Gateway
3. **Gateway** - Faz requisicao HTTP para API externa
4. **Job** - Simula webhook de confirmacao (2-5s delay)
5. **Event** - Dispara evento de confirmacao

### Tratamento de Erros nos Jobs

Os jobs de webhook possuem tratamento especifico por tipo de erro:

- **GatewayRateLimitException**: Release com delay dinamico
- **GatewayTimeoutException**: Retry com backoff
- **GatewayConnectionException**: Retry com backoff
- **InvalidWebhookPayloadException**: Marca como failed (sem retry)

### Adicionando Novo Gateway

1. Crie a classe em `app/Services/PaymentGateway/Gateways/`:

```php
class NewGateway extends AbstractGateway
{
    public function getIdentifier(): string
    {
        return 'new-gateway';
    }

    public function createPix(PixRequestDTO $request): PixResponseDTO
    {
        // Implementacao
    }

    public function processPixWebhook(array $payload): WebhookData
    {
        // Validar campos obrigatorios
        if (empty($payload['id'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: id',
                payload: $payload,
            );
        }

        return new WebhookData(
            externalId: $payload['id'],
            status: $payload['status'],
            // ...
        );
    }

    // ... outros metodos
}
```

2. Registre no `PaymentGatewayFactory`:

```php
return match ($subacquirer->slug) {
    'subadq-a' => new SubadqAGateway($subacquirer),
    'subadq-b' => new SubadqBGateway($subacquirer),
    'new-gateway' => new NewGateway($subacquirer),
    default => throw new UnsupportedGatewayException($subacquirer->slug),
};
```

3. Crie o seeder para o novo subadquirer.

## Queue Workers

O sistema utiliza Supervisor para gerenciar os workers de fila:

```bash
# Verificar status dos workers
docker compose exec worker supervisorctl status

# Ver logs dos workers
docker compose exec app tail -f storage/logs/worker.log

# Reiniciar workers
docker compose exec worker supervisorctl restart all
```

Configuracao atual: 8 processos paralelos para processar webhooks.

## Logs

Os logs sao separados por canal:

- **gateway**: Requisicoes/respostas dos gateways
- **api**: Requisicoes da API
- **worker**: Processamento de jobs

```bash
# Ver logs do gateway
docker compose exec app tail -f storage/logs/gateway.log

# Ver logs da API
docker compose exec app tail -f storage/logs/api.log

# Ver logs do worker
docker compose exec app tail -f storage/logs/worker.log
```

## Troubleshooting

### Container nao inicia

```bash
docker compose down
docker compose up -d --build
```

### Erro de permissao

```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Limpar cache

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
```

### Recriar banco de dados

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Erro 429 do gateway externo

Se o mock da Postman retornar 429, use o MockGateway local:

```bash
docker compose exec app php artisan tinker --execute="App\Models\Subacquirer::where('slug', 'subadq-a')->update(['base_url' => 'mock://local']);"
```

### Regenerar documentacao Swagger

```bash
docker compose exec app php artisan l5-swagger:generate
```

## Tecnologias

- PHP 8.2
- Laravel 11
- MySQL 8.0
- Docker & Docker Compose
- Supervisor (queue workers)
- PHPUnit
- Laravel Sanctum (autenticacao)
- L5-Swagger (documentacao OpenAPI)
