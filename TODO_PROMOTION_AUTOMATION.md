# Promotion Automation TODO

## Drop Config Additions
- [x] Capture promotion type (`price_markdown`, `bundle_bogo`, `clearance`, `custom_design_reward`).
- [x] For markdown: support percent or fixed discounts with scope (`all_items`, explicit SKU list).
- [x] For bundles: define eligible SKUs, free SKUs/quantities, and per-cart limits.
- [x] For clearance: flag SKUs for inclusion in the "Clearance" collection.
- [x] Globally enable "3D Custom Design Reward" with a fixed discount granted when a customer purchase includes another creator's design.

## Promotion Engine (`core/drop_promotions.php`)
- [x] Read active drop configuration and detect promotion states.
- [x] On activation, persist original prices per SKU and apply markdowns.
- [x] Seed bundle rules into shared storage for the cart handler.
- [x] Move clearance SKUs into a dedicated category flag.
- [x] Enable custom design reward tracking, linking drop orders to originating designers.
- [x] On deactivation, revert everything using stored originals and clear bundle/reward toggles.
- [x] Log each activation and deactivation event.

## Custom 3D Reward Flow
- [x] Confirm design ownership tracking links orders to the originating designer.
- [x] When a design sells during a drop to a different customer, credit the creator with the configured discount.
- [x] Store rewards in a wallet (table or coupon ledger) and expose them in "My Account".
- [x] Auto-apply available rewards during checkout when appropriate.

## Activation Triggers
- [x] Implement a cron-friendly command (e.g., `php artisan drop:sync` style) that enforces schedule boundaries.
- [x] Add a lazy trigger inside `get_active_flash_banner()` that reconciles stored promotion state.
- [x] Add a safety lock so the engine only runs once per state transition even with multiple triggers.

## Cart & Checkout Updates
- [x] Ensure listing, cart, and checkout use active markdown prices.
- [x] Apply bundle freebies automatically, enforce limits, and display line-item notes.
- [x] Apply the custom-design reward discount when qualifying orders are placed and record usage.

## Admin & Operations
- [x] Build a "Promotion Status" panel showing current state, last activation, and manual override controls.
- [ ] Provide exports for waitlist signups and rewarded designers.

## Testing & Logging
- [ ] Add PHPUnit coverage for activation/deactivation, price rollback, bundle logic, and reward crediting.
- [x] Log promotion state changes and reward grants for audit trails.
