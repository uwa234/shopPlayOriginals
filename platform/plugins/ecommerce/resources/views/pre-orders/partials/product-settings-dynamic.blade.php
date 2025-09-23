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
    
    // Function to update product settings visibility
    function updateProductSettings() {
        const selectedProducts = [];
        $('input[name="selected_products[]"]:checked').each(function() {
            selectedProducts.push(parseInt($(this).val()));
        });
        
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
        let html = '';
        
        selectedProductIds.forEach(function(productId) {
            const product = productData.find(function(p) { return p.id === productId; });
            if (product) {
                const defaultPrice = product.sale_price || product.price;
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
                html += '<div class="row">';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Custom Price</label>';
                html += '<input type="number" name="products[' + productId + '][price]" class="form-control" placeholder="Leave empty for default price" step="0.01" min="0">';
                html += '<small class="text-muted">Leave empty to use default price: $' + parseFloat(defaultPrice).toFixed(2) + '</small>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Max Quantity</label>';
                html += '<input type="number" name="products[' + productId + '][max_quantity]" class="form-control" placeholder="No limit" min="1">';
                html += '<small class="text-muted">Maximum quantity for this pre-order</small>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Deposit Amount</label>';
                html += '<input type="number" name="products[' + productId + '][deposit_amount]" class="form-control" placeholder="Override global deposit" step="0.01" min="0">';
                html += '<small class="text-muted">Override global deposit setting</small>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-3">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Deposit Percentage</label>';
                html += '<input type="number" name="products[' + productId + '][deposit_percentage]" class="form-control" placeholder="Override global %" step="0.01" min="0" max="100">';
                html += '<small class="text-muted">Override global percentage</small>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="row mt-2">';
                html += '<div class="col-md-6">';
                html += '<div class="form-group">';
                html += '<div class="form-check">';
                html += '<input type="checkbox" name="products[' + productId + '][is_active]" class="form-check-input" value="1" checked>';
                html += '<label class="form-check-label">Active</label>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="col-md-6">';
                html += '<div class="form-group">';
                html += '<label class="control-label">Pre-ordered Count</label>';
                html += '<input type="number" name="products[' + productId + '][pre_ordered]" class="form-control" value="0" readonly min="0">';
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
    
    // Add event listeners to checkboxes
    $('input[name="selected_products[]"]').on('change', updateProductSettings);
    
    // Initial check for any pre-selected products
    updateProductSettings();
});
</script>
