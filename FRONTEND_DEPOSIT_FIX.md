# Frontend Deposit Amount Fix - Part 2

## Issue
Individual product deposit amounts were saving in admin but not showing on the frontend checkout page. The frontend was only showing the global deposit amount or product price, ignoring product-specific settings.

## Root Cause
The `PreOrderService::getActivePreOrderForProduct()` method was **NOT eager loading** the `products` relationship with pivot data. This caused:

1. When `calculateDepositAmount()` was called, it had to make a separate database query
2. The pivot data (deposit_amount, deposit_percentage, price) wasn't being retrieved correctly
3. The method fell back to global settings instead of using product-specific values

## The Fix

### File 1: `platform/plugins/ecommerce/src/Services/PreOrderService.php`

**Added eager loading** on line 34-36:
```php
->with(['products' => function ($query) use ($product) {
    $query->where('product_id', $product->id);
}])
```

This ensures when the PreOrder is retrieved, it comes **pre-loaded** with the product's pivot data including:
- `deposit_amount` 
- `deposit_percentage`
- `price`
- `max_quantity`
- etc.

### File 2: `platform/plugins/ecommerce/src/Models/PreOrder.php`

**Optimized pivot data retrieval** in `calculateDepositAmount()`:
1. First tries to use the eager-loaded relationship (fast)
2. Only makes a database query if relationship wasn't loaded (fallback)
3. Added debug logging to trace what values are being used

## How to Test

### Step 1: Clear Cache
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/shopPlayOriginals
php artisan cache:clear
php artisan view:clear
```

### Step 2: Verify Database Values
```bash
php artisan tinker --execute="DB::table('ec_pre_order_products')->join('ec_products', 'ec_pre_order_products.product_id', '=', 'ec_products.id')->select('ec_pre_order_products.*', 'ec_products.name')->where('pre_order_id', 2)->get()->each(function(\$p) { echo \$p->name . ' => Deposit: ' . (\$p->deposit_amount ?? 'uses global') . PHP_EOL; });"
```

Expected output:
```
Vintage Denim Jacket => Deposit: 12000.00
Floral Maxi Dress => Deposit: 1000.00
Leather Ankle Boots => Deposit: uses global
Knit Turtleneck Sweater => Deposit: uses global
```

### Step 3: Test on Frontend

1. **Clear browser cache** (Cmd+Shift+R or use Incognito)

2. **Add product to cart:**
   - Go to shop
   - Add "Vintage Denim Jacket" (should use deposit: 12,000)
   - Go to checkout

3. **Check the Pre-Order Payment Options section:**
   - Should show: "Deposit Option (12,000.00)" ✅
   - NOT "Deposit Option (15,000.00)" ❌

4. **Test another product:**
   - Go back, add "Floral Maxi Dress" 
   - Go to checkout
   - Should show: "Deposit Option (1,000.00)" ✅

5. **Test global deposit:**
   - Add "Leather Ankle Boots" (no custom deposit)
   - Should show: "Deposit Option (15,000.00)" ✅ (uses global)

### Step 4: Check Debug Logs

View the logs to see what's happening:
```bash
tail -f storage/logs/laravel.log
```

Look for entries like:
```
[timestamp] local.INFO: Calculating deposit for product 
{
  "product_id": 1,
  "product_name": "Vintage Denim Jacket",
  "relation_loaded": true,
  "pivot_deposit_amount": "12000.00",
  "pivot_deposit_percentage": null,
  "pivot_price": null,
  "global_deposit_amount": "15000.00",
  "global_deposit_percentage": null
}
[timestamp] local.INFO: Using product-specific deposit amount {"deposit": 12000}
```

## What Changed - Technical Details

### Before:
```php
// PreOrderService - line 30-38
public function getActivePreOrderForProduct(Product $product): ?PreOrder
{
    return PreOrder::query()
        ->active()
        // ❌ No eager loading!
        ->whereHas('products', function ($query) use ($product) {
            $query->where('product_id', $product->id)
                  ->where('is_active', true);
        })
        ->first();
}
```

Result: PreOrder returned WITHOUT product relationship loaded.

### After:
```php
public function getActivePreOrderForProduct(Product $product): ?PreOrder
{
    return PreOrder::query()
        ->active()
        // ✅ Eager load the relationship!
        ->with(['products' => function ($query) use ($product) {
            $query->where('product_id', $product->id);
        }])
        ->whereHas('products', function ($query) use ($product) {
            $query->where('product_id', $product->id)
                  ->where('is_active', true);
        })
        ->first();
}
```

Result: PreOrder returned WITH product relationship AND pivot data pre-loaded.

## Troubleshooting

### Problem: Still showing wrong deposit amount

**Solution 1 - Clear all caches:**
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

**Solution 2 - Hard refresh browser:**
- Chrome/Safari: Cmd+Shift+R
- Or use Incognito/Private window

**Solution 3 - Check database:**
```sql
SELECT p.name, pp.deposit_amount, pp.deposit_percentage, pp.price
FROM ec_pre_order_products pp
JOIN ec_products p ON pp.product_id = p.id
WHERE pp.pre_order_id = 2;
```

### Problem: Logs show "relation_loaded": false

This means eager loading didn't work. Check:
1. Did you save the PreOrderService.php file?
2. Did you clear the cache?
3. Is there any OpCache or other caching?

Try restarting PHP-FPM or Apache:
```bash
# For XAMPP on Mac
sudo /Applications/XAMPP/xamppfiles/bin/apachectl restart
```

### Problem: Logs show pivot_deposit_amount as null

This means the data isn't in the database. Check:
1. Go to admin panel
2. Edit the pre-order
3. Verify the deposit amounts are filled in
4. Save again
5. Check database directly

## Files Modified

1. ✅ `platform/plugins/ecommerce/src/Services/PreOrderService.php` - Added eager loading
2. ✅ `platform/plugins/ecommerce/src/Models/PreOrder.php` - Optimized pivot retrieval + debug logs
3. ✅ `platform/plugins/ecommerce/src/Forms/PreOrderForm.php` - Pivot data in admin form (from earlier fix)
4. ✅ `platform/plugins/ecommerce/resources/views/pre-orders/partials/product-settings-dynamic.blade.php` - Populate saved values (from earlier fix)
5. ✅ `platform/plugins/ecommerce/src/Http/Controllers/PreOrderController.php` - Save NULL not 0 (from earlier fix)

## Performance Benefits

**Before:** 
- 1 query to get PreOrder
- N queries to get pivot data for each product (N+1 problem)

**After:**
- 1 query to get PreOrder with all pivot data (eager loaded)
- 0 additional queries needed ✅

## Next Steps

1. ✅ Test on frontend with all scenarios
2. ✅ Verify logs show correct values
3. ✅ Test with multiple products in cart
4. ✅ Once confirmed working, **remove the debug logging** from PreOrder.php to clean up logs

## Removing Debug Logs (After Testing)

Once you confirm it's working, remove lines with `\Log::info` from:
- `platform/plugins/ecommerce/src/Models/PreOrder.php` (lines added with debug logging)

This will keep your logs clean in production. 