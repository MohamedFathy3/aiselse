# Pyramidth CRM API

Laravel API for the Pyramidth freight-forwarding sales CRM. The backend remains the source of truth for authentication, ownership, validation, conversion rules, and authorization.

## Implemented MVP

- Sanctum SPA authentication with role-aware Admin and Sales users.
- Dashboard metrics scoped to the authenticated user's ownership.
- Lead listing, filtering, creation, update, duplicate warning, and Lead → Client conversion.
- Client, contact, agent, shipment, and follow-up endpoints with validation.
- Server-generated shipment reference numbers.
- Lead-search records that fail safely with an explicit configuration message until provider credentials are configured.
- Existing policies, enums, migrations, seeders, and audit-oriented schema are preserved.

## Local setup

1. Copy `.env.example` to `.env`, set `APP_KEY`, MySQL credentials, `FRONTEND_URL`, and `SANCTUM_STATEFUL_DOMAINS`.
2. Install dependencies with `composer install`.
3. Run `php artisan key:generate`, `php artisan migrate --seed`, and `php artisan serve`.
4. Configure the Next.js BFF to point at the Laravel URL in `app/lib/bff-proxy.ts`.

## Provider configuration

Web research and AI processing must be implemented behind server-side services/jobs and enabled with `WEB_SEARCH_API_KEY`, `AI_API_KEY`, and their provider settings. Gmail and Calendar must use the existing Google OAuth server-side variables. No provider secret belongs in the frontend or in Git.

## Main endpoints

All protected endpoints are under `/api/v1` and require the Sanctum session: `/dashboard`, `/leads`, `/leads/{lead}/convert`, `/clients`, `/contacts`, `/agents`, `/shipments`, `/follow-ups`, and `/lead-searches`.
