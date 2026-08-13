<?php
/**
 * Contact page - saves messages to the contact_messages table.
 */
require_once __DIR__ . '/includes/init.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $v) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }

    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Please enter your name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (mb_strlen($old['subject']) < 3) {
        $errors['subject'] = 'Please enter a subject.';
    }
    if (mb_strlen($old['message']) < 10) {
        $errors['message'] = 'Please write a message of at least 10 characters.';
    }

    if (empty($errors)) {
        $st = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $st->execute([$old['name'], $old['email'], $old['subject'], $old['message']]);
        set_flash('success', 'Thank you! Your message has been sent. We will get back to you soon.');
        redirect('contact.php');
    }
}

$supportEmail = settings('support_email') ?: '';
$pageTitle = 'Contact';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top:34px; padding-bottom:20px;">
  <div class="detail-layout" style="grid-template-columns: minmax(0,1fr) 380px; margin-top:0;">
    <div class="card" style="padding:30px;">
      <h1 style="font-size:1.6rem; margin-bottom:6px;">Get in touch</h1>
      <p class="text-muted" style="margin-bottom:20px;">Questions, suggestions or need a hand? Drop us a message.</p>

      <?php if ($errors): ?>
        <div class="alert alert-error alert-inline">Please fix the highlighted fields below.</div>
      <?php endif; ?>

      <form method="post" action="contact.php" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid mb-2">
          <div class="form-group <?= isset($errors['name']) ? 'invalid' : '' ?>">
            <label class="form-label" for="name">Your name <span class="req">*</span></label>
            <input class="form-control" type="text" id="name" name="name" value="<?= e($old['name']) ?>" required>
            <?php if (isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>
          <div class="form-group <?= isset($errors['email']) ? 'invalid' : '' ?>">
            <label class="form-label" for="email">Email <span class="req">*</span></label>
            <input class="form-control" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
            <?php if (isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="form-group mb-2 <?= isset($errors['subject']) ? 'invalid' : '' ?>">
          <label class="form-label" for="subject">Subject <span class="req">*</span></label>
          <input class="form-control" type="text" id="subject" name="subject" value="<?= e($old['subject']) ?>" placeholder="e.g. Suggestion: add a category" required>
          <?php if (isset($errors['subject'])): ?><span class="form-error"><?= e($errors['subject']) ?></span><?php endif; ?>
        </div>
        <div class="form-group mb-3 <?= isset($errors['message']) ? 'invalid' : '' ?>">
          <label class="form-label" for="message">Message <span class="req">*</span></label>
          <textarea class="form-control" id="message" name="message" minlength="10" required><?= e($old['message']) ?></textarea>
          <?php if (isset($errors['message'])): ?><span class="form-error"><?= e($errors['message']) ?></span><?php endif; ?>
        </div>
        <button class="btn btn-primary btn-lg" type="submit">Send Message</button>
      </form>
    </div>

    <aside>
      <div class="card" style="padding:24px;">
        <h3 style="margin-bottom:14px;">Contact details</h3>
        <?php if ($supportEmail): ?>
          <p style="margin-bottom:10px;">📧 <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a></p>
        <?php endif; ?>
        <p style="margin-bottom:10px;">📍 Campus central, Marketplace Office</p>
        <p style="margin-bottom:10px;">🕐 Mon–Sat, 9:00 AM – 5:00 PM</p>
      </div>
      <div class="card" style="padding:24px; margin-top:14px;">
        <h3 style="margin-bottom:10px;">Rather report a problem?</h3>
        <p class="text-muted text-small">Found a suspicious listing or user? Use the report button on the item or seller so moderators can review it quickly.</p>
      </div>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
