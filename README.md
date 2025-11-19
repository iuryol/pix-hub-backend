# PIX Hub - Gateway de Pagamentos

Módulo Laravel para integração com subadquirentes de pagamento (PIX e Saques).

## Requisitos

- Docker e Docker Compose
- Git

## Instalação

### 1. Clone o repositório

```bash
git clone <repository-url>
cd pix-hub
```

### 2. Inicie os containers Docker

```bash
docker compose up -d
```

Isso irá criar:
- **app**: PHP 8.2-FPM com Laravel
- **mysql**: MySQL 8.0
- **nginx**: Servidor web na porta 8080

### 3. Instale as dependências

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

Isso criará:
- Tabelas do banco de dados
- Subadquirentes de exemplo (SubadqA e SubadqB)
- Usuários de teste

### 6. Gere um token de teste

```bash
docker compose exec app php artisan app:create-test-token
```

## Executando os Testes

### Todos os testes

```bash
docker compose exec app php artisan test
```

### Testes específicos

```bash
# Apenas testes unitários
docker compose exec app php artisan test --testsuite=Unit

# Apenas testes de feature
docker compose exec app php artisan test --testsuite=Feature

# Teste específico
docker compose exec app php artisan test --filter=PixControllerTest
```

### Com cobertura de código

```bash
docker compose exec app php artisan test --coverage
```

## Estrutura do Projeto

```
src/
├── app/
│   ├── Contracts/PaymentGateway/    # Interfaces
│   ├── DTOs/                        # Data Transfer Objects
│   ├── Enums/                       # Enums de status
│   ├── Events/                      # Eventos do sistema
│   ├── Exceptions/                  # Exceções customizadas
│   ├── Http/
│   │   ├── Controllers/Api/         # Controllers da API
│   │   ├── Middleware/              # Middlewares
│   │   ├── Requests/                # Form Requests
│   │   └── Resources/               # API Resources
│   ├── Jobs/                        # Jobs assíncronos
│   ├── Models/                      # Eloquent Models
│   └── Services/
│       ├── PaymentGateway/          # Implementações dos gateways
│       ├── PixService.php           # Lógica de negócio PIX
│       └── WithdrawService.php      # Lógica de negócio Saque
├── database/
│   ├── migrations/                  # Migrations
│   └── seeders/                     # Seeders
├── routes/
│   └── api.php                      # Rotas da API
└── tests/
    ├── Feature/Api/                 # Testes de integração
    └── Unit/                        # Testes unitários
```

## API Endpoints

Base URL: `http://localhost:8080/api`

### Autenticação

Todas as rotas (exceto `/health`) requerem autenticação via Bearer Token.

```bash
Authorization: Bearer <token>
```

### Rotas Disponíveis

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

# Listar PIX do usuário
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

# Criar Saque (via dados bancários)
POST /withdraw
Content-Type: application/json
{
    "amount": 500.00,
    "bank_code": "001",
    "agency": "1234",
    "account": "123456-7",
    "account_type": "checking"
}

# Listar Saques do usuário
GET /withdraw

# Detalhes de um Saque
GET /withdraw/{id}
```

## Exemplos de Uso

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
    "success": true,
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
    "success": true,
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

### Padrões Utilizados

- **Strategy Pattern**: Diferentes implementações de gateway (SubadqA, SubadqB)
- **Factory Pattern**: `PaymentGatewayFactory` para instanciar gateways
- **Repository/Service Layer**: Separação de lógica de negócio
- **DTOs**: Transferência de dados tipados entre camadas

### Fluxo de Processamento

1. **Request** → Controller valida e chama Service
2. **Service** → Cria registro no DB e chama Gateway
3. **Gateway** → Faz requisição HTTP para API externa
4. **Job** → Simula webhook de confirmação (2-5s delay)
5. **Event** → Dispara evento de confirmação

### Adicionando Novo Gateway

1. Crie a classe em `app/Services/PaymentGateway/`:

```php
class NewGateway extends AbstractGateway
{
    public function getIdentifier(): string
    {
        return 'new-gateway';
    }

    public function createPix(PixRequestDTO $request): PixResponseDTO
    {
        // Implementação
    }

    // ... outros métodos
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

## Logs

Os logs são separados por canal:

- **gateway**: Requisições/respostas dos gateways
- **api**: Requisições da API

```bash
# Ver logs do gateway
docker compose exec app tail -f storage/logs/gateway.log

# Ver logs da API
docker compose exec app tail -f storage/logs/api.log
```

## Troubleshooting

### Container não inicia

```bash
docker compose down
docker compose up -d --build
```

### Erro de permissão

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

## Tecnologias

- PHP 8.2
- Laravel 11
- MySQL 8.0
- Docker & Docker Compose
- PHPUnit
- Laravel Sanctum (autenticação)
