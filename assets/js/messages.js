/* CampusMart - messaging thread (polling, no websockets) */
(function () {
  'use strict';

  const thread = document.querySelector('[data-thread]');
  if (!thread) return;

  const conversationId = thread.getAttribute('data-thread');
  const form = document.getElementById('composeForm');
  const textarea = form ? form.querySelector('textarea') : null;
  const list = document.getElementById('threadMessages');
  let lastId = parseInt(list.getAttribute('data-last-id') || '0', 10);

  function scrollDown(force) {
    if (force || isNearBottom()) {
      list.scrollTop = list.scrollHeight;
    }
  }
  function isNearBottom() {
    return list.scrollHeight - list.scrollTop - list.clientHeight < 120;
  }

  function poll() {
    fetch(window.CM_BASE + '/ajax/messages.php?conversation=' + conversationId + '&since=' + lastId, { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.messages || !data.messages.length) return;
        data.messages.forEach(function (m) {
          if (m.id <= lastId) return;
          const div = document.createElement('div');
          div.className = 'msg-bubble ' + (m.mine ? 'msg-mine' : 'msg-theirs');
          div.innerHTML = m.body.replace(/\n/g, '<br>') + '<span class="msg-time">' + m.time + '</span>';
          list.appendChild(div);
          if (m.id > lastId) lastId = m.id;
        });
        scrollDown(false);
      })
      .catch(function () {});
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const body = textarea.value.trim();
      if (!body) return;
      fetch(window.CM_BASE + '/ajax/messages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'conversation=' + encodeURIComponent(conversationId) +
              '&body=' + encodeURIComponent(body) +
              '&csrf_token=' + encodeURIComponent(window.CM_CSRF || ''),
        credentials: 'same-origin'
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.ok) {
            textarea.value = '';
            lastId = 0;
            list.innerHTML = '';
            lastId = parseInt(list.getAttribute('data-last-id') || '0', 10);
            poll();
          } else if (data.redirect) {
            window.location = data.redirect;
          } else {
            window.cmToast(data.message || 'Could not send message.', 'error');
          }
        })
        .catch(function () { window.cmToast('Network error. Please try again.', 'error'); });
    });

    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        form.requestSubmit();
      }
    });
  }

  scrollDown(true);
  setInterval(poll, 4000);
})();
