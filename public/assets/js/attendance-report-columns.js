/* Display preferences only; exports continue to contain the complete report. */
(() => {
  'use strict';
  function init(root) {
    if (root.dataset.columnsReady) return;
    root.dataset.columnsReady = '1';
    const toggles = [...root.querySelectorAll('[data-column-toggle]')];
    const key = 'attendance-report-columns:v1:' + root.dataset.columnUser;
    let saved = {};
    try {
      const value = JSON.parse(localStorage.getItem(key) || '{}');
      if (value && typeof value === 'object' && !Array.isArray(value)) saved = value;
    } catch (_) { /* Defaults remain usable when storage is unavailable. */ }
    function apply() {
      for (const toggle of toggles) {
        root.querySelectorAll('[data-column="' + toggle.dataset.columnToggle + '"]')
          .forEach(cell => { cell.hidden = !toggle.checked; });
      }
      root.querySelector('[data-column-count]').textContent =
        '(' + toggles.filter(toggle => toggle.checked).length + '/' + toggles.length + ')';
    }
    for (const toggle of toggles) {
      toggle.checked = saved[toggle.dataset.columnToggle] === true;
      toggle.addEventListener('change', () => {
        apply();
        try {
          localStorage.setItem(key, JSON.stringify(Object.fromEntries(
            toggles.map(item => [item.dataset.columnToggle, item.checked])
          )));
        } catch (_) { /* Display changes do not depend on persistence. */ }
      });
    }
    apply();
  }
  function start() {
    document.querySelectorAll('[data-attendance-columns]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
  else start();
})();
