# Billing Plugin for Pelican Panel

**Author:** Fywolf
**Requires:** Pelican Panel · Filament v4 · PHP 8.2+

A production-ready billing plugin for game hosting panels. Handles the full lifecycle of server orders — from checkout to automatic monthly renewals — entirely through Stripe Subscriptions.

---

## Features

### Stripe Subscriptions (Automatic Recurring Billing)
- Checkout uses **Stripe Subscription mode** — users are charged automatically every billing cycle
- No manual renewals required; Stripe handles the recurring charges
- Webhook-driven lifecycle:
  - `checkout.session.completed` → activates the order and provisions the server
  - `invoice.paid` → extends subscription, unsuspends server if in grace period
  - `invoice.payment_failed` → moves order to grace period
  - `customer.subscription.deleted` → expires the order and suspends the server
- Stripe Customers are created automatically and reused across orders
- Cancelling a subscription in the panel immediately cancels it in Stripe

### Products & Pricing
- Create products with server specs: CPU, RAM, disk, swap, I/O weight
- Multiple price tiers per product (e.g. monthly, quarterly, yearly)
- Prices are synced as **recurring Stripe Prices** automatically; one-time prices are detected and recreated
- Products grouped by **category** (e.g. Minecraft, VPS) with configurable sort order
- Per-price **environment variable overrides** for server startup configuration

### Store (Customer Dashboard)
- **My Servers** section at the top — shows all active servers as cards linking to the server console
- Products listed below, grouped by category with a heading per category
- Each product card shows CPU, RAM, disk, optional limits, and one order button per price tier
- **Coupon code** input on each product card (applied at Stripe checkout)
- Free tier products activate instantly with no payment
- Trial support: first-time trial orders skip payment for a configurable number of days

### Order Lifecycle

| Status | Description |
|---|---|
| Pending | Checkout session open, payment not yet received |
| Active | Subscription active, server running |
| Grace Period | Payment failed or expired — server stays online during grace window |
| Expired | Grace period exhausted — server suspended, subscription cancelled |
| Closed | Cancelled by user or admin |

- Automated expiry check every 5 minutes via `p:billing:check-orders`
- Configurable **grace period** (0–168 hours) before suspension on failed renewal
- Stale pending orders (>2 hours old) are automatically closed and Stripe sessions expired

### Server Provisioning
- Servers created automatically after payment via a queued job with retry logic (3 attempts)
- Supports **specific node targeting** or **auto-deployment** via node tags
- Port range filtering for allocation selection
- On plan upgrade/downgrade, server startup variables are updated immediately
- Servers unsuspended on renewal/reactivation, suspended on expiry/cancellation

### Coupons & Discounts
- Admin creates coupons with **fixed** or **percentage** discounts
- Coupons synced to Stripe and applied at checkout via Stripe's `discounts[]` parameter
- Coupon expiry dates and usage limits supported
- Customers enter coupon codes on the store — validated before checkout begins

### Order Confirmation Page
- Dedicated order confirmation page after successful checkout
- URL uses a **unique 64-character token** that expires after 24 hours (not guessable)
- Confirmation email sent automatically with PDF invoice attached

### Email Notifications
- Order confirmation email sent after activation (queued for background delivery)
- PDF invoice attachment includes: order reference, product, price, expiry date, company info
- PDF generation via `barryvdh/laravel-dompdf` — gracefully skipped if not installed

### Audit Logging
- Every significant event is recorded with context:
  - `order_activated`, `order_renewed`, `order_expired`, `order_closed`
  - `order_grace_period_started`, `order_plan_upgrade`, `order_plan_downgrade`
  - `server_created`, `stripe_webhook_payment_received`, `stripe_subscription_renewed`
  - `stripe_invoice_payment_failed`, `stripe_subscription_deleted`
  - Admin-triggered actions: `admin_order_activated`, `admin_order_closed`, `admin_order_plan_changed`
- Full audit log viewable per order in the admin panel

### Admin Panel
- **Products** — CRUD with category, sort order, server specs, deployment config (nodes, ports, tags)
- **Prices** — CRUD per product: interval, cost, trial days, environment overrides; auto-synced to Stripe
- **Coupons** — CRUD with type, value, expiry; auto-synced to Stripe
- **Orders** — Full table with status filters, gateway badge, customer/server links; actions: activate, change plan, cancel subscription, force-create server
- **Customers** — List with order history relation manager
- **Audit Logs** — Searchable log of all billing events
- **Settings** — Stripe credentials, webhook secret, currency, grace period, company info for invoices

### Customer Panel (App Panel)
- Store dashboard: My Servers → Products grouped by category
- Orders list with: status, server name, product, cost, expiry
- Per-order actions: open server console, change plan, pay pending order, cancel subscription

---

## Requirements

| Requirement | Version |
|---|---|
| Pelican Panel | Latest |
| PHP | 8.2+ |
| stripe/stripe-php | ^18.0 |
| barryvdh/laravel-dompdf | ^3.0 (optional — for PDF invoices) |

---

## Installation

1. Place the `billing` folder in your Pelican plugins directory
2. Install dependencies:
   ```bash
   composer require stripe/stripe-php:"^18.0" barryvdh/laravel-dompdf:"^3.0"
   ```
3. Run the database migrations:
   ```bash
   php artisan migrate
   ```
4. Go to **Admin → Settings → Billing** and enter your Stripe credentials
5. In the **Stripe Dashboard → Developers → Webhooks**, create a webhook endpoint:
   ```
   https://your-panel.com/webhooks/stripe
   ```
   Enable these events:
   ```
   checkout.session.completed
   invoice.paid
   invoice.payment_failed
   customer.subscription.deleted
   ```
6. Copy the webhook signing secret (`whsec_…`) into the Billing settings

---

## Settings Reference

| Setting | Description |
|---|---|
| Currency | USD, EUR, GBP, CAD, AUD |
| Grace Period (hours) | How long a server stays online after a failed renewal. 0 = suspend immediately |
| Default Node Tags | Tags used for auto-deployment when no specific nodes are selected on a product |
| Company Name / Address / Email / VAT / Website | Shown on PDF invoices |
| Stripe Publishable Key | `pk_live_…` |
| Stripe Secret Key | `sk_live_…` |
| Stripe Webhook Signing Secret | `whsec_…` |

---

## Database Migrations

| Migration | Description |
|---|---|
| `001_create_coupons_table` | Coupons with Stripe coupon sync |
| `002_create_customers_table` | Customer profiles linked to panel users |
| `003_create_products_table` | Products with server specs |
| `004_create_product_prices_table` | Pricing tiers per product |
| `005_create_orders_table` | Orders with payment and status tracking |
| `006_create_billing_audit_logs_table` | Audit log entries |
| `007_add_coupon_id_to_orders_table` | Coupon foreign key on orders |
| `008_add_confirmation_token_to_orders_table` | Expiring one-time confirmation tokens |
| `009_add_category_to_products_table` | Category and sort_order on products |
| `010_add_stripe_subscription_support` | `stripe_subscription_id` on orders, `stripe_customer_id` on customers |

---

## Scheduled Commands

One command is registered and runs automatically every 5 minutes:

```
php artisan p:billing:check-orders
```

This command:
- Moves **Active** orders past their `expires_at` into **Grace Period** (or directly to **Expired** if grace = 0)
- Moves **Grace Period** orders whose window has elapsed to **Expired**, cancels the Stripe subscription, and suspends the server
- Closes stale **Pending** orders older than 2 hours

---

## License

MIT — made by Fywolf
