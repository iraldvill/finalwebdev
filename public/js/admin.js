const pageTitles = {
  dashboard: 'Dashboard',
  bookings: 'Bookings',
  accounts: 'Registered Accounts',
  stations: 'PC Stations',
  reviews: 'Customer Reviews'
};

function showTab(tabName, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tabName).classList.add('active');

  document.querySelectorAll('.sidebar-nav button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  document.getElementById('pageTitle').textContent = pageTitles[tabName];
}

// Open the tab named in the URL hash (e.g. /dashboard#accounts)
document.addEventListener('DOMContentLoaded', () => {
  const tab = window.location.hash.replace('#', '');
  const btn = document.querySelector('[data-tab="' + tab + '"]');
  if (tab && btn) {
    showTab(tab, btn);
  }
});