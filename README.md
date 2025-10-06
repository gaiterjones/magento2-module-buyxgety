# Gaiterjones Magento 2 — Buy X / Spend X Get Y

Promotional cart logic for Magento 2 that automatically adds or removes **Product Y** based on either:

- **Buy X Get Y**: when the cart contains qualifying quantities of **Product X**  
- **Spend X Get Y**: when the cart subtotal falls within configured thresholds

Tested on **Magento Open Source 2.4.7** (PHP 8.1).

---

## Features

- Buy-X rules with **min/max** quantity and optional **wildcard SKU** matching (`<like>TEXT</like>`).
- Spend-X rules with **MIN** and **MAX** subtotal thresholds (MAX `0` = unlimited).
- Auto-add Y (qty 1), auto-remove Y when conditions no longer match.
- Quantity policing for Y (keeps it at 1 if customers try to increase it).
- **Frontend-only** observer + **recursion guard** to avoid infinite loops.
- Optional **debug logging** via system config.
- Safe handling when multiple tiers are configured.

---

## Installation

```bash
composer require gaiterjones/magento2-module-buyxgety
bin/magento module:enable Gaiterjones_BuyXGetY
bin/magento setup:upgrade
bin/magento cache:flush
```

> If installing directly from GitHub, add a VCS repository to your composer.json or clone to `app/code/Gaiterjones/BuyXGetY`.

---

## Configuration

**Stores → Configuration → Gaiterjones → Buy X Get Y**

### General
- **Enable Debug Logging**  
  `buyxgety/general/debug` (Yes/No). Logs helpful messages during rule evaluation.

### Buy X Get Y
- **Enable Buy X Get Y**  
- **Product X SKU List**  
  Comma-separated SKUs. For wildcard matching, wrap the match text in `<like>...</like>` (e.g. `<like>ABC-</like>` matches `ABC-123`, `ABC-XYZ`).
- **Product X Minimum Required Quantity**  
  Comma-separated integers (aligned 1:1 with Product X SKUs).
- **Product X Maximum Allowed Quantity**  
  Comma-separated integers (aligned 1:1 with Product X SKUs). Use `0` for unlimited.
- **Product Y SKU List**  
  Comma-separated SKUs (aligned 1:1 with Product X SKUs).
- **Product Y Description**  
  Comma-separated descriptions (aligned 1:1), **or a single value reused for all rules** (e.g. `Free Gift`).

### Spend X Get Y
- **Enable Spend X Get Y**  
- **Product Y SKU List**  
  Comma-separated SKUs.
- **Cart MAXIMUM subtotal to qualify**  
  Comma-separated numbers aligned 1:1 with Product Y SKUs. Use `0` for **no upper limit**.
- **Cart MINIMUM subtotal to qualify**  
  Comma-separated numbers aligned 1:1 with Product Y SKUs.
- **Product Y Description**  
  Comma-separated descriptions (aligned 1:1) **or a single value reused for all rules**.

> **Currency note:** Spend-X compares against **store-currency** subtotal by default (`getSubtotalWithDiscount()`). If you want thresholds in **base currency**, switch to `getBaseSubtotalWithDiscount()` in the model.

---

## How it works

### Buy X Get Y
- When cart quantity of **X** is within `[min, max]` for a rule, **Y** is added (qty 1).
- If Y is present and qty > 1, it’s reduced back to 1.
- If cart falls outside `[min, max]`, Y is removed.
- Wildcard X uses a substring match over item SKUs.

### Spend X Get Y
- For each Y rule, if `MIN ≤ cart subtotal ≤ MAX` (or `MIN ≤ subtotal` when MAX = 0), **Y** is added (qty 1).
- Falling outside the range removes Y.

---

## Example configs

### Spend X tiers
```
Product Y SKU List:          FREE-STUFF2,FREE-STUFF4
Cart MINIMUM subtotal:       50,101
Cart MAXIMUM subtotal:       100,999
Product Y Description:       SPEND X Free Gift
```
- Adds `FREE-STUFF2` between 50–100, and `FREE-STUFF4` between 101–999.  
- Single description applies to both tiers.

### Buy X with wildcard
```
Product X SKU List:          <like>SET-</like>,ABC-123
Min Required Quantity:       2,3
Max Allowed Quantity:        0,5
Product Y SKU List:          FREE-SET,FREE-ABC
Product Y Description:       Free Gift,Free ABC Gift
```
- Any SKU starting with `SET-` qualifies when qty ≥ 2 (no upper cap).  
- `ABC-123` qualifies when 3–5 units in cart.

---

## Debugging

- Turn on **Enable Debug Logging** and watch your logs (e.g. `var/log/debug.log` depending on logger config).
- The module logs threshold checks, item add/remove, and quantity policing.

---

## Limitations & Notes

- **Product Y** should be salable and **not require options**. If Y is configurable/bundle with required selections, Magento will reject automatic add.  
  *Tip:* you can extend `addProductToCart()` to use MSI’s `IsProductSalableInterface` and/or skip products with `getRequiredOptions()`.
- Cart matching for configurables uses the **child simple SKU** (that’s what ends up on the quote item).
- If you enable both **Buy X** and **Spend X** rules that add the **same Y**, last writer wins (your rules should avoid overlap).

---

## Troubleshooting

- **“Configuration is invalid.”**  
  - Ensure counts align (X SKUs, mins, maxes, Y SKUs).  
  - For descriptions: provide **one** value (reused) or exactly N values.  
  - Numbers only for mins/maxes; MAX `0` = unlimited.
- **Y doesn’t add**  
  - Y requires options or isn’t salable / out of stock.  
  - Thresholds are in a different currency than expected (switch base vs store subtotal in code).  
  - Event not firing on frontend (ensure `etc/frontend/events.xml` is present).
- **Observer loops / multiple runs**  
  - Ensure the **registry guard** is in place.  
  - Avoid calling observers from admin area (frontend scope only).

---

## Development

```bash
bin/magento cache:clean
bin/magento cache:flush
# optional during development
bin/magento setup:di:compile
```

Consider adding CI (PHP lint/phpstan) and unit tests around the rule evaluators.

---

## Contributing

Issues and PRs welcome. Please:
- Describe the scenario, expected vs actual behaviour.
- Include your config values (redact sensitive data).
- Note Magento & PHP versions.

---

## Licence

See the `LICENSE` file in this repository.

---

## Credits

Authored by **gaiterjones**. Original module created ~2017, refreshed for Magento **2.4.7** in 2025.
