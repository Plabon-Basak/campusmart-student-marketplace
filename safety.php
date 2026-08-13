<?php
/**
 * Campus Safety page with practical guidelines for safe trading.
 */
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Campus Safety';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top:34px; padding-bottom:20px;">
  <div class="section-head">
    <div>
      <h1>Campus Safety</h1>
      <p>Smart habits for every buy and sell on CampusMart</p>
    </div>
  </div>

  <div class="safety-list">
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Meet in public campus locations</h3><p>Arrange pickups in busy, well-lit public areas — the central cafeteria, the library lobby, a hall front desk or the sports complex. Never invite strangers to your room.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Verify the item before paying</h3><p>Inspect the product carefully before handing over any money. Turn it on, test it, check for damage — and make sure it matches the listing description and photos.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Do not share passwords or OTPs</h3><p>Never share your CampusMart password, email password, bank OTP or PIN with anyone — no matter what they say. Official staff will never ask for them.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Be careful with suspicious offers</h3><p>If a price is unrealistically low, the story seems off, or the seller pushes you off the platform, stop and report it. Too good to be true usually is.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Avoid sending sensitive information</h3><p>Keep your home address, national ID number and other sensitive details private. You only need to agree on where and when to meet on campus.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Report scams and inappropriate listings</h3><p>Use the report button on any listing or seller profile. Moderators review every report and take action — flagging a problem protects the whole campus.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Bring a friend when you can</h3><p>For higher-value items, ask a friend to come along to the meetup. Two people are always better than one.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Keep your order on CampusMart</h3><p>Complete the purchase request flow and pay in person. Avoid moving the whole deal to external chats where there is no review or report trail.</p></div>
    </div>
  </div>

  <div class="card" style="padding:30px; text-align:center; margin-top:26px;">
    <h2>Seen something suspicious?</h2>
    <p class="text-muted" style="margin:8px 0 20px;">Report it and our moderators will take it from there.</p>
    <a class="btn btn-primary btn-lg" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Marketplace</a>
    <?php if (is_logged_in()): ?>
      <a class="btn btn-ghost btn-lg" href="<?= e(APP_BASE_URL . '/user/dashboard.php') ?>">Go to Dashboard</a>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
