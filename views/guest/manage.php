<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage your reservation | Verde Royale Resort</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo \App\Helpers\Assets::url('/assets/css/style.css'); ?>">
</head>
<body>
  <div class="site-shell">
    <?php require __DIR__ . '/../partials/site-header.php'; ?>
    <main class="container manage-main" id="main-content">
      <p class="eyebrow">Reservation self-service &middot; School demo</p><h1>Manage your stay</h1>
      <p>Use the reservation reference and the same email address used when booking.</p>
      <p class="manage-message" id="manage-message" role="status" aria-live="polite"></p>
      <div class="manage-grid">
        <section class="staff-panel"><h2>Submit payment proof</h2>
          <form id="proof-upload-form" class="staff-form" enctype="multipart/form-data">
            <label class="form-label" for="proof-reference">Reservation reference</label><input class="form-input" id="proof-reference" name="reservation_reference" placeholder="RSV-0001" pattern="RSV-[0-9]{4,}" required>
            <label class="form-label" for="proof-email">Booking email</label><input class="form-input" id="proof-email" name="guest_email" type="email" required>
            <label class="form-label" for="proof-amount">Amount shown on receipt</label><input class="form-input" id="proof-amount" name="amount" type="number" min="0.01" step="0.01" required>
            <label class="form-label" for="proof-file">Receipt image (JPEG, PNG or WebP, max 5 MB)</label><input class="form-input" id="proof-file" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required>
            <p class="staff-hint">Please upload a clear, legible image of your deposit receipt or transfer confirmation.</p>
            <button class="btn btn--primary" type="submit">Send for staff review</button>
          </form>
        </section>
        <section class="staff-panel"><h2>Request a change or cancellation</h2>
          <form id="manage-reservation-form" class="staff-form">
            <label class="form-label" for="manage-reference">Reservation reference</label><input class="form-input" id="manage-reference" name="reservation_reference" placeholder="RSV-0001" pattern="RSV-[0-9]{4,}" required>
            <label class="form-label" for="manage-email">Booking email</label><input class="form-input" id="manage-email" name="guest_email" type="email" required>
            <div class="staff-form__dates"><div><label class="form-label" for="manage-in">Requested check-in</label><input class="form-input" id="manage-in" name="check_in" type="date"></div><div><label class="form-label" for="manage-out">Requested check-out</label><input class="form-input" id="manage-out" name="check_out" type="date"></div></div>
            <label class="form-label" for="manage-reason">Reason for request</label><textarea class="form-input" id="manage-reason" name="reason" maxlength="500" rows="3" required></textarea>
            <button class="btn btn--primary" type="submit">Send reschedule request</button>
            <button class="btn btn--ghost" id="guest-cancel" type="button">Request cancellation</button>
            <button class="btn btn--ghost" id="check-change-status" type="button">Check request status</button>
            <div id="change-request-status" class="staff-hint" aria-live="polite"></div>
            <p class="staff-hint">A staff member reviews requests. Rescheduling requires at least 7 days before arrival and an available date range. Cancellation terms are checked during review; refunds are not processed by this demo.</p>
          </form>
        </section>
      </div>
    </main>
    <?php require __DIR__ . '/../partials/site-footer.php'; ?>
  </div>
  <script src="<?php echo \App\Helpers\Assets::url('/assets/js/site.js'); ?>"></script>
  <script src="<?php echo \App\Helpers\Assets::url('/assets/js/manage.js'); ?>"></script>
</body>
</html>
