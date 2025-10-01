<div id="product-settings-container" style="display: none;">
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Configure individual settings for each selected product. Leave fields empty to use default values.
    </div>
    
    <div id="selected-products-settings">
        <!-- Product settings will be dynamically added here -->
    </div>
</div>

<script>
$(document).ready(function() {
    // Product data for dynamic generation
    const productData = @json($products ?? []);
    
    console.log('Product data loaded:', productData.length, 'products');
    
    // Function to update product settings visibility
    function updateProductSettings() {
        const selectedProducts = [];
        
        // Try multiple selectors to find the checkboxes
        let checkboxSelector = 'input[name="selected_products[]"]:checked';
        let checkboxes = $(checkboxSelector);
        
        // If not found, try without the brackets
        if (checkboxes.length === 0) {
            checkboxSelector = 'input[name="selected_products"]:checked';
            checkboxes = $(checkboxSelector);
        }
        
        // If still not found, try finding by ID pattern
        if (checkboxes.length === 0) {
            checkboxSelector = 'input[id^="selected-products-item-"]:checked';
            checkboxes = $(checkboxSelector);
        }
        
        console.log('Found checkboxes with selector:', checkboxSelector, 'count:', checkboxes.length);
        
        checkboxes.each(function() {
            selectedProducts.push(parseInt($(this).val()));
        });
        
        console.log('Selected products:', selectedProducts);
        
        if (selectedProducts.length > 0) {
            $('#product-settings-container').show();
            generateProductSettings(selectedProducts);
        } else {
            $('#product-settings-container').hide();
            $('#selected-products-settings').empty();
        }
    }
    
    // Function to generate product settings HTML
    function generateProductSettings(selectedProductIds) {
        console.log('Generating settings for:', selectedProductIds);
        let html = '';
        
        selectedProductIds.forEach(function(productId) {
            const product = productData.find(function(p) { return p.id === productId; });
            if (product) {
                const defaultPrice = product.sale_price || product.price;
                const pivot = product.pivot || {};
                
                // Get saved values or empty strings
                const savedPrice = pivot.price || '';
                const savedMaxQty = pivot.max_quantity || '';
                const savedDepositAmount = pivot.deposit_amount || '';
                const savedDepositPercentage = pivot.deposit_percentage || '';
                const isActive = pivot.is_active !== undefined ? pivot.is_active : true;
                const preOrdered = pivot.pre_ordered || 0;
                
                html += '<div class="card mt-3" id="product-settings-' + productId + '">';
                html += '<div class="card-header">';
                html += '<div class="row align-items-center">';
                html += '<div class="col-md-2">';
                if (product.image) {
                    html += '<img src="' + product.image + '" alt="' + product.name + '" class="img-thumbnail" style="max-width: 60px; max-height: 60px;">';
                } else {
                    html += '<div class="bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fas fa-image text-muted"></i></div>';
                }
                html += '</div>';
                html += '<div class="col-md-10">';
                html += '<strong>' + product.name + '</strong><br>';
                html += '<small class="text-muted">SKU: ' + product.sku + ' | Default Price: $' + parseFloat(defaultPrice).toFixed(2) + '</small>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="card-body">';
                html += '<input type="hidden" name="products[' + productId + '][product_id]" value="' + productId + '">';
                html += '<div class="row">';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Custom Price</label>';
                html += '<input type="number" name="products[' + productId + '][price]" class="form-control" placeholder="Leave empty for default price" step="0.01" min="0" value="' + savedPrice + '">';
                html += '<small class="text-muted">Leave empty to use default price: $' + parseFloat(defaultPrice).toFixed(2) + '</small>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Max Quantity</label>';
                html += '<input type="number" name="products[' + productId + '][max_quantity]" class="form-control" placeholder="No limit" min="1" value="' + savedMaxQty + '">';
                html += '<small class="text-muted">Maximum quantity for this pre-order</small>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Deposit Amount</label>';
                html += '<input type="number" name="products[' + productId + '][deposit_amount]" class="form-control" placeholder="Override global deposit" step="0.01" min="0" value="' + savedDepositAmount + '">';
                html += '<small class="text-muted">Override global deposit setting</small>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Deposit Percentage</label>';
                html += '<input type="number" name="products[' + productId + '][deposit_percentage]" class="form-control" placeholder="Override global %" step="0.01" min="0" max="100" value="' + savedDepositPercentage + '">';
                html += '<small class="text-muted">Override global percentage</small>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="row mt-2">';
                html += '<div class="col-md-6">';
                html += '<div class="form-group">';
                html += '<div class="form-check">';
                html += '<input type="checkbox" name="products[' + productId + '][is_active]" class="form-check-input" value="1" ' + (isActive ? 'checked' : '') + '>';
                html += '<label class="form-check-label">Active</label>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-6">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Pre-ordered Count</label>';
                html += '<input type="number" name="products[' + productId + '][pre_ordered]" class="form-control" value="' + preOrdered + '" readonly min="0">';
                html += '<small class="text-muted">This will be updated automatically</small>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
        });
        
        $('#selected-products-settings').html(html);
    }
    
    // Add event listeners to checkboxes - try multiple selectors
    let checkboxes = $('input[name="selected_products[]"]');
    if (checkboxes.length === 0) {
        checkboxes = $('input[name="selected_products"]');
    }
    if (checkboxes.length === 0) {
        checkboxes = $('input[id^="selected-products-item-"]');
    }
    
    console.log('Attaching event listeners to', checkboxes.length, 'checkboxes');
    checkboxes.on('change', updateProductSettings);
    
    // Also watch for any dynamic checkbox additions
    $(document).on('change', 'input[name="selected_products[]"], input[name="selected_products"], input[id^="selected-products-item-"]', updateProductSettings);
    
    // Initial check for any pre-selected products
    setTimeout(function() {
        console.log('Running initial update...');
        updateProductSettings();
    }, 500);
});
</script>
