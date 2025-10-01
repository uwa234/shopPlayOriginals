# Pre-Order Product Settings Fix

## Issue
When admin users input deposit amounts and other settings for individual products in the pre-order settings page, the values were not appearing on the frontend checkout page. The fields would save successfully but the saved values would not be reflected when editing the pre-order or calculating deposit amounts on the frontend.

## Root Causes

### 1. Missing Pivot Data in Form
**File:** `platform/plugins/ecommerce/src/Forms/PreOrderForm.php`

The product data passed to the dynamic JavaScript view (`product-settings-dynamic.blade.php`) did not include the pivot table data (saved settings from `ec_pre_order_products` table).

**Fixed:** Modified lines 90-107 to include pivot data when passing products to the view.

### 2. JavaScript Not Populating Saved Values
**File:** `platform/plugins/ecommerce/resources/views/pre-orders/partials/product-settings-dynamic.blade.php`

The JavaScript that dynamically generates product setting fields was creating empty input fields without populating them with existing values from the database.

**Fixed:** Modified the `generateProductSettings` function to:
- Extract pivot data from the product object
- Populate input fields with saved values (price, max_quantity, deposit_amount, deposit_percentage)
- Set checkbox and readonly field values correctly

### 3. Empty Values Saved as 0 Instead of NULL
**File:** `platform/plugins/ecommerce/src/Http/Controllers/PreOrderController.php`

When fields were left empty (which is valid - means use default), they were being saved as `0` instead of `null`. This caused:
- Custom price of 0 instead of using the product's actual price
- Deposit amounts of 0 to be treated as "set" instead of "not set"

**Fixed:** 
- Modified the `store()` method (lines 37-50) to save `null` for empty fields
- Modified the `update()` method (lines 80-93) to save `null` for empty fields
- Changed `?? 0` to `!empty($value) ? $value : null` pattern

### 4. Inefficient and Incorrect Deposit Calculation
**File:** `platform/plugins/ecommerce/src/Models/PreOrder.php`

The `calculateDepositAmount()` method had two issues:
- Querying the pivot data twice (performance issue)
- Not correctly handling 0 values in pivot price field

**Fixed:**
- Fetch pivot data once and reuse it
- Use `!empty()` check instead of just truthy check to properly handle 0 vs null
- Correctly fall back to product price when pivot price is null or 0

## Testing Steps

1. **Create/Edit Pre-Order Campaign:**
   - Go to admin panel → E-commerce → Pre-orders
   - Edit an existing pre-order or create new one
   - Select products
   - Set individual product settings:
     - Custom deposit amount (e.g., 15000)
     - Custom deposit percentage (e.g., 25)
     - Custom price (optional)
     - Max quantity (optional)

2. **Verify Settings Are Saved:**
   - Save the pre-order
   - Re-open the edit page
   - Confirm all fields show the values you entered

3. **Test Frontend Display:**
   - Add pre-order products to cart
   - Go to checkout page
   - Verify the deposit amounts match what you configured in admin
   - Test selecting deposit vs full payment options
   - Confirm prices are correct

4. **Test Global vs Product-Specific Settings:**
   - Set a global deposit amount on the pre-order (e.g., 10000)
   - Set a product-specific deposit amount (e.g., 15000)
   - Verify the product-specific setting overrides the global one on frontend

## Database Cleanup (If Needed)

If you have existing pre-orders with incorrectly saved `0` values, you may need to run:

```sql
-- Check for products with price = 0 in pre-orders
SELECT * FROM ec_pre_order_products WHERE price = 0;

-- Fix: Set price to NULL where it's 0 (to use default product price)
UPDATE ec_pre_order_products SET price = NULL WHERE price = 0;

-- Check for deposit amounts = 0
SELECT * FROM ec_pre_order_products WHERE deposit_amount = 0;

-- Fix: Set deposit_amount to NULL where it's 0 (to use global setting)
UPDATE ec_pre_order_products SET deposit_amount = NULL WHERE deposit_amount = 0;

-- Check for deposit percentages = 0
SELECT * FROM ec_pre_order_products WHERE deposit_percentage = 0;

-- Fix: Set deposit_percentage to NULL where it's 0 (to use global setting)
UPDATE ec_pre_order_products SET deposit_percentage = NULL WHERE deposit_percentage = 0;
```

## Files Modified

1. `platform/plugins/ecommerce/src/Forms/PreOrderForm.php`
2. `platform/plugins/ecommerce/resources/views/pre-orders/partials/product-settings-dynamic.blade.php`
3. `platform/plugins/ecommerce/src/Http/Controllers/PreOrderController.php`
4. `platform/plugins/ecommerce/src/Models/PreOrder.php`

## Impact

- ✅ Product-specific settings now properly save and load
- ✅ Deposit amounts correctly reflect on frontend
- ✅ Empty fields correctly use default values
- ✅ No performance regression (actually improved by reducing duplicate queries)
- ✅ Backward compatible with existing pre-orders (after optional database cleanup) 