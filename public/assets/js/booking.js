// State Elements
const dateInput = document.getElementById('booking-date');
const accommodationSelect = document.getElementById('accommodation');
const addonCheckboxes = document.querySelectorAll('.addon');

// UI Elements
const displayBase = document.getElementById('display-base');
const displayAddons = document.getElementById('display-addons');
const displayTotal = document.getElementById('display-total');
const displayDeposit = document.getElementById('display-deposit');
const modalDepositAmount = document.getElementById('modal-deposit-amount');

// Modal Elements
const bookBtn = document.getElementById('book-btn');
const modal = document.getElementById('payment-modal');
const closeModalBtn = document.getElementById('close-modal-btn');

// Set default date to today
if (dateInput) {
  dateInput.valueAsDate = new Date();
  dateInput.min = new Date().toISOString().split('T')[0];
}

function calculateTotal() {
    if (!accommodationSelect) return;

    const baseCost = parseInt(accommodationSelect.value, 10) || 0;

    let addonCost = 0;
    addonCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            addonCost += parseInt(checkbox.value, 10);
        }
    });

    const totalCost = baseCost + addonCost;
    const depositCost = totalCost * 0.5;

    // Update UI
    if (displayBase) displayBase.textContent = `₱${baseCost.toLocaleString()}`;
    if (displayAddons) displayAddons.textContent = `₱${addonCost.toLocaleString()}`;
    if (displayTotal) displayTotal.textContent = `₱${totalCost.toLocaleString()}`;
    if (displayDeposit) displayDeposit.textContent = `₱${depositCost.toLocaleString()}`;
    if (modalDepositAmount) modalDepositAmount.textContent = `₱${depositCost.toLocaleString()}`;
}

// Event Listeners
if (accommodationSelect) {
    accommodationSelect.addEventListener('change', calculateTotal);
}
addonCheckboxes.forEach(box => box.addEventListener('change', calculateTotal));

if (bookBtn) {
    bookBtn.addEventListener('click', () => {
        if (dateInput && !dateInput.value) {
            alert('Please pick a date first.');
            return;
        }
        if (modal) { modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); }
    });
}

if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
        if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
    });
}

// Run on initial load
document.addEventListener('DOMContentLoaded', calculateTotal);
document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal?.style.display === 'flex') closeModalBtn?.click(); });
