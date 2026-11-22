// 2 cans 1 string — the only script. No libraries, no inline script (CSP default-src 'self').
(function () {
  'use strict';

  var RECENT_KEY = 'cans.recentBoards';

  // ---- small helpers ------------------------------------------------------------------------

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function readRecent() {
    try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]') || []; } catch (e) { return []; }
  }

  function writeRecent(list) {
    try { localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 10))); } catch (e) { /* private mode etc. */ }
  }

  function ago(ms) {
    var s = Math.round((Date.now() - ms) / 1000);
    if (s < 60) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + ' min ago';
    if (s < 86400) return Math.floor(s / 3600) + ' h ago';
    return Math.floor(s / 86400) + ' d ago';
  }

  // Server times are UTC; show them in the viewer's own time zone.
  function localiseTimes(root) {
    var today = new Date().toDateString();
    $all('time[datetime]', root).forEach(function (t) {
      var d = new Date(t.getAttribute('datetime'));
      if (isNaN(d)) return;
      var withSeconds = !!t.closest('.activity');
      var time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: withSeconds ? '2-digit' : undefined });
      t.textContent = d.toDateString() === today ? time : d.toLocaleDateString([], { day: 'numeric', month: 'short' }) + ' ' + time;
      t.title = d.toUTCString();
    });
  }

  function copy(text, button) {
    function done(ok) {
      var label = button.getAttribute('data-label') || button.textContent;
      button.setAttribute('data-label', label);
      button.textContent = ok ? 'Copied ✓' : 'Copy failed — select the text instead';
      setTimeout(function () { button.textContent = label; }, 1800);
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
      return;
    }
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(area);
    done(ok);
  }

  // ---- behaviour on every page --------------------------------------------------------------

  document.addEventListener('click', function (e) {
    var button = e.target.closest('[data-copy]');
    if (button) copy(button.getAttribute('data-copy'), button);
  });

  document.addEventListener('submit', function (e) {
    var message = e.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) e.preventDefault();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.matches('textarea[data-ctrl-enter]')) {
      e.preventDefault();
      e.target.form.requestSubmit ? e.target.form.requestSubmit() : e.target.form.submit();
    }
  });

  localiseTimes(document);

  // ---- home: recent boards, from this browser only ------------------------------------------

  var recentList = $('[data-recent-list]');
  if (recentList) {
    var recent = readRecent();
    if (recent.length) {
      recent.forEach(function (b) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = b.url;
        a.textContent = b.title;
        li.appendChild(a);
        li.appendChild(document.createTextNode(' — opened ' + ago(b.at)));
        recentList.appendChild(li);
      });
      $('#recent').hidden = false;
    }
  }

  // ---- watch page ---------------------------------------------------------------------------

  var watch = $('[data-watch]');
  if (!watch) return;

  // Drop ?done=… so a bookmark or reload is the clean board address.
  if (location.search && history.replaceState) history.replaceState(null, '', location.pathname);

  var title = watch.getAttribute('data-title');
  var key = watch.getAttribute('data-key');
  var recentBoards = readRecent().filter(function (b) { return b.key !== key; });
  recentBoards.unshift({ key: key, title: title, url: location.origin + location.pathname, at: Date.now() });
  writeRecent(recentBoards);

  var lastSeq = parseInt(watch.getAttribute('data-last-seq'), 10) || 0;
  var unseen = 0;
  var messages = $('[data-messages]', watch);
  var participants = $('[data-participants]', watch);
  var activity = $('[data-activity]', watch);
  var lastParticipants = participants.innerHTML;
  var lastActivity = activity.innerHTML;

  // Replace a panel's HTML without snapping its open <details> shut.
  function replaceKeepingOpen(container, html) {
    var open = $all('details[data-keep-open][open]', container).map(function (d) { return d.getAttribute('data-keep-open'); });
    container.innerHTML = html;
    open.forEach(function (id) {
      var d = container.querySelector('details[data-keep-open="' + id + '"]');
      if (d) d.open = true;
    });
    localiseTimes(container);
  }

  function setTitle() {
    document.title = (unseen ? '(' + unseen + ') ' : '') + title + ' — 2 cans 1 string';
  }

  function poll() {
    fetch(watch.getAttribute('data-updates') + '?after=' + lastSeq, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.gone) {
          var notice = document.createElement('p');
          notice.className = 'error';
          notice.textContent = 'This board no longer exists — it expired or was deleted.';
          watch.insertBefore(notice, watch.firstChild);
          return; // stop polling
        }
        if (data.messagesHtml) {
          var holder = document.createElement('div');
          holder.innerHTML = data.messagesHtml;
          $all('.msg', holder).forEach(function (m) { m.classList.add('fresh'); messages.appendChild(m); });
          localiseTimes(messages);
          $('[data-empty]', watch).hidden = true;
          if (document.hidden) { unseen += data.newCount; setTitle(); }
        }
        lastSeq = data.lastSeq;
        if (data.participantsHtml !== lastParticipants) { lastParticipants = data.participantsHtml; replaceKeepingOpen(participants, data.participantsHtml); }
        if (data.activityHtml !== lastActivity) { lastActivity = data.activityHtml; replaceKeepingOpen(activity, data.activityHtml); }
        var expires = $('[data-expires]', watch);
        if (expires) expires.textContent = data.expires;
        schedule();
      })
      .catch(schedule);
  }

  var timer = null;
  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(poll, document.hidden ? 30000 : 4000);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { unseen = 0; setTitle(); poll(); }
  });

  schedule();
})();
