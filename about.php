<?php
/**
 * About CampusMart page.
 */
require_once __DIR__ . '/includes/init.php';

$totalUsers = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalListings = (int)db()->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$completedOrders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();

$pageTitle = 'About';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top:34px; padding-bottom:20px;">
  <div class="section-head">
    <div>
      <h1>About CampusMart</h1>
      <p>Buy. Sell. Connect. Within Your Campus.</p>
    </div>
  </div>

  <div class="card" style="padding:32px; margin-bottom:28px;">
    <h2>Our mission</h2>
    <p style="color:#334155; margin-top:10px;">
      CampusMart is a student-to-student marketplace built for one community: your campus. We help students
      save money on the things they need — textbooks, calculators, laptops, bikes and furniture — while
      giving sellers an easy way to earn back a little of what they spent.
    </p>
    <p style="color:#334155; margin-top:10px;">
      Everything happens in person, on campus, between students you can actually meet. No couriers, no online
      payments, no strangers from outside the university. Just a fair, safe and simple way to trade.
    </p>
  </div>

  <div class="stat-grid" style="margin-bottom:28px;">
    <div class="card stat-card">
      <div class="stat-icon" style="background:var(--color-primary-light);">🎓</div>
      <div class="stat-num"><?= number_format($totalUsers) ?>+</div>
      <div class="stat-name">Student Sellers</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon" style="background:var(--color-success-light);">📦</div>
      <div class="stat-num"><?= number_format($totalListings) ?></div>
      <div class="stat-name">Active Listings</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon" style="background:var(--color-warning-light);">🤝</div>
      <div class="stat-num"><?= number_format($completedOrders) ?></div>
      <div class="stat-name">Completed Deals</div>
    </div>
  </div>

  <div class="feature-grid" style="margin-bottom:28px;">
    <div class="card feature">
      <div class="feature-icon" aria-hidden="true">🛡️</div>
      <h3>Moderated listings</h3>
      <p>Every listing is reviewed by a moderator before it goes live, keeping scams and fake items out.</p>
    </div>
    <div class="card feature">
      <div class="feature-icon" aria-hidden="true">💬</div>
      <h3>Private messaging</h3>
      <p>Talk to sellers through in-app messages without revealing your phone number right away.</p>
    </div>
    <div class="card feature">
      <div class="feature-icon" aria-hidden="true">⭐</div>
      <h3>Trusted reviews</h3>
      <p>Only verified participants of completed deals can review each other, so ratings stay honest.</p>
    </div>
  </div>

  <div class="card" style="padding:32px;">
    <h2>How we keep it safe</h2>
    <ul class="safety-list" style="margin-top:12px;">
      <li class="card safety-item"><span class="num">✓</span><div><h3>University-only accounts</h3><p>Registration is restricted to your university email domain so only students can join.</p></div></li>
      <li class="card safety-item"><span class="num">✓</span><div><h3>No online payment</h3><p>We never touch your card details. Transactions are settled in person, in cash, on campus.</p></div></li>
      <li class="card safety-item"><span class="num">✓</span><div><h3>Reporting system</h3><p>Spot something wrong? Flag any listing or user and our moderators will review it.</p></div></li>
      <li class="card safety-item"><span class="num">✓</span><div><h3>In-person handover</h3><p>Meet in public campus spots, inspect the item first, and pay only when you are satisfied.</p></div></li>
    </ul>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
