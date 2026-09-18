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
      <p>Estimate Cost & Reserve Cottage / Room</p>
    </div>

    <!-- Date Picker -->
    <div class="form-group">
      <label for="booking-date">Reservation Date</label>
      <input type="date" id="booking-date" required />
    </div>

    <!-- Tier Selection -->
    <div class="form-group">
      <label for="accommodation">Accommodation</label>
      <select id="accommodation">
        <option value="1500">Open Cottage (10 pax) — ₱1,500</option>
        <option value="2500">Kubo Gazebo (15-20 pax) — ₱2,500</option>
        <option value="3500">Standard AC Room (2-4 pax) — ₱3,500</option>
        <option value="5500">Family AC Room (6-10 pax) — ₱5,500</option>
      </select>
    </div>

    <!-- Add-ons -->
    <div class="form-group">
      <label>Add-ons</label>
      <div class="addons-group">
        <div class="addon-item">
          <label><input type="checkbox" class="addon" value="800" /> Videoke Machine (+₱800)</label>
        </div>
        <div class="addon-item">
          <label><input type="checkbox" class="addon" value="300" /> Grilling Station (+₱300)</label>
        </div>
      </div>
    </div>

    <!-- Live Bill Calculation -->
    <div class="summary-box">
      <div class="summary-row">
        <span>Base Rate:</span>
        <span id="display-base">₱1,500</span>
      </div>
      <div class="summary-row">
        <span>Add-ons:</span>
        <span id="display-addons">₱0</span>
      </div>
      <div class="summary-row summary-total">
        <span>Total Estimated:</span>
        <span id="display-total">₱1,500</span>
      </div>
      <div class="summary-row" style="font-size: 0.8rem; color: #15803d; margin-top: 4px;">
        <span>Required 50% Deposit:</span>
        <span id="display-deposit" style="font-weight: 600;">₱750</span>
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
        Deposit Due: <span id="modal-deposit-amount">₱750</span>
      </p>
      <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 1rem;">
        Your reservation slot is temporarily held for 15 minutes while awaiting payment slip upload.
      </p>

      <button class="btn" id="close-modal-btn">Done / Close</button>
    </div>
  </div>

  <script src="/assets/js/booking.js"></script>
</body>
</html>
