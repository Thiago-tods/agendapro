# AgendaPro

[Português](README.md) · [English](README.en.md)

Plataforma multiempresa de agendamento e gestão para negócios de serviços: clínicas, barbearias, consultorias, estúdios e oficinas.

> Status: MVP em desenvolvimento. Ainda não está pronto para produção (veja [Limitações conhecidas](#limitações-conhecidas)).

## O problema

Negócios de serviços costumam controlar a agenda em planilhas, cadernos ou mensagens. Isso gera horários sobrepostos, atendimentos fora do expediente, clientes sem histórico e pouca visão do faturamento. O AgendaPro centraliza agenda, clientes, equipe e catálogo, e impede conflitos de horário no próprio banco de dados.

## Funcionalidades

- **Agenda visual** com visões de dia, semana e mês, e filtros por profissional e serviço.
  - Os blocos têm altura proporcional à duração e mostram o intervalo após o atendimento.
  - Mostra expediente, bloqueios e o status de cada agendamento, sempre com texto e não só com cor.
- **Agendamentos:** criar, confirmar, remarcar, fazer check-in, concluir, marcar falta e cancelar (com motivo). Cada mudança fica registrada no histórico.
- **Regras de disponibilidade:**
  - Expediente da empresa e horários de cada profissional.
  - Exceções por data e bloqueios de horário.
  - Duração mais intervalo de preparo, em intervalos semiabertos `[início, fim)`.
- **Proteção contra conflitos:** constraint `EXCLUDE` do PostgreSQL mais bloqueio de linha na transação. Dois agendamentos simultâneos para o mesmo profissional não passam, nem com requisições concorrentes.
- **Painel** com indicadores operacionais e gerenciais calculados a partir de dados reais.
- **Clientes:** busca, filtros, perfil, histórico de visitas e arquivamento.
- **Profissionais:** cadastro, serviços realizados, horários e ativação/desativação.
- **Serviços:** catálogo com preço, duração, intervalo, categoria, cor e profissionais associados.
- **Configurações:** dados da empresa, expediente, equipe com papéis (proprietário, administrador, recepcionista, profissional), perfil e troca de senha.
- **Multiempresa:** cada empresa é isolada. O acesso é validado pela participação do usuário em cada requisição; a URL sozinha não dá acesso.

## Capturas de tela

Dados fictícios gerados pelo seeder de demonstração.

**Painel**: indicadores do dia, próximos agendamentos e visão dos últimos 30 dias.

![Painel](docs/screenshots/painel.png)

**Agenda semanal** filtrada por profissional, com expediente, intervalos e status.

![Agenda semanal](docs/screenshots/agenda-semana.png)

**Detalhes do agendamento**: ações permitidas no momento e histórico.

![Detalhes do agendamento](docs/screenshots/agendamento.png)

**Clientes**: busca, visitas, última visita e próximo horário.

![Clientes](docs/screenshots/clientes.png)

**Mobile**: a agenda abre na visão do dia.

<img src="docs/screenshots/mobile-agenda.png" alt="Agenda do dia no celular" width="320">

## Stack

| Camada | Tecnologias |
|---|---|
| Backend | PHP 8.4, Laravel 13, Laravel Sanctum (sessão por cookie para SPA), PHPUnit |
| Banco | PostgreSQL 18 (extensões `btree_gist` e `pg_trgm`) |
| Frontend | Vue 3, TypeScript, Vite, Vue Router, Pinia, Tailwind CSS 4, Vitest |

## Arquitetura

Monólito modular com dois aplicativos separados:

```text
backend/    API REST Laravel em /api/v1, organizada por módulos de negócio
  app/Modules/{Auth, Companies, Customers, Professionals, Services,
               Availability, Appointments, Dashboard}
frontend/   SPA Vue, também organizada por módulos
  src/modules/{auth, dashboard, appointments, customers, professionals,
               services, settings, account}
```

- **Autenticação:** Sanctum em modo SPA, com cookie de sessão HttpOnly e proteção CSRF. Nenhum token é guardado no navegador.
- **Multiempresa:** as rotas `/api/v1/companies/{company}/...` passam pelo middleware `company.member` e usam bindings com escopo. As chaves estrangeiras compostas `(company_id, id)` impedem referências entre empresas.
- **Autorização:** policies e Form Requests no backend. O frontend apenas esconde ações, a partir das permissões enviadas pela API.
- **Horários:** gravados em UTC e exibidos no fuso da empresa. A API já envia o horário local, então o navegador não faz conversão de fuso.

## Requisitos

- PHP 8.4 com as extensões `pdo_pgsql`, `mbstring`, `openssl`, `intl`
- Composer 2
- PostgreSQL 18, com as extensões `btree_gist` e `pg_trgm` (distribuídas no pacote `contrib` padrão)
- Node.js `^22.18` ou `>=24.12`, com npm

## Configuração do banco

Crie dois bancos, um para desenvolvimento e outro para testes:

```sql
CREATE DATABASE agendapro;
CREATE DATABASE agendapro_test;
```

As extensões `btree_gist` (proteção contra conflito de horários) e `pg_trgm` (índices de busca) precisam estar disponíveis no servidor. As migrations executam `CREATE EXTENSION IF NOT EXISTS` para ambas, então basta o usuário do banco ter permissão para criá-las; em serviços gerenciados sem essa permissão, crie-as antes com `CREATE EXTENSION btree_gist; CREATE EXTENSION pg_trgm;` no banco de desenvolvimento e no de testes.

## Executando

### Backend

```bash
cd backend
composer install
cp .env.example .env          # PowerShell: Copy-Item .env.example .env
# Edite .env: DB_USERNAME e DB_PASSWORD
php artisan key:generate
php artisan migrate --seed    # --seed cria dados de demonstração
php artisan serve             # http://localhost:8000
```

### Frontend

```bash
cd frontend
npm ci
npm run dev                   # http://localhost:5173
```

O Vite encaminha `/api` e `/sanctum` para `http://localhost:8000`, então o navegador fala com uma única origem.

### Usuários de demonstração

O seeder cria a empresa "Studio Aurora" com dados fictícios. Todos os usuários usam a senha `password` e servem **apenas para desenvolvimento local**:

| Papel | E-mail |
|---|---|
| Proprietário | `owner@agendapro.test` |
| Administrador | `admin@agendapro.test` |
| Recepcionista | `recepcao@agendapro.test` |
| Profissional | `profissional@agendapro.test` |

## Testes

```bash
# Backend: usa o banco agendapro_test (veja phpunit.xml)
cd backend
php artisan test
vendor/bin/pint --test        # estilo do código

# Frontend
cd frontend
npx vitest run
npm run lint                  # ESLint
npm run build                 # type-check e build de produção
```

## Limitações conhecidas

- Sem testes E2E com Playwright. O Prettier está configurado (`npm run format`), mas o código existente ainda não foi reformatado.
- Exceções por data e bloqueios de horário existem na API, mas ainda não têm tela.
- Sem envio de e-mail:
  - Membros novos recebem uma senha inicial definida pelo administrador, sem troca obrigatória no primeiro acesso.
  - Adicionar um e-mail que já tem conta revela que a conta existe.
- O botão de check-in aparece fora da janela permitida; a API recusa e mostra a mensagem.
- A disponibilidade da agenda é calculada com consultas por dia e por profissional, e a busca usa `ilike` sem índice de trigramas. Pode ficar lento com volumes grandes.
- Telefones sem máscara durante a digitação. O profissional não fica vinculado a um usuário.
- Sem pagamentos, assinaturas, integração com calendários externos ou portal público de reservas.

## Licença

[MIT](LICENSE) © 2026 Thiago Oliveira da Silva.
