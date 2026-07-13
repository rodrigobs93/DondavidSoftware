# AGENTS.md — Roni WhatsApp Order Parser

## Scope

These instructions apply to **any agent (OpenClaw / Roni or otherwise) that analyzes, classifies, or extracts WhatsApp customer messages** in this project for **Carnes Don David / Don David POS**.

## Mandatory reference

**Before analyzing any WhatsApp message, read and follow:**

> [`agents/roni/roni_order_reference.md`](agents/roni/roni_order_reference.md)

That file is the **single source of truth** for order classification, extraction, JSON output, and printing. It is built from real customer chats and contains owner-confirmed business decisions. **Do not improvise rules, invent products, or change the schema — if this AGENTS.md and the reference file ever disagree, the reference file wins.**

## Required workflow (defined in detail in the reference)

1. **Group messages first** — accumulate consecutive messages from the same customer using the 90–120 s silence window (§3) before classifying. Never classify message-by-message.
2. **Classify** the whole block as order / non-order / mixed using §4. Mixed blocks (payment + order, complaint + order) ARE orders; extra content goes to `customer_notes` and `mixed_content`.
3. **Extract** products, quantities, units, prep notes, delivery, date/time, and payment per §5, tolerating real phonetic spelling and shorthand ("5x5 de molida", "120.000 de sobrebarriga", "6 asar"). Always preserve `raw_text` per item.
4. **Apply the missing-info policy** (§6): missing quantity, unit, address, time, or payment **never blocks printing** — print with the corresponding ⚠ alert (§7.2).
5. **Output the JSON** exactly as the schema in §9 — all fields, no renames, no additions.
6. **Auto-print only when §8 holds:** `is_order == true AND confidence >= 0.75 AND items.length > 0 AND requires_manual_review == false`. Use the thermal ticket format in §10, always ending with `MENSAJE ORIGINAL:`.
7. **Sessions and addenda** (§7.3): additions/corrections merge into the open order and **reprint the full updated ticket**, with `is_addendum: true`.
8. **Manual review** (§7.4): confidence < 0.75, unintelligible blocks, special-invoice favors, price/credit negotiations, conditional or third-party requests → `requires_manual_review: true`, never print.

## Hard rules (owner-confirmed — never override)

- Payment confirmations, price questions, `*RECIBO*` messages, and greetings **never** generate a ticket (§4.2).
- Missing address **never** sends an order to manual review (§5.5).
- Invoice-favor requests are **never** processed automatically (§7.4).
- Privacy (§12): never copy real names, phones, addresses, or account numbers into logs, examples, or training material — use the `[CUSTOMER_NAME]`-style placeholders. Real customer data appears only on the operational printed ticket.

## Validation

When testing changes to Roni's behavior, run against the real anonymized test cases in §11 (A: print, B: print with alert, C: never print, D: manual review) and confirm every case still produces the expected outcome.
