const menuToggle = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('#mobile-nav');

if (menuToggle && mobileNav) {
    menuToggle.hidden = false;

    const closeMenu = () => {
        mobileNav.hidden = true;
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Abrir menú');
    };

    menuToggle.addEventListener('click', () => {
        const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
        mobileNav.hidden = isOpen;
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? 'Abrir menú' : 'Cerrar menú');
    });

    mobileNav.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !mobileNav.hidden) {
            closeMenu();
            menuToggle.focus();
        }
    });

    window.matchMedia('(min-width: 761px)').addEventListener('change', (event) => {
        if (event.matches) {
            closeMenu();
        }
    });
}

const filters = document.querySelector('.collection-filters');

if (filters) {
    filters.hidden = false;
    const cards = [...document.querySelectorAll('[data-category]')];
    const status = document.querySelector('#filter-status');

    filters.addEventListener('click', (event) => {
        const selected = event.target.closest('[data-filter]');

        if (!selected) {
            return;
        }

        filters.querySelectorAll('[data-filter]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button === selected));
        });

        cards.forEach((card) => {
            card.hidden = selected.dataset.filter !== 'all' && card.dataset.category !== selected.dataset.filter;
        });

        if (status) {
            const count = cards.filter((card) => !card.hidden).length;
            status.textContent = `${count} ${count === 1 ? 'colección disponible' : 'colecciones disponibles'}.`;
        }
    });
}

if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('[data-reveal]').forEach((element) => {
        element.classList.add('reveal-ready');
        observer.observe(element);
    });
}
