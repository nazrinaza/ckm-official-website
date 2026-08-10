/* CKM Admin — JavaScript */
document.addEventListener('DOMContentLoaded', function() {

  // Auto-dismiss alerts
  document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; }, 5000);
  });

  // Quote/Invoice — add item row
  const itemsBody = document.getElementById('items-body');
  if (itemsBody) {
    const addBtn = document.getElementById('add-item');
    if (addBtn) {
      addBtn.addEventListener('click', function() {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td class="col-desc"><input type="text" name="item_desc[]" placeholder="Penerangan servis" /></td>
          <td class="col-qty"><input type="number" name="item_qty[]" value="1" min="1" onchange="calcTotals()" /></td>
          <td class="col-price"><input type="number" name="item_price[]" value="0" step="0.01" min="0" onchange="calcTotals()" /></td>
          <td class="text-center"><span class="line-total">0.00</span></td>
          <td class="col-action"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
        `;
        itemsBody.appendChild(row);
      });
    }
    calcTotals();
  }

  // Confirm delete
  document.querySelectorAll('.confirm-delete').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (!confirm('Adakah anda pasti? Tindakan ini tidak boleh diundur.')) {
        e.preventDefault();
      }
    });
  });
});

// Calculate totals for quote/invoice
function calcTotals() {
  let subtotal = 0;
  const rows = document.querySelectorAll('#items-body tr');
  rows.forEach(function(row) {
    const qty = parseFloat(row.querySelector('[name="item_qty[]"]')?.value) || 0;
    const price = parseFloat(row.querySelector('[name="item_price[]"]')?.value) || 0;
    const lineTotal = qty * price;
    row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
    subtotal += lineTotal;
  });

  const taxRate = parseFloat(document.getElementById('tax_rate')?.value) || 0;
  const discount = parseFloat(document.getElementById('discount')?.value) || 0;
  const taxAmount = (subtotal - discount) * (taxRate / 100);
  const total = subtotal - discount + taxAmount;

  const elSub = document.getElementById('subtotal');
  const elTax = document.getElementById('tax_amount');
  const elTotal = document.getElementById('total');
  if (elSub) elSub.value = subtotal.toFixed(2);
  if (elTax) elTax.value = taxAmount.toFixed(2);
  if (elTotal) elTotal.value = total.toFixed(2);

  const dispSub = document.getElementById('disp-subtotal');
  const dispTax = document.getElementById('disp-tax');
  const dispTotal = document.getElementById('disp-total');
  if (dispSub) dispSub.textContent = 'RM ' + subtotal.toFixed(2);
  if (dispTax) dispTax.textContent = 'RM ' + taxAmount.toFixed(2);
  if (dispTotal) dispTotal.textContent = 'RM ' + total.toFixed(2);
}

// Remove item row
function removeRow(btn) {
  btn.closest('tr').remove();
  calcTotals();
}
