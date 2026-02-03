# .ai/guidelines.md

## Purpose of This Document

This file exists to guide **Laravel Boost AI agents** (and any automated assistants) to correctly understand **what this project is**, **how it should be built**, and **what rules must not be violated**.

This is a **fintech-grade payment and reconciliation system**, not a demo app.

AI agents must prioritize **correctness, auditability, and financial safety** over speed or convenience.

---

## Project Overview

This project is a **Fintech Reconciliation & Payment Orchestration System** built with **Laravel**.

The system:

* Orchestrates payments across multiple providers (e.g. card, transfer, wallet)
* Maintains an internal **ledger-based accounting system**
* Reconciles internal records with external provider statements
* Handles webhook delays, retries, and failures safely
* Supports manual verification with full audit trails

End users **do not choose payment providers**. The system selects providers dynamically based on availability, cost, and health.

---

## Non‑Goals (Important)

AI agents must NOT:

* Build a "simple payment flow" without a ledger
* Update balances directly without ledger entries
* Assume webhooks are reliable or immediate
* Couple provider logic directly to controllers
* Treat payment status as a single boolean

This system is **stateful, event‑driven, and reconciliation‑first**.

---

## Core Architectural Principles

### 1. Ledger‑First Architecture (Non‑Negotiable)

All monetary movement MUST be recorded as **immutable ledger entries**.

Rules:

* No deletion or mutation of ledger rows
* Every transaction has debit and credit entries
* Balances are **derived**, not blindly stored
* Ledger is the source of truth, not providers

If an AI agent proposes direct balance mutation without a ledger entry, that proposal is invalid.

---

### 2. Domain‑Driven Structure

Business logic is organized by **domain**, not by MVC convenience.

Preferred structure:

* `Domains/Ledger`
* `Domains/Payments`
* `Domains/Reconciliation`
* `Domains/Wallets`
* `Domains/Providers`

Controllers should be thin. Domains own the logic.

---

### 3. Payment Orchestration Model

Payments follow a multi‑step lifecycle:

* PaymentIntent (what the user wants to do)
* PaymentAttempt (each provider try)
* ProviderTransaction (external reference)

Rules:

* One intent can have multiple attempts
* Attempts must be idempotent
* Provider failures must not corrupt internal state

AI agents must model payments as **state machines**, not linear flows.

---

### 4. Provider Abstraction Layer

External providers (e.g. Paystack, AlatPay, Polaris) must be isolated behind adapters.

Rules:

* No provider SDK calls inside controllers
* No provider‑specific fields leaking into core domains
* Providers are replaceable without rewriting business logic

---

### 5. Webhook Safety Rules

Webhooks are NOT trusted as the sole source of truth.

Rules:

* Webhooks can be delayed, duplicated, or missing
* All webhook handlers must be idempotent
* State changes must be validated against internal expectations
* Polling / verification jobs must exist

If an AI agent assumes "webhook = final truth", that is incorrect.

---

### 6. Reconciliation Is a First‑Class Feature

Reconciliation is not a report — it is a **core system capability**.

The system must support:

* Internal vs provider transaction matching
* Partial matches
* Missing transactions
* Excess provider entries
* Manual reconciliation with audit logs

Reconciliation logic must live in its own domain.

---

### 7. Idempotency Everywhere

Idempotency keys are REQUIRED for:

* Payment creation
* Webhooks
* Manual verification actions
* Retry jobs

AI agents must always ask: "What happens if this runs twice?"

---

### 8. Auditability & Traceability

Every sensitive action must be traceable:

* Who triggered it
* When it happened
* Why it happened
* What changed

Manual overrides must never silently mutate financial data.

---

## Laravel‑Specific Expectations

AI agents should:

* Prefer Jobs, Events, and Listeners over fat controllers
* Use database transactions carefully and explicitly
* Avoid magic helpers that hide financial behavior
* Write testable, deterministic domain logic

Laravel is a tool, not the architecture.

---

## Starter Kit Usage

This project uses **Nuno Maduro’s Laravel Starter Kit**.

It is acceptable for:

* Auth
* Base project setup
* Testing defaults
* Developer experience

It must NOT dictate fintech architecture decisions.

---

## AI Agent Behavior Rules

When generating code or suggestions:

* Ask "Is this financially safe?"
* Ask "Is this idempotent?"
* Ask "Can this be audited later?"
* Prefer correctness over speed
* Prefer explicitness over magic

If unsure, choose the **more conservative** approach.

---

## Final Note

This system may look complex, but financial systems punish shortcuts.

AI agents are expected to behave like **senior fintech engineers**, not tutorial writers.

If a suggestion feels "too simple" for money movement, it probably is.
