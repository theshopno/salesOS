# SalesOS kernel — hook contract

The authoritative list of events the `salesos` kernel fires. Add-on modules copy
hook names from this file rather than re-deriving them from `do_action()` call
sites. CodeIgniter's hook dispatcher matches on the literal string, so the name
has to be typed by hand on both sides — this document is what keeps the two ends
agreeing.

## Events

| Hook | Fired from | Payload | Fires when |
|---|---|---|---|
| `salesos_order_created` | `Salesos_model::import_order()` | `int $order_id` | A new order has been committed, from any channel (POS, WooCommerce, a future connector). |
| `salesos_order_confirmed` | `Salesos_model::trigger_status_hooks()` | `int $order_id` | An order moved into `confirmed`. Also fires during `import_order()` when an order arrives already confirmed, as POS sales do. |
| `salesos_order_cancelled` | `Salesos_model::trigger_status_hooks()` | `int $order_id` | An order moved into `cancelled`. |
| `salesos_stock_returned` | `Salesos_model::trigger_status_hooks()` | `int $order_id` | An order moved into `returned`. |

The payload is the order id alone — a subscriber that needs the order row or its
line items reads them back through the kernel's own methods, not by querying
`tblsalesos_orders` directly.

## Current subscribers

| Module | Hook | Handler |
|---|---|---|
| `fraudcheck` | `salesos_order_created` | `fraudcheck_handle_order_created` |
| `ordernotifier` | `salesos_order_created` | `ordernotifier_handle_order_created` |
| `ordernotifier` | `salesos_order_confirmed` | `ordernotifier_handle_order_confirmed` |
| `ordernotifier` | `salesos_order_cancelled` | `ordernotifier_handle_order_cancelled` |
| `inventory` | `salesos_order_confirmed` | `inventory_handle_order_confirmed` |
| `inventory` | `salesos_order_cancelled` | `inventory_handle_order_cancelled` |
| `inventory` | `salesos_stock_returned` | `inventory_handle_stock_returned` |

## When to use a hook, and when not to

Subscribe to a hook for a **side effect that reacts to something already
committed** — deducting stock, sending a notification, running a fraud check. By
the time a hook runs, the order exists and the transaction is closed, so a
subscriber cannot reject the thing that triggered it. Work that has to be able
to refuse (checking stock before taking payment, for instance) belongs at the
call site, before the order is created.

A **write that needs an immediate result** — creating an order, matching a
product — stays a direct call to the kernel's public model methods. That is the
correct pattern, not something to convert into an event.

## Naming

These names are frozen strings that live in two places at once. `salesos` here
is the module's system name, not the brand: if the product is ever rebranded,
these hook names should stay put and only the display name changes. The module
name constant is `SALESOS_MODULE_NAME`; use it for model/library/view paths,
where the loader accepts a variable.
