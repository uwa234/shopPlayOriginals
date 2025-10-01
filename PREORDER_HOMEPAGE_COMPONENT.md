# Pre-Order Homepage Component

## Overview
A new homepage component has been created to showcase pre-order products with countdown timers, similar to the flash sale component. This allows you to highlight upcoming products and build anticipation with expected delivery dates.

## Features
- ✅ Two different visual styles to choose from
- ✅ Countdown timer showing days until expected delivery
- ✅ Product slider with pre-order items
- ✅ Customizable title, subtitle, and background
- ✅ Optional "View More" button
- ✅ Responsive design

## How to Add to Homepage

### Step 1: Access Page Builder
1. Go to **Appearance > Theme Options** or directly to your homepage editor
2. Click on **Edit** for the homepage you want to modify
3. Click the **"+"** button to add a new section

### Step 2: Add Pre-Order Component
1. Search for **"Ecommerce Pre-Order"** in the component list
2. Click to add it to your page
3. Configure the settings (see below)

### Step 3: Configure Component Settings

#### **Style Selection**
Choose between 2 styles:
- **Style 1**: Product slider with side-by-side layout
- **Style 2**: Centered title with countdown prominently displayed

#### **Basic Settings**
- **Title**: Main heading (e.g., "Coming Soon" or "Pre-Order Now")
- **Subtitle**: Secondary text (only for Style 2)
- **Pre-Order Campaign**: Select which pre-order campaign to display products from

#### **Product Settings**
- **Limit**: Number of products to show (default: 3, recommended: 3-8)

#### **Button Settings** (Style 1 only)
- **Button Label**: Text for the "View More" button (e.g., "Shop Pre-Orders")
- **Button URL**: Link destination (leave empty to link to shop page)

#### **Visual Customization**
- **Background Color**: Choose a background color (default: #F3F3F3)
- **Background Image**: Optional background image

## Component Styles

### Style 1: Product Slider
```
┌────────────────────────────────────────────────┐
│  Coming Soon            [View More Button →]   │
│  ┌─────┐  ┌─────┐  ┌─────┐                    │
│  │ Pro │  │ Pro │  │ Pro │                    │
│  │ duct│  │ duct│  │ duct│                    │
│  │  1  │  │  2  │  │  3  │                    │
│  └─────┘  └─────┘  └─────┘                    │
│  [Countdown: 45D 12H 30M 15S]                  │
└────────────────────────────────────────────────┘
```

Best for: Showcasing multiple products with emphasis on shopping

### Style 2: Centered Countdown
```
┌────────────────────────────────────────────────┐
│                                                │
│            Pre-Order Campaign                  │
│        ════════════════════                   │
│                                                │
│         New Products Coming!                   │
│                                                │
│      [45 Days  12 Hrs  30 Mins  15 Secs]      │
│          Expected Delivery Date                │
│                                                │
└────────────────────────────────────────────────┘
```

Best for: Building anticipation and highlighting the delivery countdown

## Requirements

### Pre-Order Campaign Must Be:
1. **Published** (status = published)
2. **Active** (expected delivery date in the future)
3. **Have Products** - At least one product must be:
   - Published
   - Have "Enable Pre-Order" checked
   - Be linked to the pre-order campaign

## Example Configurations

### Example 1: New Product Launch
```
Title: "Coming This Fall"
Subtitle: "Pre-Order Our Latest Collection"
Style: Style 2
Pre-Order Campaign: "Fall 2025 Collection"
Background Color: #FFF5F0
```

### Example 2: Limited Edition Items
```
Title: "Limited Edition Pre-Order"
Style: Style 1
Limit: 4 products
Button Label: "See All Pre-Orders"
Button URL: /products?preorder=true
Pre-Order Campaign: "Limited Edition 2025"
Background Color: #F8F8F8
```

### Example 3: Seasonal Drop
```
Title: "Holiday Collection"
Subtitle: "Order Now, Ships December"
Style: Style 2
Pre-Order Campaign: "Holiday 2025"
Background Image: (Upload festive background)
```

## Countdown Timer

The countdown timer shows the time remaining until the expected delivery date:
- **Days**: Days until delivery
- **Hrs**: Hours remaining
- **Mins**: Minutes remaining
- **Secs**: Seconds remaining

The timer updates in real-time and creates urgency for customers.

## Tips for Best Results

1. **Choose the Right Style**:
   - Use Style 1 when you have 3+ products to showcase
   - Use Style 2 for single campaign announcement or when delivery date is the focus

2. **Optimize Product Limit**:
   - Desktop: 3-5 products work best
   - Mobile: 3 products recommended

3. **Compelling Titles**:
   - "Pre-Order Now - Save 20%"
   - "Coming Soon - Be the First"
   - "Limited Quantities - Reserve Yours"

4. **Background Images**:
   - Use high-quality images (1920x600px recommended)
   - Ensure text is readable over the image
   - Use subtle patterns or gradients

5. **Update Regularly**:
   - Remove component once delivery date passes
   - Create new campaigns for new product drops
   - Keep content fresh and relevant

## Troubleshooting

### Component Not Showing
- Check that pre-order campaign is published
- Verify expected delivery date is in the future
- Ensure products are linked to the campaign
- Check that products have "Enable Pre-Order" checked

### Products Not Appearing
- Products must be published
- Products must have "is_preorder_enabled" = true
- Products must be linked to the selected campaign

### Countdown Not Working
- Clear browser cache
- Check that expected_delivery_date is set correctly
- Ensure JavaScript is enabled

## Technical Details

### Files Created
1. `platform/themes/shofy/functions/shortcodes-ecommerce.php` - Shortcode registration
2. `platform/themes/shofy/partials/shortcodes/ecommerce-pre-order/index.blade.php` - Router file
3. `platform/themes/shofy/partials/shortcodes/ecommerce-pre-order/style-1.blade.php` - Style 1 template
4. `platform/themes/shofy/partials/shortcodes/ecommerce-pre-order/style-2.blade.php` - Style 2 template

### Database Requirements
- Uses existing `ec_pre_orders` table
- Uses existing `ec_pre_order_products` pivot table
- No new migrations required

---

**Created**: October 1, 2025
**Feature Type**: Homepage Component / Shortcode
**Similar To**: Flash Sale Component 