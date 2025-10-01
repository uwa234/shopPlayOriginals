# Testing Product-Specific Deposits (Global Toggle OFF)

## Issue Fixed
**Problem:** Individual product deposit amounts only appeared when the global "Requires Deposit" toggle was ON. If the toggle was OFF, product-specific deposits were hidden.

**Solution:** Modified logic to check if a product has specific deposit settings and show deposit options regardless of global toggle state.

---

## 🧪 Test Scenarios

### Scenario 1: Global Toggle OFF + Product Has Custom Deposit

**Setup:**
1. Go to Admin → E-commerce → Pre-orders → Edit Pre-order #2
2. Set global settings:
   - ✅ **"Allow Full Payment"**: ON  
   - ❌ **"Requires Deposit"**: OFF
   - Global Deposit Amount: 15000 (doesn't matter since toggle is OFF)
3. Individual product settings:
   - **Vintage Denim Jacket**: Deposit Amount = 13500
   - **Floral Maxi Dress**: Deposit Amount = 1500
4. **Save**

**Expected Frontend Behavior:**

When you add "Vintage Denim Jacket" to cart and go to checkout:
- ✅ Should show "Pre-Order Payment Options" section
- ✅ Should show TWO radio options:
  - **Deposit Option** (₦13,500.00) ← Product-specific
  - **Full Payment** (full price)
- ✅ Both options should be selectable

**Why it works:** Product has a custom deposit amount, so deposit option shows even though global "Requires Deposit" is OFF.

---

### Scenario 2: Global Toggle OFF + Product Has NO Custom Deposit

**Setup:**
1. Same pre-order settings as above
2. Product without custom deposit:
   - **Leather Ankle Boots**: Deposit Amount = (empty)

**Expected Frontend Behavior:**

When you add "Leather Ankle Boots" to cart and go to checkout:
- ✅ Should show "Pre-Order Payment Options" section
- ✅ Should show ONLY:
  - **Full Payment** option
- ❌ Should NOT show deposit option (no custom deposit + global toggle OFF)

**Why it works:** Product has no custom deposit AND global "Requires Deposit" is OFF, so only full payment is available.

---

### Scenario 3: Global Toggle ON + Product Has Custom Deposit

**Setup:**
1. Edit Pre-order #2:
   - ✅ **"Requires Deposit"**: ON
   - ✅ **"Allow Full Payment"**: ON
   - Global Deposit: 15000
2. Product settings:
   - **Vintage Denim Jacket**: Deposit = 13500

**Expected Frontend Behavior:**

When you add "Vintage Denim Jacket" to cart:
- ✅ Shows both deposit and full payment options
- ✅ Deposit shows **₦13,500** (product-specific, NOT ₦15,000 global)

---

### Scenario 4: Global Toggle ON + Product Has NO Custom Deposit

**Setup:**
1. Same as Scenario 3
2. Product:
   - **Leather Ankle Boots**: (no custom deposit)

**Expected Frontend Behavior:**

When you add "Leather Ankle Boots":
- ✅ Shows both deposit and full payment options
- ✅ Deposit shows **₦15,000** (falls back to global)

---

## 📋 Step-by-Step Testing

### Step 1: Set Global Toggle OFF

```bash
# Go to admin panel
http://localhost/admin/pre-orders/2/edit

# Settings:
- Requires Deposit: ❌ OFF
- Allow Full Payment: ✅ ON
- Global Deposit Amount: 15000

# Individual Products:
- Vintage Denim Jacket: Deposit = 13500
- Floral Maxi Dress: Deposit = 1500  
- Leather Ankle Boots: Deposit = (empty)

# Save
```

### Step 2: Test Product WITH Custom Deposit

1. **Clear browser cache** (Cmd+Shift+R or Incognito)
2. Go to shop, add **"Vintage Denim Jacket"** to cart
3. Go to checkout
4. **Look for "Pre-Order Payment Options"** section
5. **Verify** you see:
   ```
   [o] Deposit Option (₦13,500.00)
       Pay ₦13,500.00 now, ₦X later
   
   [ ] Full Payment (₦X.XX)
       Pay full amount now
   ```

6. ✅ **PASS:** Both options appear even though global toggle is OFF
7. ❌ **FAIL:** If deposit option doesn't appear → Check logs

### Step 3: Test Product WITHOUT Custom Deposit

1. Clear cart
2. Add **"Leather Ankle Boots"** to cart
3. Go to checkout
4. **Verify** you see:
   ```
   [o] Full Payment (₦X.XX)
       Pay full amount now
   ```
5. ✅ **PASS:** Only full payment shows (correct, no custom deposit)
6. ❌ **FAIL:** If deposit option appears → Something wrong

### Step 4: Check Debug Logs

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/shopPlayOriginals
tail -f storage/logs/laravel.log
```

Refresh checkout page and look for:

```
[timestamp] local.INFO: Checking product deposit options 
{
  "product_id": 1,
  "product_name": "Vintage Denim Jacket",
  "has_product_specific_deposit": true,    ← Should be TRUE
  "pivot_deposit_amount": "13500.00",      ← Your custom amount
  "pivot_deposit_percentage": null,
  "global_requires_deposit": false,        ← Global toggle is OFF
  "global_allow_full_payment": true
}
```

**For product WITHOUT custom deposit:**
```
{
  "product_id": 3,
  "product_name": "Leather Ankle Boots",
  "has_product_specific_deposit": false,   ← FALSE (no custom)
  "pivot_deposit_amount": null,            ← No custom deposit
  "pivot_deposit_percentage": null,
  "global_requires_deposit": false,        ← Global toggle OFF
  "global_allow_full_payment": true
}
```

---

## 🔍 What Changed in Code

### File: `preorder-payment-options.blade.php`

**Before (line 22):**
```php
if ($activePreOrder && ($activePreOrder->requires_deposit || $activePreOrder->allow_full_payment)) {
    // Only checked GLOBAL settings
}
```

**After:**
```php
// Check if product has specific deposit settings
$hasProductSpecificDeposit = !empty($productPivot?->deposit_amount) 
                           || !empty($productPivot?->deposit_percentage);

// Show deposit options if:
// 1. Product has specific deposit settings, OR ← NEW!
// 2. Global requires_deposit is true, OR
// 3. Global allow_full_payment is true
$showDepositOptions = $hasProductSpecificDeposit 
                   || $activePreOrder->requires_deposit 
                   || $activePreOrder->allow_full_payment;
```

**Logic:**
- If product has `deposit_amount` or `deposit_percentage` set → Always show deposit option
- Otherwise → Use global toggles

---

## ✅ Success Criteria

All these should be TRUE:

- ✅ Product with custom deposit shows deposit option even when global toggle is OFF
- ✅ Product without custom deposit respects global toggle settings
- ✅ Product with custom deposit uses its own amount, not global
- ✅ Deposit and full payment options both work correctly
- ✅ Logs show `has_product_specific_deposit: true` for products with custom amounts
- ✅ Logs show `has_product_specific_deposit: false` for products without custom amounts

---

## 🐛 Troubleshooting

### Problem: Deposit option still not showing

**Check 1:** Did you save the product deposit amount in admin?
```bash
php artisan tinker --execute="DB::table('ec_pre_order_products')->where('pre_order_id', 2)->where('product_id', 1)->first();"
```
Should show: `deposit_amount: "13500.00"`

**Check 2:** Clear all caches
```bash
php artisan view:clear
php artisan cache:clear
```

**Check 3:** Hard refresh browser (Cmd+Shift+R)

**Check 4:** Check logs for errors
```bash
tail -f storage/logs/laravel.log
```

### Problem: Shows wrong deposit amount

Check logs to see which deposit is being used:
- `Using product-specific deposit amount` → Custom deposit
- `Using global deposit amount` → Global deposit

---

## 📝 Summary

**Key Improvement:**
Product-specific deposit settings now **override** global toggle state. This allows you to:
- Keep global "Requires Deposit" OFF (optional for most products)
- Set custom deposits on specific products (required for those products)
- Each product independently controls its deposit behavior

**Files Modified:**
- ✅ `preorder-payment-options.blade.php` - Added product-specific deposit check 