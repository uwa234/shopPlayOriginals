# Pre-Order Fix Testing Guide

## Current Database State (Verified ✅)
- **No cleanup needed** - All values are correct (NULLs and proper deposits)
- PreOrder #2 "45687986" with Global Deposit: 15,000

### Products in PreOrder #2:
1. **Vintage Denim Jacket** - Custom deposit: **12,000** (overrides global)
2. **Floral Maxi Dress** - Custom deposit: **1,000** (overrides global)
3. **Leather Ankle Boots** - Uses global: **15,000**
4. **Knit Turtleneck Sweater** - Uses global: **15,000**

---

## 📋 Test Checklist

### ✅ Test 1: Admin Form Shows Saved Values
**URL:** http://localhost/admin/pre-orders/2/edit

**What to Check:**
1. [ ] Global deposit shows: **15000**
2. [ ] Scroll to product settings section
3. [ ] **Vintage Denim Jacket** should show:
   - Deposit Amount: **12000**
4. [ ] **Floral Maxi Dress** should show:
   - Deposit Amount: **1000**
5. [ ] **Leather Ankle Boots** should show:
   - Deposit Amount: **(empty - uses global)**
6. [ ] **Knit Turtleneck Sweater** should show:
   - Deposit Amount: **(empty - uses global)**

**Expected Result:** All fields should display the correct saved values ✅

---

### ✅ Test 2: Modify Values and Save
**In the same edit page:**

1. [ ] Change **Vintage Denim Jacket** deposit to: **13500**
2. [ ] Change **Leather Ankle Boots** deposit to: **8000** (add new custom)
3. [ ] Click **Save**
4. [ ] Wait for success message
5. [ ] Refresh the page (F5)
6. [ ] Verify the new values appear:
   - Vintage Denim Jacket: **13500** ✅
   - Leather Ankle Boots: **8000** ✅

**Expected Result:** Changed values persist after save and reload ✅

---

### ✅ Test 3: Frontend Display - Add to Cart
**URL:** http://localhost (or your frontend URL)

1. [ ] Navigate to the shop/products page
2. [ ] Find **Vintage Denim Jacket** (Product ID: 1)
3. [ ] Add it to cart
4. [ ] Go to checkout
5. [ ] Look for "Pre-Order Payment Options" section
6. [ ] Verify the deposit amount shows: **$13,500.00** (or 13500)

**Expected Result:** Custom deposit amount displays correctly ✅

---

### ✅ Test 4: Frontend - Product Using Global Deposit
**Continue from checkout:**

1. [ ] Go back to shop
2. [ ] Add **Knit Turtleneck Sweater** to cart (uses global deposit)
3. [ ] Go to checkout
4. [ ] Verify deposit shows: **$15,000.00** (global setting)

**Expected Result:** Global deposit applies when no custom deposit is set ✅

---

### ✅ Test 5: Test Deposit vs Full Payment
**In checkout page:**

1. [ ] For each pre-order item, you should see radio buttons:
   - [ ] **Deposit Option** - Pay $X now, $Y later
   - [ ] **Full Payment** - Pay full amount now
2. [ ] Select "Deposit" option
3. [ ] Verify the order total updates to show deposit amount
4. [ ] Select "Full Payment" option
5. [ ] Verify total shows full product price

**Expected Result:** Payment type selection works correctly ✅

---

### ✅ Test 6: Empty Field Saves as NULL
**URL:** http://localhost/admin/pre-orders/2/edit

1. [ ] Find **Leather Ankle Boots** (which now has custom deposit 8000)
2. [ ] **Clear** the deposit amount field (make it empty)
3. [ ] Save
4. [ ] Reload page
5. [ ] Field should be empty (means use global)
6. [ ] Go to frontend, add to cart, check deposit
7. [ ] Should show **$15,000** (global) not $0

**Expected Result:** Empty field correctly uses global setting ✅

---

## 🐛 If Something Doesn't Work

### Problem: Values don't appear in admin form
**Solution:** Clear Laravel cache
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### Problem: Frontend still shows wrong amounts
**Solution:** Clear browser cache and reload
- Chrome/Safari: Cmd+Shift+R (hard reload)
- Or use Incognito/Private window

### Problem: Changes don't save
**Check:**
1. Browser console for JavaScript errors (F12)
2. Laravel logs: `storage/logs/laravel.log`
3. Database permissions

---

## 📊 Verification Query

Run this to see current state anytime:
```bash
php artisan tinker --execute="DB::table('ec_pre_order_products')->join('ec_products', 'ec_pre_order_products.product_id', '=', 'ec_products.id')->select('ec_pre_order_products.*', 'ec_products.name')->where('pre_order_id', 2)->get()->each(function(\$p) { echo \$p->name . ' => Deposit: ' . (\$p->deposit_amount ?? 'uses global') . PHP_EOL; });"
```

---

## ✅ Success Criteria

All tests should pass with:
- ✅ Admin form displays saved values correctly
- ✅ Changes persist after save
- ✅ Frontend shows correct deposit amounts
- ✅ Product-specific deposits override global settings
- ✅ Empty fields use global settings (not $0)
- ✅ Payment type selection updates totals correctly

---

## 📝 Notes

- From your logs, I can see deposit of 15000 is already working on frontend
- The fix ensures admin form properly loads and saves these values
- NULL values are correct - they mean "use default/global setting"
- 0 values would be wrong - they would mean "free deposit" 