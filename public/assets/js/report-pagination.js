/* Shared report paging. All rows remain available to exports and printing. */
(() => {
  'use strict';
  const sizes = [10, 25, 50, 100, 250, 500, -1];
  const key = 'report-page-size';
  const readSize = () => {
    try {
      const value = Number(localStorage.getItem(key));
      return sizes.includes(value) ? value : 25;
    } catch (_) { return 25; }
  };
  const saveSize = value => {
    try { localStorage.setItem(key, String(value)); } catch (_) { /* optional preference */ }
  };
  const button = (text, action) => {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'btn btn-sm btn-light';
    element.textContent = text;
    element.addEventListener('click', action);
    return element;
  };

  function paginate(table, index) {
    if (table.dataset.reportPaginated || table.closest('td, th')) return;
    // The report dashboard already owns DataTables paging and sorting.
    if (table.classList.contains('data-table') && window.jQuery?.fn?.dataTable) return;
    const rows = Array.from(table.rows).filter(row => {
      if (row.closest('table') !== table || !row.cells.length || row.hidden || row.style.display === 'none') return false;
      if (row.parentElement.tagName === 'THEAD' || row.parentElement.tagName === 'TFOOT') return false;
      if (Array.from(row.cells).every(cell => cell.tagName === 'TH')) return false;
      if (row.matches('.total, .totals, .total-row, .summary-row')) return false;
      return !(row.cells.length === 1 && row.cells[0].colSpan > 1);
    });
    if (!rows.length) return;
    table.dataset.reportPaginated = 'true';
    if (!table.id) table.id = `report-paged-table-${index}`;

    // Keep rows connected by rowspan together, including across a page boundary.
    const groups = [];
    for (let i = 0; i < rows.length;) {
      const group = [];
      let end = i + 1;
      while (i < end && i < rows.length) {
        const row = rows[i];
        for (const cell of row.cells) end = Math.max(end, i + Math.max(1, cell.rowSpan));
        group.push(row);
        i++;
      }
      groups.push(group);
    }

    const top = document.createElement('div');
    top.className = 'report-pagination report-pagination-top';
    const label = document.createElement('label');
    label.textContent = 'عدد الصفوف في الصفحة ';
    const select = document.createElement('select');
    select.className = 'report-page-size';
    select.setAttribute('aria-label', 'عدد الصفوف في الصفحة');
    select.setAttribute('aria-controls', table.id);
    for (const size of sizes) select.add(new Option(size === -1 ? 'الكل' : String(size), String(size)));
    label.append(select);
    const info = document.createElement('span');
    info.className = 'report-page-info';
    info.setAttribute('aria-live', 'polite');
    top.append(label, info);

    const bottom = document.createElement('nav');
    bottom.className = 'report-pagination report-pagination-bottom';
    bottom.setAttribute('aria-label', 'صفحات التقرير');
    let size = readSize(), page = 0, pages = [];
    const first = button('الأولى', () => show(0));
    const previous = button('السابق', () => show(page - 1));
    const next = button('التالي', () => show(page + 1));
    const last = button('الأخيرة', () => show(pages.length - 1));
    const pageLabel = document.createElement('label');
    pageLabel.textContent = 'الصفحة ';
    const pageInput = document.createElement('input');
    pageInput.type = 'number';
    pageInput.min = '1';
    pageInput.className = 'report-page-number';
    pageInput.setAttribute('aria-label', 'رقم الصفحة');
    pageInput.setAttribute('aria-controls', table.id);
    const pageTotal = document.createElement('span');
    pageLabel.append(pageInput, pageTotal);
    bottom.append(first, previous, pageLabel, next, last);

    function show(requested) {
      page = Math.max(0, Math.min(pages.length - 1, Number.isFinite(requested) ? Math.floor(requested) : 0));
      const current = new Set(pages[page]);
      for (const row of rows) row.classList.toggle('report-page-hidden', !current.has(row));
      const start = pages.slice(0, page).reduce((sum, chunk) => sum + chunk.length, 0);
      info.textContent = `عرض ${start + 1}–${start + current.size} من ${rows.length} صف`;
      pageInput.value = String(page + 1);
      pageInput.max = String(pages.length);
      pageTotal.textContent = ` من ${pages.length}`;
      first.disabled = previous.disabled = page === 0;
      next.disabled = last.disabled = page === pages.length - 1;
    }
    function rebuild() {
      pages = [];
      let chunk = [];
      for (const group of groups) {
        if (size !== -1 && chunk.length && chunk.length + group.length > size) {
          pages.push(chunk); chunk = [];
        }
        chunk.push(...group);
      }
      if (chunk.length) pages.push(chunk);
      show(0);
    }
    select.value = String(size);
    select.addEventListener('change', () => { size = Number(select.value); saveSize(size); rebuild(); });
    pageInput.addEventListener('change', () => show(Number(pageInput.value) - 1));
    pageInput.addEventListener('keydown', event => {
      if (event.key === 'Enter') { event.preventDefault(); show(Number(pageInput.value) - 1); }
    });
    table.before(top);
    table.after(bottom);
    rebuild();
  }

  const init = () => document.querySelectorAll('table').forEach(paginate);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
