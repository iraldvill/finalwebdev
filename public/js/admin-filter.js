// Search & filter for the Accounts, Bookings and Reviews tables.
// Each .filter-bar controls the table named in its data-target.
document.querySelectorAll('.filter-bar').forEach(function (bar) {
  var table = document.getElementById(bar.dataset.target);
  if (!table) return;

  var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
  var count = bar.closest('.panel, [data-filter-panel]').querySelector('.count');
  var columns = table.querySelectorAll('thead th').length;
  var controls = bar.querySelectorAll('[data-filter]');

  var noMatch = document.createElement('tr');
  noMatch.className = 'empty-row';
  noMatch.hidden = true;
  noMatch.innerHTML = '<td colspan="' + columns + '">No records match your search.</td>';
  table.querySelector('tbody').appendChild(noMatch);

  function apply() {
    var shown = 0;
    rows.forEach(function (row) {
      var ok = true;
      controls.forEach(function (c) {
        var value = c.value.trim().toLowerCase();
        if (!value) return;
        if (c.dataset.filter === 'search') {
          if (row.dataset.search.indexOf(value) === -1) ok = false;
        } else if ((row.dataset[c.dataset.filter] || '') !== value) {
          ok = false;
        }
      });
      row.hidden = !ok;
      if (ok) shown++;
    });
    noMatch.hidden = rows.length === 0 || shown > 0;
    if (count) {
      count.textContent = shown === rows.length ? rows.length + ' total' : shown + ' of ' + rows.length;
    }
  }

  controls.forEach(function (c) {
    c.addEventListener('input', apply);
    c.addEventListener('change', apply);
  });

  bar.querySelector('.filter-reset').addEventListener('click', function () {
    controls.forEach(function (c) { c.value = ''; });
    apply();
  });
});