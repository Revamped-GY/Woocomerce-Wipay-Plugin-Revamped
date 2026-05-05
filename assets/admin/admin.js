(function () {
  var strings = window.rgyWipayAdmin || { copied: 'Copied', copy: 'Copy' };

  function copyText(text) {
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).catch(function () {});
      return;
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.rgy-copy-btn');
    if (!btn) return;
    e.preventDefault();
    var text = btn.getAttribute('data-copy') || '';
    copyText(text);
    btn.classList.add('rgy-copied');
    btn.textContent = strings.copied;
    setTimeout(function () {
      btn.classList.remove('rgy-copied');
      btn.textContent = strings.copy;
    }, 1200);
  });
})();
