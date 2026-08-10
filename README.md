# Financeiro API

API REST em Laravel para o sistema de controle e planejamento financeiro pessoal (TCC). Autenticação via Laravel Sanctum (token Bearer), banco PostgreSQL.

## Domínio

- **Categorias** — organizam receitas e despesas, com cor para exibição visual.
- **Transações** — lançamentos de entrada/saída vinculados a uma categoria.
- **Metas orçamentárias (budgets)** — limite de gasto por mês, opcionalmente por categoria, com cálculo automático do valor já gasto.

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
| GET/POST | /api/transactions | Listar (filtros `month`, `category_id`, `type`) / criar transação |
| PUT/DELETE | /api/transactions/{id} | Atualizar / remover transação |
| GET/POST | /api/budgets | Listar (filtro `month`) / criar meta |
| PUT/DELETE | /api/budgets/{id} | Atualizar / remover meta |

## Configuração do front-end

O front-end (repositório separado) precisa apontar `VITE_API_URL` para esta API, e esta API precisa liberar a origem do front-end via `FRONTEND_URL` no `.env` (usado em `config/cors.php`).
