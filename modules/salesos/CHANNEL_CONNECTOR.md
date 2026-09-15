# Connecting a storefront to SalesOS

A connector is a small class. Everything that is the same whatever the platform —
paging, recognising an order already imported, matching its products and
customer, importing it, holding it for a confirmation call, following the store's
own status afterwards — lives in `Salesos_channel`. A connector supplies the
three things that genuinely differ between WooCommerce, Shopify and a custom
Laravel site.

`Woocommerce_channel` is the worked example; it is about 150 lines.

## What you write

```php
require_once __DIR__ . '/../../salesos/libraries/Salesos_channel.php';

class Shopify_channel extends Salesos_channel
{
    public function platform_key(): string   { return 'shopify'; }
    public function platform_label(): string { return 'Shopify'; }

    public function fetch_orders(array $site, int $page)
    {
        // Return one page of raw orders, or false if the call genuinely failed.
        // An empty array means "no more orders" — not the same thing, and it
        // must not be reported as an error.
    }

    public function translate_order(array $raw, array $site): array
    {
        // Turn one raw order into the generic shape below.
    }
}
```

The base class is abstract, so CI's library loader cannot bring it in — it would
try to instantiate it. `require_once` it directly, as above.

## The generic order shape

`translate_order()` returns this. Nothing platform-specific survives past it.

```php
[
    'external_id'     => '1042',        // the store's own order id, as a string
    'external_status' => 'processing',  // the store's own status, kept verbatim
    'status'          => 'pending',     // kernel status: pending|confirmed|cancelled
    'subtotal'        => 600.00,
    'shipping_charge' => 60.00,
    'total'           => 660.00,
    'currency'        => 'BDT',
    'payment_method'  => 'Cash on delivery',
    'order_note'      => '',
    'order_date'      => '2026-09-15 11:04:00',
    'customer' => [
        'phone' => '01712345678', 'name' => 'Rahim Uddin', 'email' => '',
        'address' => 'Mirpur 10, Dhaka', 'city' => 'Dhaka', 'state' => '',
        'zip' => '1216', 'country_code' => 'BD',
    ],
    'items' => [
        [
            'sku' => 'TS-RED-M', 'name' => 'Red T-shirt (M)',
            'qty' => 2, 'unit_price' => 300.00,
            // optional, used only when the product is created for the first time
            'price' => 300.00, 'category_name' => 'T-shirts',
            'image_url' => 'https://…/red-m.jpg', 'external_id' => 501,
        ],
    ],
]
```

Keep `external_status` verbatim. It is what the person making the confirmation
call sees, and following it is how a cancellation in the shop reaches us.

## Statuses

Map the store's vocabulary onto three kernel statuses. Anything you do not
recognise should be `pending` — it is safer to hold an order for a call than to
release one that should not have shipped.

| kernel | meaning | WooCommerce | Shopify |
|---|---|---|---|
| `pending` | not yet released to fulfilment | `pending`, `on-hold` | `open` + unpaid |
| `confirmed` | the shop considers it real | `processing`, `completed` | `open` + paid |
| `cancelled` | it is not going to happen | `cancelled`, `refunded`, `failed` | `cancelled`, `refunded` |

## The confirmation gate

With `salesos_require_order_confirmation` on — the default, and the right setting
for cash on delivery in Bangladesh — every imported order lands `pending`
whatever the store already calls it, and appears in **SalesOS → Confirmations**
with the customer's number, the items, the value and that number's COD delivery
history from fraudcheck. Only when somebody confirms the call does the order
reach stock, courier booking and notifications.

A connector needs no code for this. Do not try to set `confirmed` yourself to
bypass it: `follow_existing_order()` deliberately refuses to let a re-sync push a
held order past the gate.

With the setting off, orders go straight to whatever status you mapped.

## What you get without writing it

- **Deduplication** on `channel` + `external_id`. Re-syncing an order that has
  not changed costs three queries and no catalogue work at all — check the
  already-imported case before doing anything expensive, which the base class
  does for you.
- **Catalogue matching** via `Salesos_model::match_or_create_product()`: SKU
  first, then name, creating the category and the billing item that carries the
  price. `enrich_line_item()` is called only for products being created for the
  first time, so a payload that lacks a category or image can fetch one without
  paying for it on every order.
- **Customer matching** on the phone number, through an indexed column.
- **Status following**, through `set_order_status()`, so a cancellation in the
  shop fires the events inventory, courier and notifications already listen for.
- **Fail-soft errors**: a failed fetch is reported per site, not thrown, and one
  bad order does not abandon the rest of the page.

## Wiring it up

1. Put the class in `modules/<yourmodule>/libraries/<Platform>_channel.php`.
2. Store credentials in the kernel vault with `owner_module` set to your module;
   read them with `$this->credential($site, '<yourmodule>')`.
3. Register sites through `Salesos_model::save_channel_site()` with your
   `platform` key. All platforms share `tblsalesos_channel_sites`; anything only
   your platform needs goes in its `settings` JSON column.
4. Call `sync()` from your module's `after_cron_run` handler. Self-gate the
   interval — the kernel does not schedule for you.

A custom Laravel site is the simplest case of all: it has no third-party API to
please, so `fetch_orders()` can read whatever endpoint you control, and
`translate_order()` is close to a straight field mapping.

## Retired

`wooconnector` was a second WooCommerce integration that bypassed the kernel: it
turned orders into leads for a confirmation call but never produced an order, and
stored line items as a text blob in a lead custom field, so stock, courier and
invoicing could not use them. Its confirmation workflow now lives in the kernel
where every platform gets it. Its cron is disconnected so the two cannot import
the same order twice; its screens and data stay readable for migration.
