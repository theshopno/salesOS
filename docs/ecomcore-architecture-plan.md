# ecomcore — E-commerce Management Suite: Architecture Plan

Status: DECIDED — all open design forks resolved (§11.1, 11.3, 11.4, 11.5). Not yet implemented.
Base: Perfex-CRM-derived CodeIgniter 3 app at `/home/fizz/Projects/crm`.

This plan replaces the earlier "wooconnector owns everything" design with a master
module (`ecomcore`) + a fully self-contained family of feature modules built under
it: `inventory`, `purchases`, `pos`, `returns`, `courier`, `fraudcheck`, `wcsync`
(a brand-new WooCommerce channel connector), and `ordernotifier` (order notifications
via BizBot WhatsApp + Bulk SMS, §16). **`pos` is explicitly part of the `ecomcore`
family** — it
is not a standalone module and does not exist independently of `ecomcore` + `inventory`.

**This document is not just a schema reference — §17 is binding process.** If you are
an implementer (human or agent) picking this up fresh: read §17 before writing any
code. It governs how phases are gated and when you must stop and ask the user instead
of assuming.

**Hard architecture rule (per explicit decision): the `ecomcore` family relates to
NO existing module in this codebase.** It does not depend on, call into, share
tables with, or refactor `wooconnector`, `salesos`, `bkash`, or any other
already-installed module. Where an existing module's logic is useful as a design
reference (e.g. wooconnector's order-fetch/phone-match pattern), that logic is
**reimplemented fresh inside the new module** — never imported, extended, or
coupled to the original. The existing `modules/wooconnector` is left completely
untouched and will keep running independently (functionally redundant with `wcsync`
if both stay active — that redundancy is an accepted consequence of this decision,
not an oversight). Every mechanism referenced below was verified against the actual
running code — no assumed framework behavior. Citations are `file:line`.

---

## 1. Verified platform facts (do not re-derive, these are confirmed)

| # | Fact | Evidence |
|---|---|---|
| 1 | No custom encryption class exists. CI's native `$this->encryption` (config key `APP_ENC_KEY`) is autoloaded and is the only real "standard" pattern, used by the payment-gateway framework via a per-field `'encrypted' => true` flag. | `application/config/autoload.php:66`, `application/libraries/gateways/App_gateway.php:295-298`, `modules/bkash/libraries/Bkash_gateway.php:33,38,47,81` |
| 2 | Existing credential storage is inconsistent: wooconnector stores WC API secret in **plaintext**; salesos rolled its **own** AES-256-CBC helper (`salesos_encrypt_sip_password()`); salesos's `install.php` also `add_option()`s **real hardcoded prod passwords** in plaintext. | `modules/wooconnector/install.php:15-16`; `modules/salesos/helpers/salesos_helper.php:111-136`; `modules/salesos/install.php:474,479` |
| 3 | The real event system is the global `hooks()` (WordPress-style, `bainternet/php-hooks`), synchronous/in-process, supports priority + arg-count. **Not** CI's native `system/core/Hooks.php` (that's a different, unrelated lifecycle hook). Registered from a module's **root bootstrap file** (`modules/<name>/<name>.php`), included on every request once active — not from `install.php`. | `application/vendor/bainternet/php-hooks/php-hooks.php:269,562-564`; `application/config/hooks.php:43-48`; `modules/goals/goals.php:14-22` |
| 4 | **No native "module requires module X" mechanism exists.** `module.json` is never read by the app. The only real dependency idiom in use is a runtime guard: `$CI->app_modules->is_active('name')`, checked inside the dependent module's own bootstrap. | `application/libraries/App_modules.php` (module.json unreferenced anywhere in `application/`); `modules/mreport/mreport.php:54`; `modules/openai/openai.php:31` |
| 5 | Module version tracking lives in `tblmodules` (`module_name`, `installed_version`, `active`). Versioned migrations (`modules/<name>/migrations/NNN_version_NNN.php`) are driven by `App_module_migration` + `App_modules::upgrade_database()`. In practice most existing modules **skip migrations/** and make `install.php` itself idempotent via repeated `table_exists()`/`field_exists()` guards, re-run on every activation. | `application/migrations/230_version_230.php:32-40`; `application/libraries/App_modules.php:274-279`; `modules/salesos/install.php` (506 lines, all idempotent guards) |
| 6 | Permission registration happens via `register_staff_capabilities($feature_id, $config, $name)`, called on the `admin_init` hook from the module bootstrap file — **not** install.php. Checked at runtime with `staff_can($capability, $feature)`. | `application/helpers/modules_helper.php:118-139`; `modules/wooconnector/wooconnector.php:29,41-49`; `modules/salesos/salesos.php:30,57-68` |
| 7 | `Leads_model::add()` takes a flat array matching `tblleads` columns + special keys (`tags`, `custom_fields`). `Leads_model::mark_as_lost($id)` takes only an id. `Leads_model::log_lead_activity($id, $description, $integration=false, $additional_data='')` — `$description` **must be a language key string**, not free text. **Lead→Client conversion has no reusable model method** — it's controller-only logic in `Leads::convert_to_customer()`, building a flat array and calling `Clients_model::add($data, true)`. | `application/models/Leads_model.php:67-132,403-429,903-921`; `application/controllers/admin/Leads.php:373-424` |
| 8 | `Clients_model::add($data, $withContact=false)` takes company + contact columns **interleaved in one flat array** (split internally via `$this->contact_columns`), not two separate structs. | `application/models/Clients_model.php:117` |
| 9 | `get_option()`/`update_option()` store **raw strings only** — no automatic JSON-encoding, no automatic encryption. Callers must `json_encode`/`serialize` and encrypt themselves. | `application/helpers/settings_helper.php:14-116`; `application/libraries/App.php:355-373` |
| 10 | **No stock/SKU/warehouse concept exists anywhere in the live schema.** `tblitems` (master catalog: description, rate, tax, tax2, unit, group_id) and `tblitemable` (polymorphic line items on invoices/estimates/proposals, `qty` = per-document quantity, **not** stock-on-hand) are the only product-adjacent tables. Confirmed via live `SHOW TABLES` — zero matches for `stock|sku|inventory|warehouse|product`. | live DB `SHOW CREATE TABLE tblitems/tblitemable`; `application/migrations/129_version_129.php:275`, `140_version_140.php:69-81` |
| 11 | Single cron entry point (`Cron.php`) throttled to run application logic at most every `cron_functions_execute_seconds` (default 300s, filterable), dispatching `hooks()->do_action('after_cron_run', ...)`. **Every module hooked to `after_cron_run` shares this one cadence** — there is no per-module schedule; a module needing a different interval must self-gate with its own stored timestamp. | `application/controllers/Cron.php`; `application/models/Cron_model.php:55,112`; `modules/wooconnector/wooconnector.php:30,93-98` |
| 12 | salesos already has a **working, reusable follow-up pipeline**: `WrapUp::_create_task()` inserts directly into core `tbltasks` (`rel_type`/`rel_id`-linked), reusing the CRM's native task/reminder system instead of a bespoke table. It also has a generic append-only audit-log pattern (`tblsalesos_events`: `event_type`, `entity_type`, `entity_id`, `payload JSON`) and a phone→entity resolver (`tblsalesos_phone_index`). | `modules/salesos/controllers/WrapUp.php:61-83,176-202`; `modules/salesos/install.php:91-107,109-131` |

**Implication for this plan:** items 1, 2, 4, 6, 9, 10, 11, 12 directly change the design vs. the earlier draft. Details below.

---

## 2. Architecture (revised, fact-checked)

```
ecomcore (master/kernel)
 ├─ owns: credential vault, generic order+order_items schema, customer-matching
 │        bridge, event log, central Integrations/Dashboard admin pages
 │
 ├─→ inventory      (products, stock ledger — hard dependency on ecomcore)
 │      ├─→ purchases   (supplier/PO — hard dependency on inventory)
 │      ├─→ pos         (till sale — hard dependency on inventory + core invoicing)
 │      └─→ returns     (return workflow — hard dependency on inventory + ecomcore orders)
 │
 ├─→ courier        (consignment tracking — depends on ecomcore orders only, NOT inventory)
 ├─→ fraudcheck     (phone lookup — depends on ecomcore credentials only, NOT inventory)
 ├─→ wcsync         (NEW WooCommerce channel connector — depends on ecomcore only;
 │                    logic ported/rewritten fresh, zero relation to modules/wooconnector)
 └─→ ordernotifier  (order notifications via BizBot WhatsApp + Bulk SMS, §16 —
                      depends on ecomcore hooks only; logic reimplemented fresh,
                      zero relation to/dependency on modules/bizbot; schema is
                      DRAFT pending user interview)
```

Every arrow above is **within the `ecomcore` family only**. No arrow points to
`wooconnector`, `salesos`, `bkash`, or any other pre-existing module — that is a
deliberate boundary, not an omission.

Since dependency enforcement is **not native** (fact #4), every dependent module's
bootstrap file must open with:
```php
if (!$CI->app_modules->is_active('ecomcore')) { return; } // or admin notice + disable menu
```
This is the same idiom `mreport`/`openai` already use — no new pattern invented. The
same guard is used **only** to check for other `ecomcore`-family modules, never for
`wooconnector`/`salesos`/etc.

---

## 3. Module: `ecomcore` (master)

### Tables

```sql
CREATE TABLE tblecomcore_credentials (
  id            INT(11)      NOT NULL AUTO_INCREMENT,
  owner_module  VARCHAR(50)  NOT NULL,          -- 'wcsync' | 'courier' | 'fraudcheck' | ... (ecomcore-family modules only)
  label         VARCHAR(150) NOT NULL,          -- 'Pathao API', 'bdcourier.com Fraud Check'
  cred_type     VARCHAR(30)  NOT NULL,          -- 'api_key' | 'basic_auth' | 'oauth'
  payload       TEXT         NOT NULL,          -- JSON, encrypted via $this->encryption->encrypt() before insert
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_used_at  DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY owner_module (owner_module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblecomcore_orders (
  id               INT(11)      NOT NULL AUTO_INCREMENT,
  channel          VARCHAR(30)  NOT NULL,        -- 'woo' | 'pos' | 'manual'
  channel_ref_id   VARCHAR(100) DEFAULT NULL,    -- e.g. WC order id; NULL for pos/manual
  lead_id          INT(11)      DEFAULT NULL,
  client_id        INT(11)      DEFAULT NULL,
  status           VARCHAR(30)  NOT NULL DEFAULT 'pending', -- pending/confirmed/cancelled/shipped/delivered/returned
  subtotal         DECIMAL(15,2) NOT NULL DEFAULT 0,
  shipping_charge  DECIMAL(15,2) NOT NULL DEFAULT 0,
  total            DECIMAL(15,2) NOT NULL DEFAULT 0,
  currency         VARCHAR(10)  DEFAULT 'BDT',
  payment_method   VARCHAR(60)  DEFAULT NULL,
  order_note       TEXT         DEFAULT NULL,
  order_date       DATETIME     DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY channel_order (channel, channel_ref_id),
  KEY lead_id (lead_id), KEY client_id (client_id), KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblecomcore_order_items (
  id          INT(11)      NOT NULL AUTO_INCREMENT,
  order_id    INT(11)      NOT NULL,
  item_id     INT(11)      DEFAULT NULL,   -- FK tblitems.id (nullable — see §7 open question)
  product_id  INT(11)      DEFAULT NULL,   -- FK tblinventory_products.id (nullable if inventory module absent)
  name        VARCHAR(255) NOT NULL,
  sku         VARCHAR(100) DEFAULT NULL,
  qty         DECIMAL(15,2) NOT NULL DEFAULT 1,
  unit_price  DECIMAL(15,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY order_id (order_id), KEY product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblecomcore_events (      -- modeled on tblsalesos_events (fact #12), module-private, not shared
  id           INT(11)     NOT NULL AUTO_INCREMENT,
  event_type   VARCHAR(60) NOT NULL,   -- 'order.created' | 'order.confirmed' | 'stock.deducted' | ...
  entity_type  VARCHAR(30) DEFAULT NULL,
  entity_id    INT(11)     DEFAULT NULL,
  payload      JSON        DEFAULT NULL,
  created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY event_type (event_type), KEY entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Bridge functions (exposed via `Ecomcore_model`, cross-module loadable)

- `import_order(array $generic_order): int` — inserts `tblecomcore_orders`+`order_items`,
  runs customer matching, fires `hooks()->do_action('ecomcore_order_created', $order_id)`.
- `match_or_create_customer($phone, $data): array` — **new glue code**, not a wrapper
  around an existing core function (fact #7 confirms none exists). Mirrors the phone
  normalize/match logic already written once in `wooconnector_model` and again
  independently in `salesos_model::match_phone()` — this is the **second duplicate**
  of the same phone-matching logic in this codebase; ecomcore should be the single
  place it lives going forward.
- `set_order_status($order_id, $status)` — fires `ecomcore_order_confirmed` /
  `ecomcore_order_cancelled` / `ecomcore_stock_returned` as appropriate.
- `Ecomcore_encryption::encrypt($data)` / `decrypt($id)` — thin wrapper over
  `$this->encryption`, used by every credential write/read (fact #1).

### Permissions (registered on `admin_init`, per fact #6)

```php
register_staff_capabilities('ecomcore', [
    'capabilities' => [
        'view'     => 'View E-commerce Dashboard',
        'settings' => 'Manage Channels & Credentials',
    ],
], 'E-commerce Management');
```

---

## 4. Module: `inventory`

```sql
CREATE TABLE tblinventory_products (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  item_id        INT(11)      DEFAULT NULL,  -- optional FK tblitems.id, see §7
  sku            VARCHAR(100) DEFAULT NULL,
  name           VARCHAR(255) NOT NULL,
  category_id    INT(11)      DEFAULT NULL,
  reorder_level  DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY sku (sku), KEY item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblinventory_categories (
  id INT(11) NOT NULL AUTO_INCREMENT, name VARCHAR(150) NOT NULL, parent_id INT(11) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblinventory_warehouses (
  id INT(11) NOT NULL AUTO_INCREMENT, name VARCHAR(150) NOT NULL, is_default TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblinventory_stock (       -- denormalized current snapshot, fast reads
  id             INT(11) NOT NULL AUTO_INCREMENT,
  product_id     INT(11) NOT NULL,
  warehouse_id   INT(11) NOT NULL,
  qty_on_hand    DECIMAL(15,2) NOT NULL DEFAULT 0,
  qty_reserved   DECIMAL(15,2) NOT NULL DEFAULT 0,  -- Phase 2, see §6.9
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY product_warehouse (product_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblinventory_stock_ledger (  -- append-only, source of truth; tblinventory_stock is derived
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  product_id     INT(11)      NOT NULL,
  warehouse_id   INT(11)      NOT NULL,
  movement_type  VARCHAR(30)  NOT NULL,   -- purchase_in/sale_out/return_in/adjustment/transfer_in/transfer_out
  qty            DECIMAL(15,2) NOT NULL,  -- signed
  balance_after  DECIMAL(15,2) NOT NULL,
  ref_type       VARCHAR(30)  DEFAULT NULL, -- 'ecomcore_order' | 'purchase_order' | 'return_order' | 'manual'
  ref_id         INT(11)      DEFAULT NULL,
  staff_id       INT(11)      DEFAULT NULL,
  note           TEXT         DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY product_id (product_id), KEY ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Reacts to: `ecomcore_order_confirmed` (deduct), `ecomcore_order_cancelled` (restock if
already deducted), `ecomcore_stock_returned` (restock, conditional — see §6.7).

MVP scope: single default warehouse row auto-created on install; `tblinventory_warehouses`
exists for future multi-location but the UI hides warehouse selection in MVP.

---

## 5. Module: `purchases`

```sql
CREATE TABLE tblpurchases_suppliers (
  id INT(11) NOT NULL AUTO_INCREMENT, name VARCHAR(200) NOT NULL, phone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL, address TEXT DEFAULT NULL,
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblpurchases_orders (
  id INT(11) NOT NULL AUTO_INCREMENT, supplier_id INT(11) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',  -- draft/ordered/received/cancelled
  total DECIMAL(15,2) NOT NULL DEFAULT 0, order_date DATE DEFAULT NULL, received_at DATETIME DEFAULT NULL,
  staff_id INT(11) DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY supplier_id (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblpurchases_order_items (
  id INT(11) NOT NULL AUTO_INCREMENT, purchase_order_id INT(11) NOT NULL, product_id INT(11) NOT NULL,
  qty DECIMAL(15,2) NOT NULL, unit_cost DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (id), KEY purchase_order_id (purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblpurchases_supplier_ledger (
  id INT(11) NOT NULL AUTO_INCREMENT, supplier_id INT(11) NOT NULL,
  entry_type VARCHAR(20) NOT NULL,   -- 'debit' (we owe more) | 'credit' (we paid)
  amount DECIMAL(15,2) NOT NULL, ref_type VARCHAR(30) DEFAULT NULL, ref_id INT(11) DEFAULT NULL,
  note TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY supplier_id (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

"Receive" action on a PO calls `inventory_model->add_stock()` per line (movement_type=`purchase_in`,
ref_type=`purchase_order`). Hard dependency on `inventory`.

---

## 6. Module: `pos`

✅ **Decided (§11.5): POS sale = a real core invoice, not a parallel record type.**

```sql
CREATE TABLE tblpos_sales (   -- thin link table, NOT the sale's source of truth
  id                 INT(11) NOT NULL AUTO_INCREMENT,
  invoice_id         INT(11) NOT NULL,          -- FK tblinvoices.id — core invoicing owns the money math
  ecomcore_order_id  INT(11) DEFAULT NULL,       -- FK tblecomcore_orders.id (channel='pos')
  client_id          INT(11) DEFAULT NULL,
  cashier_staff_id   INT(11) NOT NULL,
  payment_method     VARCHAR(60) DEFAULT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Flow: cashier screen → `ecomcore->import_order(channel='pos')` → core `Invoices_model`
creates the actual invoice (reusing Perfex's existing tax/currency engine — fact #10
shows `tblitems` already carries `tax`/`tax2`) → invoice marked paid → fires
`ecomcore_order_confirmed` → inventory deducts. Hard dependency on `inventory` + core invoicing.

---

## 7. Module: `returns`

```sql
CREATE TABLE tblreturns_orders (
  id                 INT(11) NOT NULL AUTO_INCREMENT,
  ecomcore_order_id  INT(11) NOT NULL,
  status             VARCHAR(30) NOT NULL DEFAULT 'requested', -- requested/approved/rejected/received/restocked/refunded
  reason             VARCHAR(255) DEFAULT NULL,
  requested_by       VARCHAR(20) NOT NULL DEFAULT 'customer',  -- customer | staff
  staff_id           INT(11) DEFAULT NULL,
  refund_amount      DECIMAL(15,2) DEFAULT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY ecomcore_order_id (ecomcore_order_id), KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblreturns_order_items (
  id                INT(11) NOT NULL AUTO_INCREMENT,
  return_order_id   INT(11) NOT NULL,
  order_item_id     INT(11) NOT NULL,   -- FK tblecomcore_order_items.id
  qty               DECIMAL(15,2) NOT NULL,
  condition_note    VARCHAR(20) NOT NULL DEFAULT 'sellable', -- 'sellable' | 'damaged'
  PRIMARY KEY (id), KEY return_order_id (return_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Flag (§ below):** only `condition_note = 'sellable'` items auto-restock on `received`;
`damaged` items require an explicit manual `inventory` adjustment, never silent.

---

## 8. Module: `courier`

```sql
CREATE TABLE tblcourier_accounts (
  id              INT(11)      NOT NULL AUTO_INCREMENT,
  provider        VARCHAR(30)  NOT NULL,  -- 'pathao' | 'steadfast' | 'redx' | ...
  label           VARCHAR(150) DEFAULT NULL,
  credential_id   INT(11)      NOT NULL,  -- FK tblecomcore_credentials.id
  pickup_address  TEXT         DEFAULT NULL,
  is_default      TINYINT(1)   NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblcourier_consignments (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  ecomcore_order_id   INT(11)      NOT NULL,
  courier_account_id  INT(11)      NOT NULL,
  tracking_id         VARCHAR(100) DEFAULT NULL,
  cod_amount          DECIMAL(15,2) NOT NULL DEFAULT 0,
  status              VARCHAR(30)  NOT NULL DEFAULT 'pending', -- pending/booked/picked_up/in_transit/delivered/returned/cancelled
  raw_response        JSON         DEFAULT NULL,
  last_synced_at      DATETIME     DEFAULT NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY ecomcore_order_id (ecomcore_order_id), KEY tracking_id (tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Status sync via `after_cron_run` (fact #11) — 300s shared cadence is acceptable for
tracking polling in MVP; no custom scheduler needed. Soft dependency on ecomcore
orders only, **not** on inventory — booking a consignment doesn't require stock data.

---

## 9. Module: `fraudcheck`

API confirmed from the doc you provided:
`POST https://api.bdcourier.com/courier-check`, `Authorization: Bearer <key>`,
body `{"phone": "017xxxxxxxx"}`, response includes per-courier `total_parcel /
success_parcel / cancelled_parcel / success_ratio` plus a `summary` block and a
`reports[]` array of merchant-submitted fraud reports.

```sql
CREATE TABLE tblfraudcheck_lookups (
  id                INT(11)      NOT NULL AUTO_INCREMENT,
  phone             VARCHAR(20)  NOT NULL,   -- normalized 10-digit, same normalize_phone() logic as ecomcore bridge
  total_parcel      INT(11)      NOT NULL DEFAULT 0,
  success_parcel    INT(11)      NOT NULL DEFAULT 0,
  cancelled_parcel  INT(11)      NOT NULL DEFAULT 0,
  success_ratio     DECIMAL(5,2) NOT NULL DEFAULT 0,
  report_count      INT(11)      NOT NULL DEFAULT 0,  -- length of reports[]
  raw_response      JSON         DEFAULT NULL,          -- full response incl. per-courier breakdown
  checked_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Credential stored once in `tblecomcore_credentials` (`owner_module='fraudcheck'`,
`cred_type='api_key'`). **Caching is mandatory, not optional** — the API's own docs
say it auto-selects free/paid plan by subscription, implying quota; re-querying the
same phone on every page view would burn quota for no benefit. Default cache TTL
48h, manual "re-check" button bypasses cache.

UI hook: badge on `wcsync`'s own Website Orders queue view (the ecomcore-family
equivalent of wooconnector's orders screen, built fresh — see §10), a read-only
consumer of `fraudcheck_model->check($phone)`. No hook into wooconnector's or
salesos's views — those stay untouched per the no-relation rule (§2).

**Trigger policy — ✅ Decided, see §11.4.**

---

## 10. Module: `wcsync` — new WooCommerce channel connector (built fresh, NOT wooconnector)

**This is a brand-new module in the `ecomcore` family, not a refactor of
`modules/wooconnector`.** The existing `wooconnector` module is left 100% untouched —
different table names, different code, no shared runtime state, no FK between the two.
`wcsync` independently re-implements the equivalent capability, using wooconnector's
approach purely as a **design reference** (the general shape of "poll WC REST API →
match/create customer by phone → track sync state" is sound and worth reusing as a
pattern — the code itself is not reused).

```sql
CREATE TABLE tblwcsync_sites (
  id                INT(11)      NOT NULL AUTO_INCREMENT,
  name              VARCHAR(150) NOT NULL,
  site_url          VARCHAR(255) NOT NULL,
  credential_id     INT(11)      NOT NULL,   -- FK tblecomcore_credentials.id (encrypted — wcsync is a NEW module, uses the vault)
  default_lead_status_id INT(11) NOT NULL,
  last_synced_at    DATETIME     DEFAULT NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblwcsync_orders (   -- pure sync bookkeeping, NOT the business record
  id           INT(11)  NOT NULL AUTO_INCREMENT,
  site_id      INT(11)  NOT NULL,
  wc_order_id  INT(11)  NOT NULL,
  ecomcore_order_id INT(11) NOT NULL,  -- FK tblecomcore_orders.id (channel='woo')
  synced_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY site_order (site_id, wc_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Because credential storage is genuinely new here (unlike wooconnector's existing
plaintext columns, which stay untouched per §11.1), `wcsync` uses the encrypted
`tblecomcore_credentials` vault from day one — no plaintext API secret anywhere in
this module.

Flow: fetch WC REST API orders → translate to the generic `import_order()` shape →
`Ecomcore_model->import_order()` handles customer matching, order/items insert, event
firing. `wcsync`'s own model contains only WC-specific translation logic — the
phone-normalize/match/create-lead logic lives once, in `ecomcore`, written fresh for
this module family (see §11.8 — this is intentionally **not** shared with
wooconnector's or salesos's independent implementations).

Own lead-status rows and options, independent of wooconnector's: `wcsync` creates its
own `Called`/`Confirmed`/`Cancelled`-equivalent lead statuses (or reuses generic core
ones — an implementation detail for later) and its own
`wcsync_called_status_id` / `wcsync_confirmed_status_id` / `wcsync_cancelled_status_id`
options, structurally similar to wooconnector's pattern but a separate set of DB rows
— no shared option keys, no shared status ids.

---

## 11. Flag points / technical debates requiring a decision

### 11.1 Encryption scope — ✅ DECIDED: new modules only
The bkash pattern (`'encrypted' => true` + CI's `$this->encryption`) is the only
sound existing precedent (fact #1). wooconnector's plaintext secret and salesos's
homemade AES + hardcoded prod passwords (fact #2) are real, pre-existing security
gaps in this codebase, unrelated to whether you build ecomcore.

**Decision: scope stays narrow.** `ecomcore`'s encrypted `tblecomcore_credentials`
vault is used by every genuinely new credential in the `ecomcore` family (`courier`,
`fraudcheck`, and `wcsync`'s WooCommerce API keys — §10). wooconnector's existing
plaintext `consumer_key`/`consumer_secret` columns are left untouched — no migration,
no retrofit, no relation at all (per the no-relation rule in §2). This is an explicit
scope cut: wooconnector's plaintext storage and salesos's hardcoded prod passwords
remain known, pre-existing gaps, unrelated to this build. Worth a separate security
ticket later, but not part of ecomcore's delivery.

### 11.2 No native module dependency enforcement (fact #4)
Every feature module must defensively check `is_active('ecomcore')` in its own
bootstrap. If a user deactivates `ecomcore` while `inventory`/`returns`/`courier` stay
active, those modules must fail soft (hide menu, log a warning) — not fatal-error on
a missing table. This needs to be a written convention for every module you add, not
assumed. This guard checks **only** for other `ecomcore`-family modules — it is never
used to check for `wooconnector`, `salesos`, or any other pre-existing module, per
the no-relation rule in §2.

### 11.3 `tblitems` reuse vs. a fully separate product catalog — ✅ DECIDED: separate
**Decision: option (B).** `tblinventory_products` stays a fully separate table (as
already written in §4), with a nullable `item_id` bridge to `tblitems` for cases where
a staff member wants a SKU-tracked product to also be selectable on invoices/estimates.
Chosen for clean module boundaries and zero risk of a future core-CRM update
conflicting with inventory's schema. Consequence to design around: the invoice/estimate
item picker and the inventory product picker are **two distinct UIs** unless a product
is explicitly linked via `item_id` — this needs to be a conscious UX choice in the
`inventory` and `pos` module views, not an accident.

### 11.4 Fraud-check trigger policy — ✅ DECIDED: on specific lead status
**Decision:** auto-trigger only when a `wcsync` order's lead reaches a specific status
— "Called" (the stage right before "Confirmed"). `wcsync` creates its **own**
`Called`/`Confirmed`/`Cancelled` lead-status rows and its own
`wcsync_called_status_id` / `wcsync_confirmed_status_id` / `wcsync_cancelled_status_id`
options (§10) — structurally the same pattern wooconnector uses for its own statuses,
but a fully separate set of rows/options, since `wcsync` does not read or write
anything of wooconnector's. A listener on the core lead-status-changed hook
(`hooks()->add_action('lead_status_changed', ...)` — this is a **core CRM hook**, not
part of any module, so listening to it does not violate the no-relation rule) calls
`fraudcheck_model->check($phone)` when a lead transitions into `wcsync`'s "Called"
status. Cheapest useful middle ground: telesales sees the fraud badge exactly when
they're about to decide whether to call, not on every lead import.

### 11.5 POS integration depth — ✅ DECIDED: real core invoice
**Decision:** POS sales create a real core invoice (as already written in §6), not a
parallel lightweight record. Reuses Perfex's existing tax/currency engine and shows up
in every existing financial report (P&L, tax reports, client statements) for free,
which outweighs the extra step of going through `Invoices_model` at the counter.

### 11.6 Damaged returns must not auto-restock
`tblreturns_order_items.condition_note` gates this explicitly (§7) — a returned item
marked `damaged` sits in a "pending inspection" state and requires a manual
`inventory` adjustment to re-enter sellable stock. Silent auto-restock of damaged
goods is a data-integrity bug waiting to happen, not a hypothetical.

### 11.7 Cron cadence is shared, not per-module (fact #11)
Do not design any ecomcore feature assuming it gets its own schedule. Courier status
polling and fraud-check cache refresh both ride the same `after_cron_run` throttle
(~300s by default, whatever the server crontab actually runs). If a feature genuinely
needs a coarser cadence (e.g. daily stock reconciliation report), it must self-gate
with its own stored "last run" option, exactly like `Cron.php` gates itself.

### 11.8 Phone-matching logic — intentionally a third independent implementation
`wooconnector_model` (private methods) and `salesos_model::match_phone()` (fact #12)
are two existing, independent phone-normalize/match implementations already in this
codebase. `ecomcore`'s bridge (§3) is a deliberate **third** implementation, written
fresh — not a consolidation of the other two, per the no-relation rule (§2). This is
an accepted trade: normally you'd want one canonical version, but the explicit
decision here is that `ecomcore` stays fully self-contained rather than reaching into
`wooconnector` or `salesos` to share code. Within the `ecomcore` family itself,
`wcsync`/`inventory`/`returns`/etc. all call the one `ecomcore` version — the
duplication is only *across* the family boundary, not within it.

### 11.9 Stock reservation on unconfirmed orders (Phase 2, not MVP-blocking)
`tblinventory_stock.qty_reserved` exists in the schema (§4) but MVP does not populate
it. Risk being flagged for later: a WooCommerce order sitting in "pending confirmation"
doesn't reserve stock today, so two unconfirmed orders can oversell the same last unit
before telesales confirms either one. Real risk for low-stock SKUs, acceptable for MVP
given order volume is presumably still small, but should not be forgotten.

### 11.10 Perfex's own tax/currency engine should not be duplicated
`tblitems` already carries `tax`/`tax2` (fact #10). Any module that produces something
invoice-shaped (POS, and arguably WooCommerce orders once confirmed) should reuse
Perfex's existing tax calculation rather than re-implementing tax math inside ecomcore
— this is why §6 recommends POS create a real core invoice rather than compute its
own totals.

---

## 12. Build order

```
1. ecomcore          (credentials, generic orders, bridge functions, permissions)
2. inventory          (depends on 1)
3. wcsync             (NEW module, depends on 1 only; can run parallel with 2, but its
                        stock-deduct behavior needs 2 done first to take effect)
4. purchases          (depends on 2)
5. pos                (depends on 2 + core invoicing)
6. returns            (depends on 1 + 2)
7. courier            (depends on 1 only — can be built anytime after step 1)
8. fraudcheck         (depends on 1 only — can be built anytime after step 1, including in parallel with 2-6)
9. ordernotifier      (depends on 1 only; requires the interview in §16/§17 BEFORE
                        this phase starts — do not build ahead of that conversation)
```

Every step above is a new build under `ecomcore`. `modules/wooconnector` and
`modules/bizbot` are not part of this build order — neither is touched, modified, or
depended upon at any step (§2). Per §17.2, these are built **one at a time, strictly
in this order** — do not start step N+1 before step N's acceptance criteria (§15) are
met and logged in the ledger.

---

## 13. Decisions log

| # | Question | Decision |
|---|---|---|
| 1 | §11.3 — product catalog | Separate `tblinventory_products`, nullable `item_id` bridge to `tblitems` |
| 2 | §11.4 — fraud-check trigger | Auto-check on lead reaching "Called" status (new `wooconnector_called_status_id` option + `lead_status_changed` hook listener) |
| 3 | §11.5 — POS record type | Real core invoice via `Invoices_model`, not a parallel record |
| 4 | §11.1 — encryption scope | New modules only (`courier`, `fraudcheck`, `wcsync`); wooconnector's existing plaintext credentials stay untouched, out of scope |
| 5 | §2 — module relationship scope | `ecomcore` family (`ecomcore`, `inventory`, `purchases`, `pos`, `returns`, `courier`, `fraudcheck`, `wcsync`, `ordernotifier`) is fully self-contained — no dependency, integration, or code-sharing with `wooconnector`, `salesos`, `bkash`, `bizbot`, or any other existing module. `pos` is explicitly confirmed as part of the family. Where an existing module's logic is a useful reference (e.g. wooconnector's WC-sync pattern, bizbot's WhatsApp API shape), it is reimplemented fresh in a new module (`wcsync` §10, `ordernotifier` §16) — never imported or coupled. `modules/bizbot` is explicitly confirmed **not** a dependency of `ordernotifier` |
| 6 | §16 — ordernotifier module scope | Order notifications reuse BizBot's WhatsApp API shape (verified from `modules/bizbot`'s code, but with the base URL corrected per user instruction to `api.bizbot.bd`, §16) **plus** Bulk SMS as a second channel (provider TBD, user to supply credentials later) — via a fresh `ordernotifier` module, **not** a modification of `modules/bizbot`. Channel scope (WhatsApp+SMS) is now locked (§16.0); event list, templates, recipients, retry policy, and SMS provider specifics remain pending interview (§17.1) before Phase 9 |

All six are locked except the remaining open items inside #6 (§16.1), which are
intentionally open pending interview. Next step: build order per §12, starting with
`ecomcore`. Read §17 before starting any phase.

---

## 14. Module scaffolding template (verified — apply to every new module, do not invent a different convention)

Confirmed against the live `modules/wooconnector/wooconnector.php` bootstrap file
(full file read, not excerpted) and `modules/wooconnector/controllers/Wooconnector.php`.
Every `ecomcore`-family module copies this shape.

### Directory layout
```
modules/<name>/
  <name>.php       -- bootstrap: docblock header + hook registrations (below)
  install.php       -- idempotent schema/data setup, run once via activation hook (fact #5)
  controllers/
  models/
  views/
  migrations/       -- optional; most existing modules skip this (fact #5)
```

### Bootstrap file (`modules/<name>/<name>.php`)
```php
<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: <Display Name>
Description: <one line>
Version: 1.0.0
Requires at least: 2.3.4
*/

define('<NAME>_MODULE_NAME', '<name>');
define('<NAME>_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(<NAME>_MODULE_NAME, '<name>_activation_hook');
function <name>_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   '<name>_load_resources');
hooks()->add_action('admin_init', '<name>_register_menu');
hooks()->add_action('admin_init', '<name>_register_permissions');
// + module-specific hooks: after_cron_run, lead_status_changed,
//   ecomcore_order_confirmed, etc. — see this module's own section (§3-§10)

function <name>_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('ecomcore')) { return; } // §11.2 — every module except ecomcore itself
    $CI->load->model(<NAME>_MODULE_NAME . '/<name>_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function <name>_register_permissions(): void
{
    register_staff_capabilities('<name>', [
        'capabilities' => [
            'view'     => 'View ...',
            'settings' => 'Manage Settings',
            // add per-module verbs as needed, mirror wooconnector's pattern
        ],
    ], '<Display Name>');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function <name>_register_menu(): void
{
    if (!staff_can('view', <NAME>_MODULE_NAME)) { return; }
    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('<name>', [
        'name' => '<Display Name>', 'icon' => 'fa fa-...', 'position' => <n>,
    ]);
    $CI->app_menu->add_sidebar_children_item('<name>', [
        'slug' => '<name>-dashboard', 'name' => 'Dashboard',
        'href' => admin_url('<name>'), 'position' => 5,
    ]);
    // + one add_sidebar_children_item() per additional controller action page
}
```

### Controller convention (verified against `Wooconnector.php`)
```php
class <Name> extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('<name>/<name>_model');
        if (!staff_can('view', '<name>')) { access_denied('<Display Name>'); }
    }

    public function index()
    {
        $data['title'] = '...';
        $this->load->view('<name>/dashboard', $data);
    }
}
```
AJAX endpoints: guard with `ajax_access_denied()` instead of `access_denied()`, respond
with `echo json_encode([...])` — see `Wooconnector::sync_now()`/`retry_conversion()`
for the exact pattern to copy.

### Model convention
```php
class <Name>_model extends App_Model
{
    public function __construct() { parent::__construct(); }
}
```
Standard CI idioms apply throughout: `$this->db->...`, always prefix table names with
`db_prefix()`, use `get_option()`/`update_option()` for settings (fact #9 — remember
these store raw strings only, `json_encode`/`serialize` yourself if needed).

### `install.php` convention
Idempotent, guarded with `table_exists()`/`field_exists()` checks, safe to re-run on
every activation (fact #5). Copy the exact guard style already used in
`modules/wooconnector/install.php` or `modules/salesos/install.php` — both are in this
repo and can be read directly rather than re-derived.

### Hook payload shapes — verified, do not guess
- `lead_status_changed`: `$data['lead_id']` (int), `$data['new_status']` (string — the
  **name** of the new status, not its id). Verified at
  `modules/wooconnector/wooconnector.php:102-125`.
- `after_cron_run`: no meaningful payload; fires on the shared throttled cadence (fact #11).
- `ecomcore_order_created` / `ecomcore_order_confirmed` / `ecomcore_order_cancelled` /
  `ecomcore_stock_returned`: **new hooks, defined by this plan, not yet real** — they
  don't exist until `Ecomcore_model` (Phase 1, §15) is actually implemented. Whoever
  writes `Ecomcore_model` first fixes the exact payload shape (recommended:
  `hooks()->do_action('ecomcore_order_confirmed', $ecomcore_order_id)` — a plain int)
  and **must update this table** once decided, so `inventory`/`courier`/`returns`
  (built in later phases, possibly by a different session) don't have to guess.

---

## 15. Phase-wise execution plan

Complete, smoke-test, and (if the user asks for a commit) commit each phase before
starting the next — the dependency chain in §2/§12 is real, not procedural ceremony.
This section plus §1-§14 is the **complete context needed to execute this build with
no additional research** — a fresh session with no memory of how this plan was
produced should be able to work from this file alone.

### Phase 0 — Before writing any code, every session
- Confirm working tree / branch state (`git status`).
- Confirm this is a fresh start: `SHOW TABLES LIKE 'tblecomcore%'` should return nothing yet.
- Read this entire file top to bottom once — it is the single source of truth; do not
  re-derive facts already verified in §1 or re-open the "no relation to existing
  modules" question already decided in §2/§13.

### Phase 1 — `ecomcore` (master)
Build: `modules/ecomcore/ecomcore.php` (§14 template), `install.php` (creates the 4
tables in §3), `models/Ecomcore_model.php` (`import_order()`,
`match_or_create_customer()`, `set_order_status()`), `libraries/Ecomcore_encryption.php`
(wraps `$this->encryption`), `controllers/Ecomcore.php` + `views/` for a dashboard and
an Integrations/credentials settings page. Register `view`/`settings` permissions.
**Also**: fix the exact hook payload shape for the four `ecomcore_order_*` events (§14)
and record it back into this file.

Acceptance:
- Activates cleanly from Setup > Modules, no errors.
- A credential saved through the settings UI is unreadable in a raw `SELECT` on
  `tblecomcore_credentials.payload` (proves encryption actually ran).
- `import_order()` produces correct rows in `tblecomcore_orders`+`order_items` and
  correctly branches both ways in `match_or_create_customer()` (existing client match
  vs. new lead creation) — test both paths explicitly, not just the happy path.

### Phase 2 — `inventory`
Build: per §4. Reacts to `ecomcore_order_confirmed`/`cancelled`/`stock_returned`.

Acceptance:
- Manual stock adjustment via UI writes a `tblinventory_stock_ledger` row and updates
  `tblinventory_stock.qty_on_hand` correctly (ledger is source of truth — verify the
  snapshot table never drifts from `SUM(qty)` in the ledger for a given product+warehouse).
- Manually firing `ecomcore_order_confirmed` deducts stock; firing
  `ecomcore_order_cancelled` afterward restocks it back to the original quantity.

### Phase 3 — `wcsync`
Build: per §10 — own tables, own credential (encrypted), own status/options, WC REST
API fetch translated into `Ecomcore_model->import_order()` calls. **No file in this
module may `require`, `load->model()`, or otherwise reference anything under
`modules/wooconnector/`** — that would violate the decided no-relation rule (§2/§13).

Acceptance:
- A real WooCommerce test order syncs in with correct line items and a matched/created
  lead or client.
- Cancelling/refunding the WC order is reflected correctly on the next sync.
- Credential round-trips through the encrypted vault (same check as Phase 1).

### Phase 4 — `purchases`
Build: per §5. Acceptance: receiving a PO increases `tblinventory_stock` via a
`purchase_in` ledger entry; a supplier ledger entry is recorded.

### Phase 5 — `pos`
Build: per §6 — POS sale creates a **real** `tblinvoices` row via core `Invoices_model`
(§11.5 decision), not a parallel record. Acceptance: a POS sale deducts stock and
appears in existing Perfex financial reports (P&L/tax) with zero custom reporting code.

### Phase 6 — `returns`
Build: per §7. Acceptance: a `sellable` return auto-restocks on `received`; a `damaged`
return does **not** auto-restock (§11.6) and requires a manual `inventory` adjustment —
test both paths explicitly, this is the highest-risk silent-bug area in the whole plan.

### Phase 7 — `courier`
Build: per §8. Acceptance: a consignment books against a confirmed order; status
updates via the shared cron cadence (§11.7 — do not add a custom scheduler).

### Phase 8 — `fraudcheck`
Build: per §9. Acceptance: repeated lookups within the cache TTL make zero additional
API calls (verify against `bdcourier.com`'s response or a request log); auto-trigger
fires exactly on the `wcsync` "Called" status transition (§11.4) and at no other point;
manual re-check bypasses cache correctly.

### Phase 9 — `ordernotifier`
⚠️ **Do not start this phase's code until the §16.1 interview checklist has been asked
and answered by the user (§17.1).** The channel decision (WhatsApp+SMS, §16.0) is
locked; the rest of §16's schema is a draft skeleton, not a locked design — treat it
as a starting point for the interview, not as something to build directly. SMS-channel
code specifically cannot start until the user supplies the provider's identity and API
contract (§16.1 item 7) — WhatsApp-only may be able to ship first within this phase,
but confirm that phased approach with the user rather than assuming it.

Build: per §16, once finalized. Acceptance (finalize alongside the interview, but at
minimum): a real order-lifecycle event triggers a WhatsApp message via the
independently-reimplemented BizBot API call to `api.bizbot.bd` (§16); `tblordernotifier_log`
records status (pending/sent/failed) correctly per channel; a deliberately-failed send
(e.g. bad credential) logs `failed` with the provider's error response captured, not
silently swallowed.

---

## 16. Module: `ordernotifier` — order notifications via BizBot (WhatsApp) + Bulk SMS (fresh build, referencing `modules/bizbot`)

Per the no-relation rule (§2), this is a **new module in the `ecomcore` family**, not
a modification of the existing `modules/bizbot`. **`modules/bizbot` is not a
dependency of `ordernotifier`** — confirmed explicitly per user instruction, matching
the general no-relation rule already governing this whole plan. It is used only as a
**reference for the WhatsApp API's general shape** (verified from its live code), not
imported, extended, or required to be active.

**BizBot API contract for this module — per explicit user correction:**
```
POST https://api.bizbot.bd/public/v1/chat
Headers: Content-Type: application/json, x-api-key: <api_key>
Body: {"channel": "<channel_guid>", "phone": "<phone>", "message": "<text>"}
```
⚠️ **Discrepancy flagged, not silently resolved:** the *existing*
`modules/bizbot/libraries/Bizbot_api.php:17` code in this repo calls
`api.bizbot.one`, not `api.bizbot.bd`. The user has explicitly instructed
`ordernotifier` to call `api.bizbot.bd` instead. This has **not** been independently
re-verified against BizBot's own API docs (none were fetched) — it is taken as given
per direct user instruction, the same way the bdcourier.com fraud-check contract (§9)
was taken from a doc the user pasted. If `api.bizbot.bd` turns out unreachable during
Phase 9 implementation, that is a real signal to stop and ask the user rather than
silently falling back to `.one` — the two domains may be different environments
(e.g. a Bangladesh-specific deployment vs. a global one) and are not interchangeable
by assumption.

The request/header/body **shape** (`x-api-key` header, `{channel, phone, message}`
body) is still taken from the verified `Bizbot_api.php` code as the structural
reference — only the base URL is overridden per the user's correction.

`modules/bizbot` stores `bizbot_api_key`/`bizbot_channel_guid` via plain
`get_option()` — unencrypted, the same gap noted in fact #2. `ordernotifier` does
**not** read those options and does **not** call into `modules/bizbot` at all. It
stores its own credential in `tblecomcore_credentials` and re-issues the HTTP call
independently, so `ordernotifier`'s WhatsApp sending keeps working even if
`modules/bizbot` is ever deactivated or removed.

### 16.0 Channel scope — ✅ DECIDED: two channels, one new (was open question #3)
`ordernotifier` supports **two channels**, resolving what was previously open
question #3 in the interview checklist:
1. **WhatsApp via BizBot** — contract above, credentials to be supplied later.
2. **Bulk SMS** — provider not yet chosen; **user will supply credentials and API
   details later.** Do not invent a specific SMS gateway or API contract — this stays
   a placeholder until real information arrives (per §17.1, no defaults on a real
   decision). `channel` columns below already accommodate `'sms'` as a value so the
   schema doesn't need to change shape when the SMS provider is confirmed — only the
   sending logic for that channel gets implemented once specs arrive.

For reference, `modules/bizbot` (v1.1.0, per `modules/bizbot/changelog.md`) already
supports: multi-event automation across leads/customers/invoices/tasks/projects/
tickets, a template manager per event, flexible recipients (customer/staff/admin/task
followers), delayed lead follow-ups, a send queue, bulk messaging with CSV/filter
support, and manual retry on failed sends. `ordernotifier`'s scope is narrower (order
lifecycle events only) — the interview checklist below (§16.1) decides how much of
this breadth needs replicating versus deliberately leaving out.

⚠️ **STATUS: schema below is a draft skeleton for the WhatsApp+SMS shape, NOT fully
locked.** The channel decision (§16.0) is settled; event list, message templates, and
SMS provider specifics are still open — see §16.1 and §17.1.

```sql
-- DRAFT — channel shape settled (§16.0), remaining fields subject to the §16.1 interview
CREATE TABLE tblordernotifier_templates (
  id           INT(11)     NOT NULL AUTO_INCREMENT,
  event_type   VARCHAR(50) NOT NULL,   -- e.g. 'order_confirmed' | 'order_shipped' | ... — FINALIZE IN INTERVIEW
  channel      VARCHAR(20) NOT NULL DEFAULT 'whatsapp',  -- 'whatsapp' | 'sms'
  template     TEXT        NOT NULL,   -- placeholder text, e.g. {{customer_name}}, {{order_id}}, {{total}}
  is_active    TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY event_channel (event_type, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tblordernotifier_log (
  id                 INT(11)     NOT NULL AUTO_INCREMENT,
  ecomcore_order_id  INT(11)     DEFAULT NULL,
  event_type         VARCHAR(50) NOT NULL,
  channel            VARCHAR(20) NOT NULL DEFAULT 'whatsapp',  -- 'whatsapp' | 'sms' — same event may fire on both
  phone              VARCHAR(20) NOT NULL,
  message            TEXT        NOT NULL,
  status             VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending/sent/failed
  provider_response  JSON        DEFAULT NULL,
  sent_at            DATETIME    DEFAULT NULL,
  created_at         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY ecomcore_order_id (ecomcore_order_id), KEY status (status), KEY channel (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Soft dependency only on `ecomcore` (listens to its hooks) — does not require
`courier`/`returns`/`inventory` to be installed; a notification tied to a hook from a
module that isn't installed simply never fires. Two credential rows expected in
`tblecomcore_credentials` (`owner_module='ordernotifier'`): one `label='BizBot
WhatsApp'` (`payload = {api_key, channel_guid}`, per the contract above) and one
`label='Bulk SMS'` (payload shape TBD — depends entirely on the provider the user
supplies later).

### 16.1 Interview checklist — ask before Phase 9 starts, do not default any of these

1. Exact event list to notify on — order confirmed? shipped? delivered? cancelled?
   return approved/rejected? payment received? Which `ecomcore`/`courier`/`returns`
   hook does each one bind to?
2. Message template ownership — plain text with placeholders (as sketched above), or
   does `ordernotifier` need a template-manager UI at all, or is a fixed hardcoded
   message per event acceptable for MVP?
3. ~~WhatsApp only, or also SMS?~~ **Resolved (§16.0): both.** Remaining: does every
   event notify on both channels, or does the channel vary per event (e.g. WhatsApp
   for confirmation, SMS as a fallback if WhatsApp fails)?
4. Who receives notifications — customer only, or also staff/admin/assigned agent
   (BizBot's existing module supports "Flexible Recipients" — does `ordernotifier`
   need the same breadth, or is customer-only sufficient for MVP)?
5. Retry policy on failed sends — manual retry only (mirrors BizBot's own pattern), or
   automatic retry with backoff? Does a failed WhatsApp send automatically fall back
   to SMS, or are the two channels independent?
6. Rate/cost awareness — does the BizBot plan or the (not-yet-chosen) SMS provider
   have a message quota/cost-per-message similar to the fraud-check API's
   plan-tiering (§9)? If so, does `ordernotifier` need the same caching/throttling
   discipline?
7. Bulk SMS provider identity and API contract — **entirely TBD.** Do not start any
   SMS-sending code until the user supplies the provider name, API docs, and
   credentials. WhatsApp-only can ship first if SMS specs aren't ready when Phase 9
   starts — confirm with the user whether that phased rollout within Phase 9 is
   acceptable, rather than assuming it is.

---

## 17. Process & Governance — MANDATORY, applies to every phase

This section is binding operating procedure for whoever executes this plan — expected
to be a different developer, on a different machine, in a fresh session with no memory
of how this plan was produced. It exists because the phases have real dependencies
(§2/§12) and because some decisions in this plan (currently: all of §16.1) were
deliberately left for the implementer to gather from the user rather than assumed.

### 17.1 Interview-first — no default decisions, ever
Before starting **any** phase, and before making **any** implementation decision that
is not already explicitly locked in §11/§13 of this document, the implementer must
stop and ask the user. This includes, but is not limited to:
- Anything marked ⚠️ / DRAFT / "needs interview" in this document (currently: the
  entire §16.1 checklist, and nothing else — every other module's design questions
  are already resolved in §11/§13).
- Any judgment call this document doesn't explicitly resolve. Low-stakes cosmetic
  choices (an icon, a menu position number, exact wording of a UI label) are
  reasonable to decide without asking — but anything touching the data model,
  business logic, or a user-facing workflow must be confirmed, not guessed.
- If a phase's acceptance criteria (§15) can be satisfied more than one way, ask which
  way before building, not after.

**Never proceed on an assumption when a real decision is required.** This document
being thorough is not permission to stop asking — it is the record of what has
*already* been asked and answered. Anything not written into §11 or §13 as a decision
has not been decided yet, regardless of how obvious it may seem.

### 17.2 Strict phase-gating — one module at a time, no exceptions
Modules are built **in the exact order given in §12**, one at a time:
- Do not start Phase N+1's code until Phase N's acceptance criteria (§15) are fully
  met **and** the ledger (§17.3) shows Phase N as `Done`.
- Do not leave a module partially built to go work on another. If blocked mid-phase,
  record the blocker in the ledger and resolve it (asking the user if needed) before
  moving on — never skip ahead to unblock yourself on a different module.
- Reading/exploring a later phase's dependencies for context is fine; writing its code
  or schema before its turn is not.

This is intentionally strict: the modules have real data dependencies (§2), and
building e.g. `purchases` against a not-yet-finalized `inventory` schema produces
exactly the kind of silent mismatch this entire plan exists to prevent.

### 17.3 Two companion files — kept separate from this document, on purpose

This architecture document (`ecomcore-architecture-plan.md`) is the **fixed
reference** — it holds only pre-implementation decisions (§1-§16) and this governance
section. Two companion files travel alongside it and are updated *during*
implementation, never this one:

- **`ecomcore-ledger.md`** — phase-by-phase progress tracker. Update it the moment a
  phase starts, blocks, or finishes — not retroactively at the end of a session. This
  is what makes §17.2 enforceable across sessions/machines: before starting work,
  check this file to see the true current phase.
- **`ecomcore-technical-debates.md`** — a running log of *new* technical debates or
  decisions that come up during actual coding (things this plan didn't anticipate).
  Keep these out of code comments and out of the architecture file — §11/§13 stay a
  clean historical record of the pre-build decisions. New debates get their own entry
  in the companion file, following the same rigor used throughout this document:
  verify the relevant fact first (don't assume framework behavior), present the real
  options, then record the user's actual decision — not a default.

Templates for both files exist alongside this plan
(`docs/ecomcore-ledger.md`, `docs/ecomcore-technical-debates.md`) — use them as-is.
