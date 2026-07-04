# KARYA — ERP for Indonesian EPC Contractors

A Laravel 11 **modular monolith** for Engineering–Procurement–Construction contractors in Indonesia: project cost control, *termin* progress billing with retention and *uang muka*, and Indonesian construction tax (PPh final, PPN/e-Faktur, PSAK 72) — built to run on a cheap VPS, on-premises, or hybrid.

This repository is **Pass 1**: the architectural spine plus the two highest-risk pieces (the double-entry ledger and the construction-tax engine) built to depth with tests, and one end-to-end "money path" vertical slice. See `docs`-equivalent strategy in the accompanying blueprint. What is deferred is listed at the bottom.

> **Provenance note.** The domain layer was authored and its invariants verified with the PHP CLI (`php bin/domain-tests.php`, **23/23 green** as of Pass 3). As of Pass 2 the full stack is validated too: `composer check` (Pint + PHPStan L8 + deptrac + Pest) runs green against a real PostgreSQL database; Pass 3 adds four feature tests (commitment loop, GR/IR accrual, month-end close, e-Faktur) the team runs there. Pass 2 also fixed three latent wiring defects Pass 1 shipped unvalidated — see *Pass 2* below.

## Architecture in one screen

```
app-modules/
  platform/     Foundation: companies (+ KSO substrate), numbering, event outbox,
                audit, custom fields, Money value object, base Action
  finance/      Double-entry GL, PostingRuleEngine, PSAK 72, fiscal periods
  tax/          PPh final konstruksi (PP 9/2022 + transitional), PPN, e-Faktur outbox
  projects/     Projects, WBS tree, versioned BOQ, control budget
  billing/      Progress claims, Berita Acara chain, termin engine  ← AR money path
  payables/     Vendor/subcontractor bills, PPh-final withholding   ← AP money path (Pass 2)
  procurement/ inventory/ receivables/ reporting/   (schema + facts; logic lands later)
```

**The dependency law** (enforced by `deptrac` + Pest arch tests, so a build fails rather than a reviewer catching it): domain modules → `{Finance, Tax}` → `Platform`. Cross-module **writes** happen only through the transactional **outbox**, never by one module touching another's models. Domain code imports zero Filament, so the UI layer is replaceable.

**The one seam worth understanding:** a domain module never writes a journal. It publishes a typed *fact* to the outbox (e.g. `billing.progress_invoice_issued`); the relay hands it to Finance's `PostingRuleEngine`, which maps it to a **balanced** journal using per-company account mappings (`fin_posting_rules` — data, not code). All GL logic lives in one module; every customer keeps their own chart of accounts.

## Verify the crown jewels now (no Composer needed)

```bash
php bin/domain-tests.php
```

Runs the pure-domain suite with just the PHP CLI: Money allocation/rounding, the **PPh-final resolver incl. the pre-2022 transitional rule**, the **double-entry balance invariant**, the **termin money path** and its payables mirror the **subcontractor-bill money path** (each posting a balanced journal), and **PSAK 72** cost-to-cost recognition — plus the Pass 3 legs: **budget control** (OK/WARN/BLOCK), the **GR/IR accrual** balance, **moving-average** valuation, **three-way match**, the **PSAK 72 month-end true-up**, and the **e-Faktur** XML + submission-status guard. Expected: `23 passed, 0 failed`.

## Full stack setup

Requires PHP 8.3, Composer, PostgreSQL 16 (Redis optional).

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class="Modules\\Tax\\Database\\Seeders\\PphFinalRateSeeder"

composer check     # pint + phpstan(L8) + deptrac + pest
vendor/bin/pest    # includes the end-to-end money-path feature test
```

### The money path, end to end

`tests/Feature/TerminMoneyPathTest.php` proves the whole architecture against a real database: a `ProgressClaim` for Rp 1.000.000 →

| Component | Amount | Rule |
|---|---:|---|
| Work value (DPP) | 1.000.000 | certified this termin |
| PPN output (11%) | 110.000 | `PpnCalculator` |
| Retensi (5%) | −50.000 | `TerminCalculator` |
| Uang muka recovery (20%) | −200.000 | `TerminCalculator` |
| PPh final (2.65%, EPC medium SBU) | −26.500 | `PphFinalRateResolver` (PP 9/2022) |
| **Net receivable** | **833.500** | |

→ published to the outbox → relayed → posted as a **balanced** journal (debits = credits = 1.110.000).

## Deployment

One image (`docker/Dockerfile`, FrankenPHP), two profiles:

```bash
docker compose -f docker/compose.2gb.yml up   # minimal: no Redis/Gotenberg, DB queue — the floor
docker compose -f docker/compose.4gb.yml up    # standard: Redis + Horizon + Gotenberg — the contractual floor we sell
```

The server always runs at HQ/VPS, never at the project site; site capture is a separate offline-tolerant PWA (Pass 2). Air-gapped offices get `docker save` tarballs applied by the `epcctl` upgrade CLI (Pass 2).

## What Pass 1 delivers vs. defers

**Delivered in Pass 1 (with tests):** Platform foundation · Finance double-entry core + PostingRuleEngine + PSAK 72 · Tax PPh-final (incl. transitional rule) + PPN + e-Faktur outbox schema · Projects (WBS/BOQ/budget) · the Billing→outbox→Finance money path · deptrac/phpstan/pest/docker/CI tooling.

## What Pass 2 adds

**The procure-to-pay money path — the mirror of the termin path, on the payables side.** A subcontractor bill decomposes into work value + PPN input − retensi − PPh-final withheld (the contractor now the *withholder*, reusing the very same `PphFinalRateResolver` the billing side uses), publishes a `payables.vendor_bill_approved` fact to the outbox, and Finance's new `VendorBillPostingRule` turns it into a **balanced accrual** — proving the outbox seam holds symmetrically. Covered by `tests/Feature/SubcontractPayablePathTest.php`, two new cases in `bin/domain-tests.php`, and the now-explicit outbox **idempotency** test.

**Three latent Pass-1 wiring defects, found by actually running the stack and fixed:**
- **Outbox availability race** — `event_outbox.available_at` is `timestamp(0)`, so the DB default `CURRENT_TIMESTAMP` *rounded* the sub-second part and could push a just-published event's availability into the next second, leaving the relay unable to claim it (the money-path test was silently flaky). `Outbox::publish()` now floors `available_at` to the current second.
- **deptrac collector type** — the config used `type: className`, unsupported by the installed deptrac; corrected to `classNameRegex` with delimited patterns, so the module dependency law is enforced again.
- **Module provider/autoload registration** — module service providers and PSR-4 namespaces are now registered explicitly (`bootstrap/providers.php`, root `composer.json` autoload) rather than depending on the composer-merge-plugin having run, which it did not.

The whole codebase is now Pint-clean and PHPStan-L8-clean (Pass 1 shipped with 41 static-analysis findings; all resolved).

## What Pass 3 adds

**Four legs, all over schema Passes 1–2 already laid — the behaviour layer that was missing.**

- **Cost-control commitment loop** (the "C" of EPC, killer workflow #1) — a PO is gated against the project control budget (`BudgetControlPolicy`: available = budget − open commitments − actuals), and on approval raises a commitment through the outbox to Finance's `CommitmentProjector`. Receiving goods (`ReceiveGoods`) consumes that commitment, books a **balanced GR/IR accrual** (`GrnPostingRule`: Dr Inventory/WIP · Cr GR/IR), and moves stock at **moving-average** cost (`MovingAverageValuation` → the append-only stock ledger). `ThreeWayMatch` (PO ↔ GRN ↔ bill) is built and tested.
- **Month-end close** (the highest-risk workflow) — `CloseFiscalPeriod` recognises **PSAK 72** revenue across active projects (cost-to-date from the GL; commercials supplied by the caller, keeping Finance below Projects/Billing in the dependency law), posts the period true-up (`Psak72PostingRule`: contract asset / contract liability) **through the engine while the period is still open**, records an auditable `fin_revrec_runs` row, then **hard-locks** the period — after which `FiscalPeriodGuard` refuses any late post. `ReopenFiscalPeriod` lifts the lock for a controlled correction.
- **e-Faktur** — `EfakturXmlBuilder` (pure, self-checking DPP/PPN totals) produces the Coretax tax-invoice document; `QueueEfaktur` enqueues one the moment a termin is issued (idempotent on the claim); `EfakturSubmissionStatus` guards the Queued→Sent→Acked lifecycle; `CoretaxChannel` transmits it (team-verified against a sandbox).
- **Filament UI spine** — the first replaceable UI, in the **app layer** (`app/Filament`), never inside a module, so "domain is Filament-free" holds without exception. Resources for Project, Purchase Order (+ *Approve*, budget-gated), Vendor Bill (+ *Approve*), Progress Claim (+ *Issue Termin*), a read-only Journal viewer, Fiscal Period (+ *Close*), and a budget-vs-committed-vs-actual dashboard widget. Every screen calls an Action — no business logic in the UI.

**The seam held symmetrically again.** The outbox relay now fans one fact out to a *set* of consumers (`Platform\Support\OutboxConsumer`) — the posting engine plus projections (commitment ledger, stock ledger, e-Faktur queue) — all inside its one per-event transaction, and the `domain → {Finance, Tax} → Platform` law stayed green (Finance never imports a domain module; each consumer names its upstream fact type as a local string).

**Verified in-session:** `php bin/domain-tests.php` → **23 passed** (up from 16): budget OK/WARN/BLOCK, GR/IR balance, moving-average blend + drain, three-way match, PSAK 72 true-up (asset & liability), e-Faktur XML bytes + status guard. **Team-verified after `composer install`:** feature tests `PurchaseOrderCommitmentPathTest`, `GoodsReceiptAccrualPathTest`, `MonthEndCloseTest`, `EfakturSubmissionTest`, plus `composer check` (Pint + PHPStan L8 + deptrac + Pest) and a Filament smoke.

**Deferred to Pass 4+:** wiring the vendor-bill posting to *clear* GR/IR when a bill references a PO (the `ThreeWayMatch` domain is ready; the GL clearing variant is not yet wired) · Filament multi-company/tenant scoping (the panel is a single-company spine) · payment batches · a BOQ-management resource (same CRUD pattern) · e-Faktur NPWP enrichment/resolver · native payroll, tender, equipment, doc-control, HSE · the offline PWA · the `epcctl` upgrade CLI.

## License

Proprietary. No GPL/Frappe code — clean-room greenfield, so on-prem distribution carries no source-conveyance obligation.
