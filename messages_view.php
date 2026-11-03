<?php
// Shared Messages page. Set $role = 'student' | 'landlord' before including.
require_once __DIR__ . '/auth.php';
require_role($role);
$isStudent = $role === 'student';
$pageTitle = 'Messages'; $active = 'messages';
$extraHead = '<link rel="stylesheet" href="assets/css/messages.css">';
require __DIR__ . '/' . ($isStudent ? 'student_header.php' : 'landlord_header.php');
$open = (int)($_GET['c'] ?? 0);
$emptyText = $isStudent
    ? 'No conversations yet. Open a property and choose "Send a message" to contact the landlord.'
    : 'No conversations yet. Messages from students about your properties will appear here.';
?>
<h1>Messages</h1>
<div class="msg-app" id="msgApp" data-api="messages-api.php" data-csrf="<?= e(csrf_token()) ?>" data-open="<?= $open ?>">
  <aside class="msg-list">
    <div class="msg-search"><input type="search" id="msgSearch" placeholder="Search conversations" aria-label="Search conversations" maxlength="100"></div>
    <div id="convList" class="conv-list" aria-live="polite"></div>
    <div id="convEmpty" class="msg-empty" data-default="<?= e($emptyText) ?>" hidden>
      <p class="msg-empty-text"></p>
      <?php if ($isStudent): ?><p style="margin-top:12px"><a class="btn btn-small" href="student-properties.php">Find a place</a></p><?php endif; ?>
    </div>
  </aside>
  <section class="msg-thread" id="threadPane">
    <div class="thread-empty" id="threadPlaceholder">Select a conversation to read and reply.</div>
    <div class="thread-box" id="threadBox" hidden>
      <header>
        <button class="back" id="backBtn" type="button" aria-label="Back to conversations" data-nobusy>&larr;</button>
        <span class="avatar" id="thAvatar"></span>
        <div><strong id="thName"></strong><small id="thProp"></small></div>
        <a id="thLink" class="btn btn-small btn-ghost" href="#">View property</a>
      </header>
      <div class="thread-msgs" id="threadMsgs" aria-live="polite"></div>
      <form class="send-form" id="sendForm" autocomplete="off">
        <textarea id="msgInput" rows="1" maxlength="1000" placeholder="Type a message" aria-label="Message"></textarea>
        <button class="btn" type="submit" data-nobusy>Send</button>
        <div class="send-err" id="sendErr" role="alert"></div>
      </form>
    </div>
  </section>
</div>
<script src="assets/js/messages.js"></script>
<?php require __DIR__ . '/' . ($isStudent ? 'student_footer.php' : 'landlord_footer.php'); ?>
