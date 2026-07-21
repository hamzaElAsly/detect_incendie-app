import './bootstrap';

const root = document.documentElement;
const applyTheme = (theme) => {
  const dark = theme === 'dark';
  root.classList.toggle('dark', dark);
  localStorage.setItem('fire-command-theme', theme);
  document.querySelectorAll('[data-theme-icon]').forEach((el) => el.textContent = dark ? '☀' : '☾');
  document.querySelectorAll('[data-theme-label]').forEach((el) => el.textContent = dark ? (el.dataset.lightLabel || 'Light') : (el.dataset.darkLabel || 'Dark'));
};
applyTheme(localStorage.getItem('fire-command-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('[data-sidebar]');
  const overlay = document.querySelector('[data-sidebar-overlay]');
  const openSidebar = (open) => {
    sidebar?.classList.toggle('-translate-x-full', !open);
    overlay?.classList.toggle('hidden', !open);
    document.body.classList.toggle('overflow-hidden', open && innerWidth < 1024);
  };
  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => openSidebar(sidebar?.classList.contains('-translate-x-full'))));
  overlay?.addEventListener('click', () => openSidebar(false));
  const setSidebarCollapsed = (collapsed) => {
    root.classList.toggle('sidebar-collapsed', collapsed);
    localStorage.setItem('fire-command-sidebar', collapsed ? 'collapsed' : 'expanded');
    document.querySelectorAll('[data-sidebar-collapse]').forEach(button => button.setAttribute('aria-expanded', String(!collapsed)));
  };
  setSidebarCollapsed(localStorage.getItem('fire-command-sidebar') === 'collapsed');
  document.querySelectorAll('[data-sidebar-collapse]').forEach(button => button.addEventListener('click', () => setSidebarCollapsed(!root.classList.contains('sidebar-collapsed'))));
  addEventListener('resize', () => { if (innerWidth >= 1024) openSidebar(false); });
  document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => applyTheme(root.classList.contains('dark') ? 'light' : 'dark')));
  document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => button.addEventListener('click', (event) => {
    event.stopPropagation(); const menu = document.getElementById(button.getAttribute('aria-controls')); const expanded = button.getAttribute('aria-expanded') === 'true';
    document.querySelectorAll('[data-dropdown-menu]').forEach(el => { if (el !== menu) el.classList.add('hidden'); });
    document.querySelectorAll('[data-dropdown-toggle]').forEach(el => { if (el !== button) el.setAttribute('aria-expanded', 'false'); });
    button.setAttribute('aria-expanded', String(!expanded)); menu?.classList.toggle('hidden', expanded);
  }));
  const closeMenus = () => { document.querySelectorAll('[data-dropdown-menu]').forEach(el => el.classList.add('hidden')); document.querySelectorAll('[data-dropdown-toggle]').forEach(el => el.setAttribute('aria-expanded','false')); };
  document.addEventListener('click', closeMenus);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeMenus(); openSidebar(false); document.querySelector('[data-confirm-dialog][open]')?.close(); } });
  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => { const input = document.getElementById(button.dataset.passwordToggle); if (input) { input.type = input.type === 'password' ? 'text' : 'password'; button.setAttribute('aria-pressed', String(input.type === 'text')); } }));
  const setFormLoading = form => form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.setAttribute('aria-busy','true'); });
  document.querySelectorAll('form:not([data-confirm])').forEach(form => form.addEventListener('submit', () => setFormLoading(form)));
  const dialog = document.querySelector('[data-confirm-dialog]');
  let pendingForm = null;
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (form.dataset.confirmed === 'true' || !dialog) return;
    event.preventDefault(); pendingForm = form;
    dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    dialog.showModal(); dialog.querySelector('[data-confirm-accept]')?.focus();
  }));
  dialog?.querySelector('[data-confirm-accept]')?.addEventListener('click', () => {
    if (!pendingForm) return; pendingForm.dataset.confirmed = 'true'; dialog.close(); setFormLoading(pendingForm); pendingForm.requestSubmit(); pendingForm = null;
  });
  dialog?.querySelectorAll('[data-confirm-cancel]').forEach(button => button.addEventListener('click', () => { dialog.close(); pendingForm = null; }));
});
