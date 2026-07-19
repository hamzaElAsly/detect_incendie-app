import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toggle = () => {
        sidebar?.classList.toggle('-translate-x-full');
        overlay?.classList.toggle('hidden');
    };
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', toggle));
    document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => button.addEventListener('click', () => {
        const menu = document.getElementById(button.getAttribute('aria-controls'));
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        menu?.classList.toggle('hidden', expanded);
    }));
});
