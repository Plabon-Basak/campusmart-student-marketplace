<?php
/**
 * Sell an Item landing page - explains the selling workflow and guides
 * logged-out students to register first.
 */
require_once __DIR__ . '/includes/init.php';

$user = current_user();
$pageTitle = 'Sell an Item';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top:30px;">
  <div class="section-head">
    <div>
      <h1>Sell an Item</h1>
      <p>Turn what you no longer need into cash — right here on campus.</p>
    </div>
  </div>

  <div class="card" style="padding:34px; margin-bottom:30px; background:linear-gradient(135deg,#312e81,#4f46e5); color:#fff; border:0;">
    <h2 style="font-size:1.6rem; margin-bottom:10px;">Your listing could be someone&rsquo;s textbook, laptop or bicycle.</h2>
    <p style="color:#c7d2fe; max-width:640px;">
      CampusMart lets you list used and new student items in minutes. Every listing is reviewed by a
      moderator before going live, so the marketplace stays safe for everyone.
    </p>
    <div style="margin-top:20px;">
      <a class="btn btn-light btn-lg" href="<?= e(APP_BASE_URL . ($user ? '/user/add-listing.php' : '/register.php')) ?>">Start Selling Now</a>
      <?php if (!$user): ?>
        <a class="btn btn-lg" style="background:transparent; border:1px solid #c7d2fe; color:#e0e7ff;" href="<?= e(APP_BASE_URL . '/login.php') ?>">I already have an account</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="steps" style="margin-bottom:34px;">
    <div class="card step">
      <div class="step-num">1</div>
      <h3>Create your listing</h3>
      <p>Add a title, description, price, condition and up to 5 real photos of the item. Be honest about its condition.</p>
    </div>
    <div class="card step">
      <div class="step-num">2</div>
      <h3>Moderator review</h3>
      <p>Your listing goes into a short review queue. Once approved it becomes active and visible to every student.</p>
    </div>
    <div class="card step">
      <div class="step-num">3</div>
      <h3>Connect &amp; sell</h3>
      <p>Buyers message you or send a purchase request. Agree on a public campus spot, hand over the item and get paid.</p>
    </div>
  </div>

  <div class="safety-list" style="margin-bottom:30px;">
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>What can I sell?</h3><p>Textbooks, electronics, calculators, lab equipment, bicycles, furniture, sports gear, clothing and other student-related items in good working condition.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>What is not allowed?</h3><p>Prohibited items, counterfeit or fake products, and anything illegal or unsafe. Moderators review every listing before it goes live.</p></div>
    </div>
    <div class="card safety-item">
      <span class="num">✓</span>
      <div><h3>Tips for great photos</h3><p>Use bright, natural light, shoot from a few angles, and show any defects clearly. Listings with clear photos sell faster.</p></div>
    </div>
  </div>

  <?php if (!$user): ?>
    <div class="card" style="padding:30px; text-align:center;">
      <h2>Ready when you are</h2>
      <p class="text-muted" style="margin:8px 0 20px;">Create a free student account to start selling today.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(APP_BASE_URL . '/register.php') ?>">Create an Account</a>
    </div>
  <?php else: ?>
    <div class="card" style="padding:30px; text-align:center;">
      <h2>Ready to list your first item?</h2>
      <p class="text-muted" style="margin:8px 0 20px;">It only takes a couple of minutes.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(APP_BASE_URL . '/user/add-listing.php') ?>">Create a Listing</a>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
