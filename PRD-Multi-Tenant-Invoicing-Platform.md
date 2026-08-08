# Product Requirements Document (PRD)
## Multi-Tenant Invoicing & Financial Management Platform

**Version:** 1.0
**Status:** Draft
**Owner:** [Your Name]
**Last Updated:** August 5, 2026

---

## 1. Overview

### 1.1 Problem Statement
Small and mid-sized companies (e.g. consumer product manufacturers, distributors) need a simple way to manage sales, invoicing, distributors, vendors, products, and finances — but most either use spreadsheets or expensive/overbuilt ERP software. This product provides a lightweight, branded, multi-tenant SaaS platform where each company gets its own isolated workspace to manage these operations, while a central admin (platform owner) controls onboarding, billing, and account status.

### 1.2 Product Vision
A single web application that multiple independent companies can sign up for and use to issue invoices, track distributor/vendor relationships, manage products, and view financial reports — each with their own branding (logo, company name) — while the platform owner administers all tenant accounts from a super-admin panel.

### 1.3 Goals
- Let companies self-onboard (or be onboarded by admin) and start invoicing within minutes.
- Give each company full data isolation from every other company on the platform.
- Give the platform owner full visibility and control over all tenant accounts.
- Ship a production-ready, secure, maintainable MVP fast, using a proven stack.

### 1.4 Non-Goals (for v1)
- Public REST API for third-party integrations (may be added later).
- Native mobile apps.
- Multi-currency support within a single company (single currency per company for v1).
- Payment gateway integration for online invoice payment (v1 tracks payments manually; online payment collection is a future phase).

---

## 2. Users & Roles

| Role | Description | Access Scope |
|---|---|---|
| **Super Admin** | Platform owner (you) | All companies, billing, platform settings |
| **Company Admin** | Owner/manager at a client company | Full access within their own company only |
| **Company Staff** | Employee at a client company | Limited/configurable access within their own company |

### 2.1 Role Permissions (v1)

| Action | Super Admin | Company Admin | Company Staff |
|---|---|---|---|
| Create/suspend company accounts | ✅ | ❌ | ❌ |
| View all companies' data | ✅ | ❌ | ❌ |
| Upload company logo/branding | ❌ | ✅ | ❌ |
| Invite/manage company staff | ❌ | ✅ | ❌ |
| Create invoices | ❌ | ✅ | ✅ |
| Manage customers/distributors | ❌ | ✅ | ✅ |
| Manage vendors | ❌ | ✅ | ✅ |
| Manage products | ❌ | ✅ | ✅ |
| View financial reports | ❌ | ✅ | Configurable |
| Record expenses | ❌ | ✅ | Configurable |

---

## 3. Core Features (v1 Scope)

### 3.1 Super Admin Panel
- Company account creation, suspension, and deletion
- View per-company usage stats (invoice count, storage used, last activity)
- Impersonate a company account for support purposes
- Platform-wide dashboard (total companies, total invoices issued, active vs inactive accounts)

### 3.2 Company Onboarding & Branding
- Company registration (self-serve or admin-created, with invite link)
- Logo upload
- Company name, address, tax ID, default currency symbol
- Assign initial Company Admin user

### 3.3 Dashboard (Company-facing)
Matches the reference layout already scoped:
- Welcome banner with company name
- Total balance summary
- Quick stats: Sales, Payments From Distributors, Expenses, Promotions
- Quick-action buttons: Add Invoice, Add Distributor, Add Vendor, Add Product
- Current date/time display

### 3.4 Customer / Distributor Management
- CRUD for distributors: name, contact info, address, running balance
- View distributor purchase/payment history
- Record payments received from distributors

### 3.5 Vendor Management
- CRUD for vendors: name, contact info, category
- Track amounts owed to vendors (for raw materials/purchases)

### 3.6 Product & Raw Materials Management
- CRUD for products: name, SKU, price, stock quantity
- CRUD for raw materials: name, unit, stock quantity, linked to products (optional, for manufacturers)

### 3.7 Invoicing
- Create invoice: select distributor, add line items (products, qty, price), auto-calculate totals
- Sequential, per-company invoice numbering
- Invoice statuses: Draft, Sent, Paid, Overdue, Cancelled
- Generate PDF (async, via queue job)
- Secure, authenticated PDF download (no public/guessable URLs)
- Email invoice to distributor (async, via queue job)

### 3.8 Payments
- Record payments against invoices (full or partial)
- Auto-update invoice status based on payment total
- Payment history per distributor

### 3.9 Expenses
- Record company expenses: category, amount, date, note
- Expense list/filter by date range and category

### 3.10 Financial Reports
- Sales summary (by date range, by product, by distributor)
- Outstanding balances (what's owed to the company, what the company owes vendors)
- Expense summary
- Exportable as PDF (async via queue)

### 3.11 File Storage
- Company logos, invoice PDFs, and any uploaded documents stored on the VPS filesystem
- Strict per-company folder isolation (`storage/app/public/{type}/{company_id}/...`)
- All sensitive files (invoice PDFs) served only through authenticated controller routes — never via direct public storage links

---

## 4. Technical Architecture

### 4.1 Stack

| Layer | Technology |
|---|---|
| Backend framework | Laravel (PHP) |
| Frontend | React, via Inertia.js (server-driven SPA, no separate API layer) |
| Database | PostgreSQL |
| Cache / Queue driver | Redis |
| Queue processing & monitoring | Laravel Horizon |
| PDF generation | `barryvdh/laravel-dompdf` (or Browsershot if more complex layouts are needed) |
| Auth | Laravel Sanctum (session-based, since Inertia keeps everything same-origin) |
| Hosting | Self-managed Hostinger VPS (Nginx + PHP-FPM + Postgres + Redis + Supervisor, all on one server) |

### 4.2 Multi-Tenancy Model
- **Shared database, shared schema** — every tenant-scoped table carries a `company_id` foreign key.
- Tenant isolation enforced via middleware (`IdentifyTenant`) that resolves the current user's `company_id` and via query scoping (global Eloquent scope or explicit checks) on every model.
- Rationale: simpler operations, lower cost, easier cross-tenant admin queries, and sufficient isolation for this scale. Can migrate a specific high-value client to a dedicated database later if ever required — not needed for v1.

### 4.3 Data Model (Core Tables)
```
companies         (id, name, logo_path, slug, currency, status, created_at)
users             (id, company_id, name, email, password, role)
customers         (id, company_id, name, contact_info, balance)
distributors      (id, company_id, name, contact_info, balance)
vendors           (id, company_id, name, contact_info, balance_owed)
products          (id, company_id, name, sku, price, stock_qty)
raw_materials     (id, company_id, name, unit, stock_qty)
invoices          (id, company_id, distributor_id, invoice_number, status, total, due_date)
invoice_items     (id, invoice_id, product_id, qty, unit_price, subtotal)
payments          (id, company_id, invoice_id, amount, method, paid_at)
expenses          (id, company_id, category, amount, note, spent_at)
```

### 4.4 Application Structure
- **Two distinct surfaces** within one codebase:
  - `/admin/*` routes and `Admin/` Inertia pages — super-admin only
  - Main app routes and `Company/` Inertia pages — tenant-scoped, used by company admins/staff
- Single Blade entry point (`app.blade.php`) bootstraps Inertia; all actual UI is React (`.jsx` pages under `resources/js/Pages`)

### 4.5 Background Jobs (Horizon Queues)
- `GenerateInvoicePdf` — renders and stores invoice PDF
- `SendInvoiceEmail` — emails invoice to distributor
- `UpdateCompanyLedger` — recalculates balances after payment/invoice events
- `GenerateFinancialReport` — builds exportable report PDFs

### 4.6 Deployment
- Single Hostinger VPS running Nginx, PHP-FPM, PostgreSQL, Redis, and Supervisor (for Horizon workers)
- Postgres and Redis bound to `127.0.0.1` only — never exposed publicly
- HTTPS via Let's Encrypt
- Firewall (UFW): only ports 80, 443, and a non-default SSH port open
- SSH key-only authentication, root login disabled, `fail2ban` enabled

---

## 5. Security Requirements

- All cross-tenant data access blocked at both the middleware and query level; every model query scoped by `company_id`.
- Invoice/document downloads served through authenticated, authorized controller routes — never via guessable static file URLs.
- File upload validation: MIME-type and content verification, size limits (e.g. 2MB for logos).
- Rate limiting on sensitive routes (e.g. PDF downloads: 30 requests/minute).
- `.env` secrets never committed; restrictive file permissions.
- Redis password-protected even on localhost-only binding.
- Dedicated, least-privilege PostgreSQL app user (not superuser).
- Automated nightly database backups pushed off-server (e.g. to S3/Backblaze), with periodic restore testing.
- Regular OS/package security updates.

---

## 6. Success Metrics (v1)

- Time from company signup to first invoice sent: target under 15 minutes.
- Zero cross-tenant data leakage incidents.
- Invoice PDF generation completes within 10 seconds (async, non-blocking to the user).
- Platform uptime target: 99.5%.

---

## 7. Open Questions / Decisions Needed

- **Billing model:** flat subscription per company, per-invoice fee, or tiered plans? Affects the `companies` table schema and needs deciding before admin billing screens are built.
- **Invoice numbering compliance:** does invoice format need to meet specific tax/legal requirements in the target country/countries?
- **Company subdomains:** will companies access via `companyname.yourapp.com` or a shared URL with account switching? Affects the `IdentifyTenant` middleware design.
- **Staff permission granularity:** is a single "Staff" role sufficient for v1, or do specific companies need custom permission sets (e.g. sales-only staff vs. full-access staff)?

---

## 8. Phased Build Plan

| Phase | Scope |
|---|---|
| 1 | Auth, company onboarding, multi-tenant scaffolding, logo/branding upload |
| 2 | Core CRUD: Customers/Distributors, Vendors, Products, Raw Materials |
| 3 | Invoicing (creation, PDF generation, email delivery) |
| 4 | Payments & Expenses tracking |
| 5 | Dashboard aggregation & Financial Reports |
| 6 | Super-Admin panel (company management, platform analytics) |
| 7 | Hardening: security review, backups, rate limiting, load testing |

---

## 9. Appendix: Reference Dashboard Layout

The company-facing dashboard (already scoped from design reference) includes:
- Top navigation: Dashboard, Sales, Products, Raw Materials, Distributors, Vendors, Finance
- Welcome header with company name and branding
- Total balance figure
- Quick stats row: Sales, Payments From Distributors, Expenses, Promotions
- Quick-action buttons: Add Invoice, Add Distributor, Add Vendor, Add Product
- Live date/time display
