<?php
// views/guest/book.php
//
// Rendered by ReservationController::showForm(). Every price on this page is
// read from the catalogue/config — no rate is hardcoded in the markup.
//
// @var array<string, mixed>|null        $room
// @var array<int, array<string, mixed>> $rooms
// @var array<int, array<string, mixed>> $addons
// @var array<string, mixed>             $settings

$selectedId = $room['id'] ?? ($rooms[0]['id'] ?? null);
$symbol = $settings['currency_symbol'] ?? 'PHP';
$depositRate = (float) ($settings['deposit_rate'] ?? 0.5);
$holdMinutes = (int) ($settings['hold_minutes'] ?? 90);

$selected = null;
foreach ($rooms as $candidate) {
    if ((int) $candidate['id'] === (int) $selectedId) {
        $selected = $candidate;
        break;
    }
}

$selectedRate = (float) ($selected['rate'] ?? 0);
$selectedDeposit = round($selectedRate * $depositRate, 2);
$format = static fn (float $amount): string => number_format($amount, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verde Royale Resort - Quick Booking</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

  <div class="booking-card">
    <div class="header">
      <h2>Verde Royale Resort</h2>
      <p>Estimate Cost &amp; Reserve Cottage / Room</p>
    </div>

    <!-- Date range -->
    <div class="form-group">
      <label for="booking-date">Check-in Date</label>
      <input type="date" id="booking-date" required />
    </div>

    <div class="form-group">
      <label for="booking-checkout">Check-out Date</label>
      <input type="date" id="booking-checkout" required />
    </div>

    <!-- Tier selection, driven by data/accommodations.json -->
    <div class="form-group">
      <label for="accommodation">Accommodation</label>
      <select id="accommodation">
        <?php foreach ($rooms as $option): ?>
          <option
            value="<?php echo (float) $option['rate']; ?>"
            data-id="<?php echo (int) $option['id']; ?>"
            <?php echo ((int) $option['id'] === (int) $selectedId) ? 'selected' : ''; ?>
          ><?php echo htmlspecialchars($option['name']); ?> — <?php echo $symbol . $format((float) $option['rate']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Add-ons, driven by the 'addons' config -->
    <div class="form-group">
      <label>Add-ons</label>
      <div class="addons-group">
        <?php foreach ($addons as $addon): ?>
          <div class="addon-item">
            <label>
              <input
                type="checkbox"
                class="addon"
                value="<?php echo (float) $addon['rate']; ?>"
                data-code="<?php echo htmlspecialchars((string) $addon['code']); ?>"
              />
              <?php echo htmlspecialchars((string) $addon['label']); ?> (+<?php echo $symbol . $format((float) $addon['rate']); ?>)
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Guest details -->
    <div class="form-group">
      <label for="guest-name">Guest Name</label>
      <input type="text" id="guest-name" maxlength="120" placeholder="Juan Dela Cruz" />
    </div>

    <div class="form-group">
      <label for="guest-email">Email</label>
      <input type="email" id="guest-email" maxlength="190" placeholder="juan@example.com" />
    </div>

    <div class="form-group">
      <label for="guest-phone">Mobile Number</label>
      <input type="tel" id="guest-phone" maxlength="13" placeholder="09171234567" />
    </div>

    <!-- Live bill calculation -->
    <div class="summary-box">
      <div class="summary-row">
        <span>Base Rate:</span>
        <span id="display-base"><?php echo $symbol . $format($selectedRate); ?></span>
      </div>
      <div class="summary-row">
        <span>Add-ons:</span>
        <span id="display-addons"><?php echo $symbol ?>0.00</span>
      </div>
      <div class="summary-row summary-total">
        <span>Total Estimated:</span>
        <span id="display-total"><?php echo $symbol . $format($selectedRate); ?></span>
      </div>
      <div class="summary-row" style="font-size: 0.8rem; color: #15803d; margin-top: 4px;">
        <span>Required <?php echo (int) round($depositRate * 100); ?>% Deposit:</span>
        <span id="display-deposit" style="font-weight: 600;"><?php echo $symbol . $format($selectedDeposit); ?></span>
      </div>
    </div>

    <button class="btn" id="book-btn">Proceed to Payment Hold</button>
  </div>

  <!-- QR Checkout Modal -->
  <div class="modal-overlay" id="payment-modal">
    <div class="modal-content">
      <h3 style="font-size: 1.1rem; color: #166534;">Scan to Pay Deposit</h3>
      <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">GCash / Maya Verification</p>

      <div class="qr-box">VERDE GCASH QR</div>

      <p style="font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem;">
        Deposit Due: <span id="modal-deposit-amount"><?php echo $symbol . $format($selectedDeposit); ?></span>
      </p>
      <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 1rem;">
        Your reservation slot is temporarily held for <?php echo $holdMinutes; ?> minutes while awaiting payment slip upload.
      </p>

      <button class="btn" id="close-modal-btn">Done / Close</button>
    </div>
  </div>

  <script src="/assets/js/booking.js"></script>
</body>
</html>
