# Financeiro API

API REST em Laravel para o sistema de controle e planejamento financeiro pessoal (TCC). Autenticação via Laravel Sanctum (token Bearer), banco PostgreSQL.

## Domínio

- **Categorias** — organizam receitas e despesas, com cor para exibição visual. O tipo (receita/despesa) da transação é sempre o tipo da sua categoria — não existe um campo de tipo redundante na transação.
- **Contas** — nome e instituição financeira, sem dados bancários sensíveis. Transações podem opcionalmente ser associadas a uma conta; o saldo de cada conta é calculado a partir das transações vinculadas a ela.
- **Transações** — lançamentos de entrada/saída vinculados a uma categoria (obrigatória) e a uma conta (opcional).
- **Limites de gastos (spending limits)** — valor máximo de gasto por mês, opcionalmente restrito a uma categoria, com cálculo automático do valor já gasto e do percentual atingido.

## Rodando com Docker (recomendado)

Requer Docker e Docker Compose instalados.

```bash
cp .env.example .env
```

Edite o `.env` e defina `DB_HOST=db` (nome do serviço no `docker-compose.yml`).

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

A API sobe em `http://localhost:8000`. Um usuário de teste é criado pelo seeder: `test@example.com` / `password`.

## Rodando localmente (sem Docker)

Requer PHP 8.3+, Composer e PostgreSQL.

```bash
composer install
cp .env.example .env
php artisan key:generate
# ajuste DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD no .env
php artisan migrate --seed
php artisan serve
```

## Autenticação

1. `POST /api/auth/register` ou `POST /api/auth/login` retornam `{ user, token }`.
2. Envie o token nas próximas requisições: `Authorization: Bearer <token>`.
3. `POST /api/auth/logout` revoga o token atual.

## Endpoints principais

Todos (exceto register/login) exigem o header `Authorization: Bearer <token>`.

| Método | Rota | Descrição |
|---|---|---|
| GET | /api/auth/me | Usuário autenticado |
| GET/POST | /api/categories | Listar / criar categoria |
| PUT/DELETE | /api/categories/{id} | Atualizar / remover categoria |
| GET/POST | /api/accounts | Listar (com saldo calculado) / criar conta |
| PUT/DELETE | /api/accounts/{id} | Atualizar / remover conta |
| GET/POST | /api/transactions | Listar (filtros `month`, `category_id`, `type`) / criar transação |
| PUT/DELETE | /api/transactions/{id} | Atualizar / remover transação |
| GET/POST | /api/spending-limits | Listar (filtro `month`) / criar limite de gastos |
| PUT/DELETE | /api/spending-limits/{id} | Atualizar / remover limite de gastos |

## Configuração do front-end

O front-end (repositório separado) precisa apontar `VITE_API_URL` para esta API, e esta API precisa liberar a origem do front-end via `FRONTEND_URL` no `.env` (usado em `config/cors.php`).
