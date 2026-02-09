You are implementing a Payout feature inside an existing Laravel + DDD fintech backend.

⚠️ CRITICAL RULES (NON-NEGOTIABLE)

You MUST strictly follow the existing architecture and patterns already used in:

Payments

Ledger postings

Provider integrations

Actions/Services structure

Domain boundaries

DO NOT:

invent new patterns

introduce new architecture styles

mix responsibilities

write logic in controllers

bypass LedgerService

bypass Provider facade

write raw SQL

directly mutate balances

create “quick shortcuts”

Everything MUST look and feel identical to existing payment flow code.

If unsure, COPY the same structure used by Payments and adapt.

Consistency > creativity.

Business Requirements

Implement Payouts (money leaving the system).

Definition

A payout:

deducts money from a wallet/business account

sends funds to an external bank account via a provider

records double-entry ledger postings

supports test and live environments safely

Core Rules
1. Ledger First (MANDATORY)

Payout MUST use double-entry accounting.

NEVER update balances directly.

Always:

create Transaction

create LedgerEntries via LedgerService

Entries must balance to zero.

Example:

Debit: Business/Wallet Account
Credit: Provider/Clearing/Payable Account

2. Mode Isolation (VERY IMPORTANT)

System has:

test mode

live mode

Payout must NEVER send real money in test mode.

Rules:

IF mode = test:

DO NOT call live provider APIs, call test providers

still create ledger postings

mark transaction as test

IF mode = live:

call real provider

This must be enforced at the service layer, not controller.

3. Provider Mode Filtering

Providers should have:

mode = test | live


When selecting provider:

ALWAYS filter:

payout method

active = true

mode = current transaction mode

Example:

live payout → only live providers
test payout → only test providers

Never mix.

4. Required Architecture (Follow exactly)

Use the same structure already used in Payments:

Create:

Domains/Payouts/

Actions/
CreatePayout.php
ProcessPayout.php
RecordPayoutLedgerPostings.php

Services/
PayoutService.php

DTOs/

Enums/

Exceptions/

Follow same naming conventions and coding style as Payments domain.

5. Flow (strict sequence)

Implement payout in this order ONLY:

Step 1 — Create payout transaction

status: pending

mode: test/live

amount

wallet/account

provider_id nullable

Step 2 — Select provider

filter by mode + method + active

Step 3 — Ledger posting (reserve funds)

debit wallet

credit payout clearing

Step 4 — Call provider

test → fake success

live → real API call

Step 5 — Update transaction status

success → completed

failure → failed + reversal ledger

Step 6 — On failure

reverse ledger using LedgerService

NEVER delete entries

6. Database Requirements

Follow existing schema conventions.

Add:

payouts table:

id

transaction_id

account_id

provider_id

amount

mode (test|live)

status

reference

metadata

timestamps

providers table:

mode column (test|live)

transactions:

must already support mode

ledger_entries:

must inherit transaction mode

7. Coding Constraints

Must:

use Actions pattern

use Services for orchestration

use Provider facade

use DB transactions

use strict types

follow existing namespace structure

follow existing enums

follow existing exception style

Must NOT:

write business logic in controller

directly adjust balances

skip ledger

mix test/live logic inside controllers

hardcode provider

8. Safety Rules

Absolutely prevent:

❌ sending live money in test
❌ posting ledger without transaction
❌ unbalanced entries
❌ mixing environments
❌ silent failures

Throw explicit exceptions.

9. Deliverables

Generate:

migrations

models

enums

actions

service

provider selection logic

ledger posting class

example usage

fully typed PHP code

Match the style used in existing Payments code exactly.