# Osiris API

API REST em Laravel para o sistema de controle e planejamento financeiro pessoal (TCC). Autenticação via Laravel Sanctum (token Bearer), banco PostgreSQL.

## Domínio

- **Categorias** — organizam receitas e despesas, com cor para exibição visual. O tipo (receita/despesa) da transação é sempre o tipo da sua categoria — não existe um campo de tipo redundante na transação.
- **Contas** — nome e instituição financeira, sem dados bancários sensíveis. Toda transação pertence a uma conta (obrigatória, ver `specs/05-architecture.md`); o saldo de cada conta é calculado a partir das transações vinculadas a ela; remover uma conta remove as transações dela junto.
- **Transações** — lançamentos de entrada/saída vinculados a uma categoria (ou divididos entre várias, ver `transaction_splits`) e a uma conta (obrigatória), com comprovante opcional (foto do cupom).
- **Lançamentos recorrentes** — cadastrados uma vez (categoria, conta, valor, dia do mês), geram automaticamente uma transação normal todo mês.
- **Limites de gastos (spending limits)** — valor máximo de gasto por mês, opcionalmente restrito a uma categoria, com cálculo automático do valor já gasto, do percentual atingido e, pro mês corrente, de um valor sugerido de gasto diário pra não estourar. Ao estourar 100%, dispara uma notificação push (se o usuário tiver ativado); todo domingo à noite também dispara um resumo da semana (gasto total e categoria que mais pesou).
- **Metas de economia (savings goals)** — valor alvo que o usuário quer juntar, com progresso e contribuições manuais.
- **Relatórios** — exportação de um relatório mensal completo em PDF.
- **Índice de saúde financeira** — score de 0 a 100 combinando taxa de poupança, limites, metas e gastos atípicos do mês, calculado no `/api/dashboard/summary`.

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
| GET/POST | /api/savings-goals | Listar / criar meta de economia |
| PUT/DELETE | /api/savings-goals/{id} | Atualizar / remover meta de economia |
| GET/POST | /api/recurring-transactions | Listar / criar lançamento recorrente |
| PUT/DELETE | /api/recurring-transactions/{id} | Atualizar (inclusive pausar/retomar) / remover recorrência |
| POST/DELETE | /api/push-subscriptions | Registrar / remover inscrição de notificação push do navegador |
| GET | /api/reports/monthly | Baixar relatório mensal em PDF (`?month=YYYY-MM`) |
| GET | /api/dashboard/summary | Resumo do mês (totais, gastos por categoria, projeção de saldo, índice de saúde financeira) |
| GET | /api/dashboard/compare | Comparar totais/gastos por categoria de dois meses à escolha (`?month_a=YYYY-MM&month_b=YYYY-MM`) |

Detalhes completos de cada endpoint (formato de body, campos da resposta) em `specs/04-api-contract.md`.

## Notificações push (opcional)

Pra ativar o envio de notificação push quando um limite de gastos estoura, gere um par de chaves VAPID e configure no `.env`:

```bash
php -r "require 'vendor/autoload.php'; \$k = \Minishlink\WebPush\VAPID::createVapidKeys(); echo \$k['publicKey'].PHP_EOL.\$k['privateKey'].PHP_EOL;"
```

```
VAPID_SUBJECT=mailto:seu-email@example.com
VAPID_PUBLIC_KEY=<chave pública gerada>
VAPID_PRIVATE_KEY=<chave privada gerada>
```

A chave pública também precisa ir pro front-end (`VITE_VAPID_PUBLIC_KEY`). Sem essas variáveis, o resto do sistema funciona normalmente — só a notificação em si não é enviada.

## Configuração do front-end

O front-end (repositório separado) precisa apontar `VITE_API_URL` para esta API, e esta API precisa liberar a origem do front-end via `FRONTEND_URL` no `.env` (usado em `config/cors.php`).
