(() => {
  // Ensure add/account pages don't stay hidden by the no-flash CSS injected server-side.
  const markDomReady = () => {
    try {
      document.body?.classList.add('account-dom-ready');
    } catch {
      // no-op
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markDomReady, { once: true });
  }
  markDomReady();

  // Some pages can load without the Choices.js vendor script (or it can fail to load).
  // The theme bundle expects `window.Choices` to exist; provide a no-op shim to prevent runtime crashes
  // that would otherwise stop subsequent page scripts (wizard bindings, redirects, etc.).
  if (typeof window.Choices !== 'function') {
    class NoopChoices {
      constructor() {}
      destroy() {}
      setChoiceByValue() {}
      clearChoices() {}
      clearStore() {}
      removeActiveItems() {}
      removeActiveItemsByValue() {}
    }
    window.Choices = NoopChoices;
  }

  const ensurePageLoader = () => {
    const styleId = 'mc-page-loader-style';
    if (!document.getElementById(styleId)) {
      const style = document.createElement('style');
      style.id = styleId;
      style.textContent = `
        .mc-page-loader{
          position:fixed;
          inset:0;
          z-index:200000;
          display:flex;
          align-items:center;
          justify-content:center;
          padding:24px;
          background:rgba(255,255,255,.42);
          backdrop-filter:blur(8px);
          -webkit-backdrop-filter:blur(8px);
          opacity:0;
          visibility:hidden;
          pointer-events:none;
          transition:opacity .18s ease, visibility .18s ease;
        }
        .mc-page-loader.is-visible{
          opacity:1;
          visibility:visible;
          pointer-events:auto;
        }
        .mc-page-loader__card{
          width:72px;
          height:72px;
          display:flex;
          align-items:center;
          justify-content:center;
        }
        .mc-page-loader__spinner{
          position:relative;
          width:52px;
          height:52px;
          filter:drop-shadow(0 6px 18px rgba(249, 115, 22, .20));
        }
        .mc-inline-loading-text{
          width:28px;
          height:28px;
          display:inline-block;
          vertical-align:middle;
          font-size:0 !important;
          line-height:0 !important;
          color:transparent !important;
          position:relative;
          overflow:hidden;
        }
        .mc-page-loader__spinner::before,
        .mc-page-loader__spinner::after,
        .mc-inline-loading-text::before,
        .mc-inline-loading-text::after{
          content:"";
          position:absolute;
          inset:0;
          border-radius:50%;
        }
        .mc-page-loader__spinner::before{
          border:4px solid rgba(15, 23, 42, .10);
          border-top-color:#fd7e14;
          border-right-color:#f59e0b;
          animation:mc-page-loader-spin .9s linear infinite;
        }
        .mc-page-loader__spinner::after{
          inset:9px;
          border:4px solid rgba(249, 115, 22, .20);
          border-bottom-color:#f97316;
          border-left-color:#fb923c;
          animation:mc-page-loader-spin-reverse .72s linear infinite;
        }
        .mc-inline-loading-text::before{
          border:3px solid rgba(15, 23, 42, .10);
          border-top-color:#fd7e14;
          border-right-color:#f59e0b;
          animation:mc-page-loader-spin .9s linear infinite;
        }
        .mc-inline-loading-text::after{
          inset:6px;
          border:3px solid rgba(249, 115, 22, .20);
          border-bottom-color:#f97316;
          border-left-color:#fb923c;
          animation:mc-page-loader-spin-reverse .72s linear infinite;
        }
        @keyframes mc-page-loader-spin{
          to{transform:rotate(360deg)}
        }
        @keyframes mc-page-loader-spin-reverse{
          to{transform:rotate(-360deg)}
        }
      `;
      document.head.appendChild(style);
    }

    let overlay = document.getElementById('mcPageLoader');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'mcPageLoader';
      overlay.className = 'mc-page-loader';
      overlay.setAttribute('aria-hidden', 'true');
      overlay.innerHTML = `
        <div class="mc-page-loader__card" role="status" aria-live="polite" aria-busy="true">
          <div class="mc-page-loader__spinner" aria-hidden="true"></div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    return overlay;
  };

  const showPageLoader = () => {
    const overlay = ensurePageLoader();
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-hidden', 'false');
    document.body?.classList.add('mc-page-loading');
  };

  const hidePageLoader = () => {
    const overlay = document.getElementById('mcPageLoader');
    if (!overlay) return;
    overlay.classList.remove('is-visible');
    overlay.setAttribute('aria-hidden', 'true');
    document.body?.classList.remove('mc-page-loading');
  };

  const shouldKeepLoaderVisible = () => {
    const body = document.body;
    if (!body) return document.readyState !== 'complete';
    const waitingForEntry = body.classList.contains('monaclick-entry-shell') && body.getAttribute('data-entry-ready') !== '1';
    if (waitingForEntry) return true;
    return document.readyState !== 'complete';
  };

  const normalizeLoadingPlaceholders = (root = document) => {
    const scope = root && typeof root.querySelectorAll === 'function' ? root : document;
    const nodes = [];
    if (root instanceof Element) nodes.push(root);
    scope.querySelectorAll?.('*').forEach((node) => nodes.push(node));

    nodes.forEach((node) => {
      if (!(node instanceof Element)) return;
      if (node.closest('#mcPageLoader')) return;
      if (node.dataset.mcLoadingNormalized === '1') return;
      if (node.children.length) return;

      const text = String(node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (!text) return;
      const isLoadingText = text === 'loading'
        || text === 'loading...'
        || text === 'loading…'
        || text === 'please wait'
        || text === 'please wait...'
        || text === 'please wait…'
        || text.startsWith('loading ')
        || text.startsWith('please wait ');
      if (!isLoadingText) return;

      node.dataset.mcLoadingNormalized = '1';
      node.setAttribute('aria-hidden', 'true');
      node.textContent = '';
      node.classList.add('mc-inline-loading-text');
    });
  };

  window.__MC_SHOW_PAGE_LOADER__ = showPageLoader;
  window.__MC_HIDE_PAGE_LOADER__ = hidePageLoader;

  const isNavigatingAnchor = (anchor) => {
    if (!(anchor instanceof HTMLAnchorElement)) return false;
    if (anchor.closest('.glightbox-container, .gslide, .goverlay')) return false;
    if (document.body?.classList.contains('glightbox-open')) return false;
    const href = (anchor.getAttribute('href') || '').trim();
    if (!href || href === '#' || href === '#!') return false;
    if (anchor.hasAttribute('data-mc-no-loader')) return false;
    if (anchor.hasAttribute('download')) return false;
    if ((anchor.getAttribute('target') || '').toLowerCase() === '_blank') return false;
    if (anchor.hasAttribute('data-bs-toggle') || anchor.getAttribute('role') === 'button') return false;
    if (/^(mailto:|tel:|javascript:)/i.test(href)) return false;

    let url;
    try {
      url = new URL(anchor.href, window.location.href);
    } catch {
      return false;
    }

    if (url.origin !== window.location.origin) return false;
    if (window.location.pathname.startsWith('/listings') && url.pathname === window.location.pathname) return false;
    if (url.href === window.location.href) return false;
    return true;
  };

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented) return;
    if (event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const anchor = event.target.closest('a');
    if (!isNavigatingAnchor(anchor)) return;
    showPageLoader();
  }, true);

  document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-mc-review-form')) return;
    if (form.hasAttribute('data-mc-no-loader')) return;
    const method = String(form.getAttribute('method') || 'get').toLowerCase();
    if (!['get', 'post'].includes(method)) return;
    if (window.location.pathname.startsWith('/listings')) {
      try {
        const actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        if (actionUrl.origin === window.location.origin && actionUrl.pathname === window.location.pathname) {
          return;
        }
      } catch {
        // ignore malformed action URLs and fall through
      }
    }
    showPageLoader();
  }, true);

  window.addEventListener('pageshow', () => {
    normalizeLoadingPlaceholders();
    if (shouldKeepLoaderVisible()) {
      showPageLoader();
      return;
    }
    hidePageLoader();
  });

  window.addEventListener('load', () => {
    normalizeLoadingPlaceholders();
    hidePageLoader();
  }, { once: true });

  const loadingObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => normalizeLoadingPlaceholders(node));
      if (mutation.type === 'characterData' && mutation.target?.parentElement) {
        normalizeLoadingPlaceholders(mutation.target.parentElement);
      }
    });
  });

  if (document.body) {
    loadingObserver.observe(document.body, { childList: true, subtree: true, characterData: true });
    normalizeLoadingPlaceholders(document.body);
  } else {
    document.addEventListener('DOMContentLoaded', () => {
      if (!document.body) return;
      loadingObserver.observe(document.body, { childList: true, subtree: true, characterData: true });
      normalizeLoadingPlaceholders(document.body);
    }, { once: true });
  }

  const footer = document.querySelector('footer.footer');

  const currentPath = window.location.pathname.toLowerCase();
  const isAuthSplitPage = currentPath === '/signin' || currentPath === '/signup' || currentPath === '/password-recovery';
  const isAuthed = !!window.__MC_AUTH__;

  const ensureAddListingAuthModal = () => {
    let modalEl = document.getElementById('mcAddListingAuthModal');
    if (modalEl) return modalEl;

    modalEl = document.createElement('div');
    modalEl.id = 'mcAddListingAuthModal';
    modalEl.className = 'modal fade';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h2 class="h4 modal-title mb-0">Create a listing</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body pt-3">
            <p class="text-body-secondary mb-4">Please sign in or create an account first to add your listing.</p>
            <div class="d-flex flex-column gap-2">
              <a class="btn btn-primary" data-mc-add-listing-signin href="/signin?next=%2Fadd-listing">Sign in</a>
              <a class="btn btn-outline-secondary" data-mc-add-listing-signup href="/signup?next=%2Fadd-listing">Sign up</a>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modalEl);
    return modalEl;
  };

  const bindAddListingAuthPrompt = () => {
    if (isAuthed || isAuthSplitPage) return;

    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('a[href="/add-listing"]');
      if (!(trigger instanceof HTMLAnchorElement)) return;
      if (trigger.hasAttribute('data-mc-no-auth-prompt')) return;

      event.preventDefault();
      event.stopPropagation();

      const modalEl = ensureAddListingAuthModal();
      const next = encodeURIComponent('/add-listing');
      modalEl.querySelector('[data-mc-add-listing-signin]')?.setAttribute('href', `/signin?next=${next}`);
      modalEl.querySelector('[data-mc-add-listing-signup]')?.setAttribute('href', `/signup?next=${next}`);

      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        return;
      }

      const wantsSignIn = window.confirm('Please sign in or sign up first to create a listing.\n\nPress OK for Sign in, or Cancel for Sign up.');
      window.location.href = wantsSignIn ? `/signin?next=${next}` : `/signup?next=${next}`;
    }, true);
  };
  bindAddListingAuthPrompt();

  const needsVerticalCenter = currentPath === '/signin' || currentPath === '/signup' || currentPath === '/password-recovery';
  if (needsVerticalCenter) {
    const panel = document.querySelector('main.content-wrapper > .d-lg-flex > div.d-flex.flex-column.min-vh-100');
    if (panel) panel.classList.add('justify-content-center');

    const heading = panel?.querySelector('h1.h2.mt-auto');
    if (heading) heading.classList.remove('mt-auto');
  }
  if (isAuthSplitPage) {
    const main = document.querySelector('main.content-wrapper');
    const splitRow = main?.querySelector(':scope > .d-lg-flex');
    const header = main?.querySelector('header.navbar.navbar-sticky');

    if (main && splitRow && header && header.parentElement !== main) {
      main.insertBefore(header, splitRow);
      header.classList.add('mb-2');
    }

    if (main && footer) {
      main.insertAdjacentElement('afterend', footer);
    }
  }
  if (footer) {
  const moduleLinks = [
    { href: '/listings/contractors', label: 'Contractors' },
    { href: '/listings/real-estate', label: 'Real Estate' },
    { href: '/listings/cars', label: 'Cars' },
    { href: '/listings/restaurants', label: 'Restaurants' },
  ];

  const cities = ['New York', 'Chicago', 'Los Angeles', 'Dallas', 'Houston', 'Seattle'];
  const cityPills = cities
    .map((city) => `<li class="nav-item"><a class="nav-link" href="/listings/restaurants?q=${encodeURIComponent(city.toLowerCase())}">${city}</a></li>`)
    .join('');

  const moduleItemsCol1 = moduleLinks
    .slice(0, 3)
    .map((item) => `<li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="${item.href}">${item.label}</a></li>`)
    .join('');

  const moduleItemsCol2 = moduleLinks
    .slice(3)
    .map((item) => `<li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="${item.href}">${item.label}</a></li>`)
    .join('');

  footer.outerHTML = `
    <footer class="footer bg-body border-top" data-bs-theme="dark">
      <div class="container pb-md-2">
        <div class="d-md-flex align-items-center justify-content-between border-bottom pt-5 pb-4 pb-md-5">
          <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-3 gap-sm-4 mb-4 mb-md-0">
            <div class="d-flex align-items-center">
              <i class="fi-mail fs-4 lh-0 text-body d-none d-sm-block me-2"></i>
              <i class="fi-mail text-body d-sm-none me-2"></i>
              <h6 class="ps-sm-1 mb-0">
                <span class="h5 d-none d-sm-block mb-0">Subscribe to Monaclick updates</span>
                <span class="d-sm-none">Subscribe to Monaclick updates</span>
              </h6>
            </div>
            <a class="btn btn-primary" href="#" data-mc-newsletter-trigger="true">Subscribe</a>
          </div>
          <div class="h5 d-none d-sm-block text-center mb-0">
            <span class="text-body-secondary fw-normal me-3">Need help?</span>
            <a class="text-white text-decoration-none hover-effect-underline" href="/contact">Contact us</a>
          </div>
          <div class="h6 d-sm-none text-center mb-0">
            <span class="text-body-secondary fw-normal me-2">Need help?</span>
            <a class="text-white text-decoration-none hover-effect-underline" href="/contact">Contact us</a>
          </div>
        </div>

        <div class="accordion row pt-4 pt-sm-5 mt-3 mt-sm-0" id="footerLinks">
          <div class="col-md-4 col-lg-5 mb-4 mb-sm-5 mb-md-0">
            <a class="d-inline-flex align-items-center text-dark-emphasis text-decoration-none mb-3" href="/combined">
              <span class="flex-shrink-0 text-primary rtl-flip me-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="35" height="34"><path d="M34.5 16.894v10.731c0 3.506-2.869 6.375-6.375 6.375H17.5h-.85C7.725 33.575.5 26.138.5 17c0-9.35 7.65-17 17-17s17 7.544 17 16.894z" fill="currentColor"/><g fill-rule="evenodd"><path d="M17.5 13.258c-3.101 0-5.655 2.554-5.655 5.655s2.554 5.655 5.655 5.655 5.655-2.554 5.655-5.655-2.554-5.655-5.655-5.655zm-9.433 5.655c0-5.187 4.246-9.433 9.433-9.433s9.433 4.246 9.433 9.433a9.36 9.36 0 0 1-1.569 5.192l2.397 2.397a1.89 1.89 0 0 1 0 2.671 1.89 1.89 0 0 1-2.671 0l-2.397-2.397a9.36 9.36 0 0 1-5.192 1.569c-5.187 0-9.433-4.246-9.433-9.433z" fill="#000" fill-opacity=".05"/><g fill="#fff"><path d="M17.394 10.153c-3.723 0-6.741 3.018-6.741 6.741s3.018 6.741 6.741 6.741 6.741-3.018 6.741-6.741-3.018-6.741-6.741-6.741zM7.347 16.894A10.05 10.05 0 0 1 17.394 6.847 10.05 10.05 0 0 1 27.44 16.894 10.05 10.05 0 0 1 17.394 26.94 10.05 10.05 0 0 1 7.347 16.894z"/><path d="M23.025 22.525c.645-.645 1.692-.645 2.337 0l3.188 3.188c.645.645.645 1.692 0 2.337s-1.692.645-2.337 0l-3.187-3.187c-.645-.646-.645-1.692 0-2.337z"/></g></g><path d="M23.662 14.663c2.112 0 3.825-1.713 3.825-3.825s-1.713-3.825-3.825-3.825-3.825 1.713-3.825 3.825 1.713 3.825 3.825 3.825z" fill="#fff"/><path fill-rule="evenodd" d="M23.663 8.429a2.41 2.41 0 0 0-2.408 2.408 2.41 2.41 0 0 0 2.408 2.408 2.41 2.41 0 0 0 2.408-2.408 2.41 2.41 0 0 0-2.408-2.408zm-5.242 2.408c0-2.895 2.347-5.242 5.242-5.242s5.242 2.347 5.242 5.242-2.347 5.242-5.242 5.242-5.242-2.347-5.242-5.242z" fill="currentColor"/></svg>
              </span>
              <span class="fs-4 fw-semibold">Monaclick</span>
            </a>
            <p class="fs-sm text-body-secondary pt-md-1" style="max-width: 290px">One platform for contractors, real estate, cars, and restaurants across major cities.</p>
            <div class="d-flex gap-3 pt-2 pt-md-3">
              <a class="btn btn-icon btn-sm btn-secondary rounded-circle" href="/about" aria-label="Follow us on Instagram"><i class="fi-instagram fs-sm"></i></a>
              <a class="btn btn-icon btn-sm btn-secondary rounded-circle" href="/about" aria-label="Follow us on Facebook"><i class="fi-facebook fs-sm"></i></a>
              <a class="btn btn-icon btn-sm btn-secondary rounded-circle" href="/blog" aria-label="Follow us on X (Twitter)"><i class="fi-x fs-sm"></i></a>
            </div>
          </div>

          <div class="col-sm-8 col-md-5 col-lg-4">
            <div class="accordion-item border-0">
              <h6 class="accordion-header" id="categoryLinksHeading">
                <span class="h5 d-none d-sm-block">Explore modules</span>
                <button type="button" class="accordion-button collapsed py-3 d-sm-none" data-bs-toggle="collapse" data-bs-target="#categoryLinks" aria-expanded="false" aria-controls="categoryLinks">Explore modules</button>
              </h6>
              <div class="accordion-collapse collapse d-sm-block" id="categoryLinks" aria-labelledby="categoryLinksHeading" data-bs-parent="#footerLinks">
                <div class="row row-cols-2">
                  <div class="col">
                    <ul class="nav flex-column gap-2 pt-sm-1 pt-lg-2 pb-3 pb-sm-0 mt-n1 mb-1 mb-sm-0">${moduleItemsCol1}</ul>
                  </div>
                  <div class="col">
                    <ul class="nav flex-column gap-2 pt-sm-1 pt-lg-2 pb-3 pb-sm-0 mt-n1 mb-1 mb-sm-0">${moduleItemsCol2}
                      <li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="/add-listing">Add listing</a></li>
                    </ul>
                  </div>
                </div>
              </div>
              <hr class="d-sm-none my-0">
            </div>
          </div>

          <div class="col-sm-4 col-md-3 col-lg-2 offset-lg-1">
            <div class="accordion-item border-0">
              <h6 class="accordion-header" id="companyLinksHeading">
                <span class="h5 d-none d-sm-block">Company</span>
                <button type="button" class="accordion-button collapsed py-3 d-sm-none" data-bs-toggle="collapse" data-bs-target="#companyLinks" aria-expanded="false" aria-controls="companyLinks">Company</button>
              </h6>
              <div class="accordion-collapse collapse d-sm-block" id="companyLinks" aria-labelledby="companyLinksHeading" data-bs-parent="#footerLinks">
                <ul class="nav flex-column gap-2 pt-sm-1 pt-lg-2 pb-3 pb-sm-0 mt-n1 mb-1 mb-sm-0">
                  <li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="/about">About</a></li>
                  <li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="/blog">Blog</a></li>
                  <li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="/contact">Contact us</a></li>
                  <li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="/terms-and-conditions">Terms of use</a></li>
                  <li class="pt-1"><a class="nav-link hover-effect-underline d-inline text-body fw-normal p-0" href="/privacy-policy">Privacy</a></li>
                </ul>
              </div>
              <hr class="d-sm-none my-0">
            </div>
          </div>
        </div>

        <div class="d-md-flex gap-4 pt-4 pt-sm-5">
          <h6 class="mt-1 mb-md-0">
            <span class="h5 text-nowrap d-none d-sm-block mb-0">Top cities</span>
            <span class="text-nowrap d-sm-none">Top cities</span>
          </h6>
          <ul class="nav nav-pills gap-2 gap-md-3">${cityPills}</ul>
        </div>

        <div class="d-md-flex align-items-center py-4 pt-sm-5 mt-3 mt-sm-0">
          <div class="d-flex gap-2 gap-sm-3 justify-content-center ms-md-auto mb-4 mb-md-0 order-md-2">
            <div><img src="/finder/assets/img/payment-methods/visa-dark-mode.svg" alt="Visa"></div>
            <div><img src="/finder/assets/img/payment-methods/mastercard.svg" alt="Mastercard"></div>
            <div><img src="/finder/assets/img/payment-methods/paypal-dark-mode.svg" alt="PayPal"></div>
            <div><img src="/finder/assets/img/payment-methods/google-pay-dark-mode.svg" alt="Google Pay"></div>
            <div><img src="/finder/assets/img/payment-methods/apple-pay-dark-mode.svg" alt="Apple Pay"></div>
          </div>
          <p class="text-body-secondary fs-sm text-center text-md-start mb-0 me-md-4 order-md-1">&copy; All rights reserved. Developed by <a class="text-body fw-medium text-decoration-none hover-effect-underline" href="https://uslogoandweb.com" target="_blank" rel="noopener">US Logo and Web</a></p>
        </div>
      </div>
    </footer>
  `;
  }
})();

(() => {
  const path = window.location.pathname.toLowerCase();

  const toSlug = (value) =>
    String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');

  const isElementInMain = (element) => {
    const main = element.closest('main');
    if (!main) return false;
    if (element.closest('#customizer')) return false;
    return true;
  };

  const getFieldKey = (element) => {
    const id = (element.id || '').trim();
    if (id) return id;
    const aria = (element.getAttribute('aria-label') || '').trim();
    if (aria) return `${element.tagName.toLowerCase()}:${toSlug(aria)}`;
    return '';
  };

  const encodePayload = (payload) => {
    try {
      const json = JSON.stringify(payload || {});
      return btoa(unescape(encodeURIComponent(json)));
    } catch {
      return '';
    }
  };

  const setupUsLocationFields = () => {
    const stateSelect = document.getElementById('state');
    if (!(stateSelect instanceof HTMLSelectElement)) return;

    const citySelect = document.getElementById('city');
    if (!(citySelect instanceof HTMLSelectElement)) return;

    const citiesCache = new Map(); // stateCode -> [names]
    let citiesRequestId = 0;

    const ensurePlaceholder = () => {
      // Keep placeholder consistent even if the template had "Select location".
      const first = stateSelect.querySelector('option[value=\"\"]');
      if (first) {
        first.textContent = 'Select state';
        return;
      }
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Select state';
      opt.selected = true;
      stateSelect.insertBefore(opt, stateSelect.firstChild);
    };

    const fillStates = (states) => {
      if (stateSelect.dataset.monaclickStatesBound === '1') return;
      stateSelect.dataset.monaclickStatesBound = '1';

      const current = (stateSelect.value || '').trim().toUpperCase();
      // Reset existing options (some templates ship with a "locations" city list here).
      stateSelect.innerHTML = '';
      ensurePlaceholder();
      const existing = new Set(Array.from(stateSelect.options).map((o) => String(o.value || '').toUpperCase()));
      states.forEach((s) => {
        const code = String(s?.code || '').trim().toUpperCase();
        const name = String(s?.name || '').trim();
        if (!code || !name || existing.has(code)) return;
        const opt = document.createElement('option');
        opt.value = code;
        opt.textContent = `${name} (${code})`;
        stateSelect.appendChild(opt);
      });

      if (current && Array.from(stateSelect.options).some((o) => String(o.value || '').toUpperCase() === current)) {
        stateSelect.value = current;
      }
    };

    fetch('/api/monaclick/locations/states')
      .then((res) => (res.ok ? res.json() : null))
      .then((payload) => {
        const states = Array.isArray(payload?.data) ? payload.data : [];
        if (states.length) fillStates(states);
      })
      .catch(() => {
        // no-op
      });

    const fetchCities = (stateCode) => {
      const code = String(stateCode || '').trim().toUpperCase();
      if (!code) return Promise.resolve([]);
      if (citiesCache.has(code)) return Promise.resolve(citiesCache.get(code) || []);

      const requestId = ++citiesRequestId;
      return fetch(`/api/monaclick/locations/cities?state=${encodeURIComponent(code)}`)
        .then((res) => (res.ok ? res.json() : null))
        .then((payload) => {
          if (requestId !== citiesRequestId) return [];
          const cities = Array.isArray(payload?.data) ? payload.data : [];
          const names = cities
            .map((c) => String(c?.name || '').trim())
            .filter((n) => n !== '');
          citiesCache.set(code, names);
          return names;
        })
        .catch(() => []);
    };

    const fillCities = (names) => {
      const current = (citySelect.value || '').trim();
      citySelect.innerHTML = '';
      const p = document.createElement('option');
      p.value = '';
      p.textContent = 'Select city';
      citySelect.appendChild(p);
      (Array.isArray(names) ? names : []).forEach((name) => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        citySelect.appendChild(opt);
      });
      if (current && Array.from(citySelect.options).some((o) => o.value === current)) {
        citySelect.value = current;
      }
    };

    const refreshCities = () => {
      const code = (stateSelect.value || '').trim().toUpperCase();
      fetchCities(code).then((names) => {
        fillCities(names);
      });
    };

    stateSelect.addEventListener('change', () => {
      citySelect.value = '';
      refreshCities();
    });

    refreshCities();
  };

  const setupCarMakeModelSync = () => {
    const brandSelect = document.querySelector('select[aria-label="Car brand select"]');
    const modelSelect = document.querySelector('select[aria-label="Car model select"]');
    if (!(brandSelect instanceof HTMLSelectElement) || !(modelSelect instanceof HTMLSelectElement)) return;

    let makesRequest = 0;
    let modelsRequest = 0;

    const setOptions = (select, items, placeholder) => {
      const current = select.value;
      select.innerHTML = '';
      const p = document.createElement('option');
      p.value = '';
      p.textContent = placeholder;
      select.appendChild(p);
      items.forEach((it) => {
        const v = String(it?.value || '').trim();
        const t = String(it?.label || '').trim();
        if (!v || !t) return;
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = t;
        select.appendChild(opt);
      });
      if (current && Array.from(select.options).some((o) => o.value === current)) {
        select.value = current;
      }
    };

    const loadMakes = () => {
      const req = ++makesRequest;
      return fetch('/api/monaclick/cars/makes')
        .then((res) => (res.ok ? res.json() : null))
        .then((payload) => {
          if (req !== makesRequest) return;
          const makes = Array.isArray(payload?.data) ? payload.data : [];
          setOptions(
            brandSelect,
            makes.map((m) => ({ value: m.name || m.slug, label: m.name || m.slug })),
            'Select brand'
          );
        });
    };

    const loadModels = (makeName) => {
      const req = ++modelsRequest;
      const makeSlug = toSlug(makeName);
      return fetch(`/api/monaclick/cars/models?make=${encodeURIComponent(makeSlug)}`)
        .then((res) => (res.ok ? res.json() : null))
        .then((payload) => {
          if (req !== modelsRequest) return;
          const currentMake = toSlug((brandSelect.value || '').trim());
          if (!currentMake || currentMake !== makeSlug) return;
          const models = Array.isArray(payload?.data) ? payload.data : [];
          setOptions(
            modelSelect,
            models.map((m) => ({ value: m.name || m.slug, label: m.name || m.slug })),
            'Select model'
          );
        })
        .catch(() => {
          // keep existing options if API fails
        });
    };

    if (brandSelect.dataset.monaclickMakeModelBound === '1') return;
    brandSelect.dataset.monaclickMakeModelBound = '1';

    loadMakes()
      .then(() => {
        const selected = (brandSelect.value || '').trim();
        if (selected) loadModels(selected);
      })
      .catch(() => {
        // no-op
      });

    brandSelect.addEventListener('change', () => {
      modelSelect.value = '';
      const makeName = (brandSelect.value || '').trim();
      if (!makeName) return;
      loadModels(makeName);
    });
  };

  const setupJoinProNetworkGate = () => {
    const triggers = Array.from(document.querySelectorAll('a, button'))
      .filter((el) => (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() === 'join pro network');
    if (!triggers.length) return;

    const isAuthed = !!window.__MC_AUTH__;
    if (isAuthed) return;

    const ensureModal = () => {
      if (document.getElementById('mcAuthGateModal')) return;
      const redirect = encodeURIComponent(window.location.pathname + window.location.search);
      document.body.insertAdjacentHTML('beforeend', `
        <div class="modal fade" id="mcAuthGateModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Sign in required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p class="mb-0">Please sign in or create an account to join the Pro Network and publish listings.</p>
              </div>
              <div class="modal-footer">
                <a class="btn btn-outline-secondary" href="/signup?redirect=${redirect}">Sign up</a>
                <a class="btn btn-primary" href="/signin?redirect=${redirect}">Sign in</a>
              </div>
            </div>
          </div>
        </div>
      `);
    };

    const showModal = () => {
      ensureModal();
      const el = document.getElementById('mcAuthGateModal');
      if (!el) return;
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(el).show();
      } else {
        el.classList.add('show');
        el.style.display = 'block';
      }
    };

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        showModal();
      });
    });
  };

  const setupNewsletterPopup = () => {
    const triggers = Array.from(document.querySelectorAll('[data-mc-newsletter-trigger="true"]'));
    if (!triggers.length) return;

    const ensureModal = () => {
      if (document.getElementById('mcNewsletterModal')) return;
      const csrf = String(window.__MC_CSRF__ || '');
      document.body.insertAdjacentHTML('beforeend', `
        <div class="modal fade" id="mcNewsletterModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
              <div class="modal-header border-0 pb-0">
                <div>
                  <span class="badge text-bg-light border mb-2">Newsletter</span>
                  <h5 class="modal-title mb-0">Subscribe to Monaclick updates</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body pt-3">
                <p class="text-body-secondary mb-4">Get new listings, product updates, and local highlights in your inbox.</p>
                <div class="alert alert-success d-none" data-mc-newsletter-success role="alert">
                  Thanks for subscribing. We will keep you posted.
                </div>
                <div class="alert alert-danger d-none" data-mc-newsletter-error role="alert">
                  Please enter a valid email address.
                </div>
                <form class="row g-3" data-mc-newsletter-form data-mc-no-loader>
                  <input type="hidden" name="_token" value="${csrf}">
                  <div class="col-12">
                    <label class="form-label" for="mcNewsletterEmail">Email address</label>
                    <input class="form-control form-control-lg" type="email" id="mcNewsletterEmail" name="email" placeholder="Enter your email" required>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-primary w-100" type="submit">Subscribe</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      `);

      const modal = document.getElementById('mcNewsletterModal');
      const form = modal?.querySelector('[data-mc-newsletter-form]');
      const success = modal?.querySelector('[data-mc-newsletter-success]');
      const error = modal?.querySelector('[data-mc-newsletter-error]');
      const input = modal?.querySelector('#mcNewsletterEmail');
      if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) return;

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const email = String(input.value || '').trim();
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

        success?.classList.add('d-none');
        error?.classList.add('d-none');

        if (!isValid) {
          error?.classList.remove('d-none');
          input.focus();
          return;
        }

        form.reset();
        success?.classList.remove('d-none');
      });
    };

    const showModal = () => {
      ensureModal();
      const el = document.getElementById('mcNewsletterModal');
      if (!el) return;
      const success = el.querySelector('[data-mc-newsletter-success]');
      const error = el.querySelector('[data-mc-newsletter-error]');
      success?.classList.add('d-none');
      error?.classList.add('d-none');
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(el).show();
      } else {
        el.classList.add('show');
        el.style.display = 'block';
      }
      const input = el.querySelector('#mcNewsletterEmail');
      if (input instanceof HTMLInputElement) {
        window.setTimeout(() => input.focus(), 120);
      }
    };

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        showModal();
      });
    });
  };

  const setupDriveEngineSync = () => {
    const driveSelect = document.querySelector('select[aria-label="Drive type select"]');
    const engineSelect = document.querySelector('select[aria-label="Engine select"]');
    if (!(driveSelect instanceof HTMLSelectElement) || !(engineSelect instanceof HTMLSelectElement)) return;

    if (driveSelect.dataset.monaclickDriveEngineBound === '1') return;
    driveSelect.dataset.monaclickDriveEngineBound = '1';

    let driveRequest = 0;
    let engineRequest = 0;

    const setOptions = (select, items, placeholder) => {
      const current = select.value;
      select.innerHTML = '';
      const p = document.createElement('option');
      p.value = '';
      p.textContent = placeholder;
      select.appendChild(p);
      items.forEach((it) => {
        const v = String(it?.value || '').trim();
        const t = String(it?.label || '').trim();
        if (!v || !t) return;
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = t;
        select.appendChild(opt);
      });
      if (current && Array.from(select.options).some((o) => o.value === current)) {
        select.value = current;
      }
    };

    const loadDriveTypes = () => {
      const req = ++driveRequest;
      return fetch('/api/monaclick/cars/drive-types')
        .then((res) => (res.ok ? res.json() : null))
        .then((payload) => {
          if (req !== driveRequest) return;
          const drives = Array.isArray(payload?.data) ? payload.data : [];
          if (!drives.length) return;
          setOptions(
            driveSelect,
            drives.map((d) => ({ value: d.name || d.slug, label: d.name || d.slug })),
            'Select drive type'
          );
        })
        .catch(() => {});
    };

    const loadEngines = (driveValue) => {
      const req = ++engineRequest;
      const driveSlug = toSlug(driveValue);
      return fetch(`/api/monaclick/cars/engines?drive=${encodeURIComponent(driveSlug)}`)
        .then((res) => (res.ok ? res.json() : null))
        .then((payload) => {
          if (req !== engineRequest) return;
          const currentDrive = toSlug((driveSelect.value || '').trim());
          if (!currentDrive || currentDrive !== driveSlug) return;
          const engines = Array.isArray(payload?.data) ? payload.data : [];
          if (!engines.length) return;
          setOptions(
            engineSelect,
            engines.map((e) => ({ value: e.name || e.slug, label: e.name || e.slug })),
            'Select engine'
          );
        })
        .catch(() => {});
    };

    loadDriveTypes().then(() => {
      const drive = (driveSelect.value || '').trim();
      if (drive) loadEngines(drive);
    });

    driveSelect.addEventListener('change', () => {
      engineSelect.value = '';
      const drive = (driveSelect.value || '').trim();
      if (!drive) return;
      loadEngines(drive);
    });
  };

  const setupRestaurantForm = () => {
    // Restaurant form has its own page-specific controller injected from routes/web.php.
    // Keeping global listeners here causes duplicate submit/upload handlers and inconsistent redirects.
    return;
  };

  const setupWizardSubmission = () => {
    const isProperty = path.includes('add-property');
    const isContractor = path.includes('add-contractor');
    if (!isProperty && !isContractor) return;

    const storageKey = isProperty ? 'monaclick:add-property' : 'monaclick:add-contractor';
    const submitPath = isProperty ? '/submit/property' : '/submit/contractor';
    const finalPath = isProperty ? '/add-property-promotion.html' : '/add-contractor-project.html';
    const modulePathPrefix = isProperty ? '/add-property' : '/add-contractor';

    const readState = () => {
      try {
        const raw = localStorage.getItem(storageKey);
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch {
        return {};
      }
    };

    const writeState = (state) => {
      localStorage.setItem(storageKey, JSON.stringify(state));
    };

    const collectState = () => {
      const state = readState();
      const fields = Array.from(document.querySelectorAll('input, select, textarea'))
        .filter((field) => isElementInMain(field))
        .filter((field) => !field.disabled)
        .filter((field) => field.type !== 'file')
        .filter((field) => field.type !== 'range')
        .filter((field) => field.type !== 'color');

      fields.forEach((field) => {
        if (field.type === 'radio' && field.name) {
          const selected = document.querySelector(`input[type="radio"][name="${field.name}"]:checked`);
          if (selected && isElementInMain(selected)) {
            state[`radio:${field.name}`] = selected.id || selected.value || '';
          }
          return;
        }

        const key = getFieldKey(field);
        if (!key) return;

        if (field.type === 'checkbox') {
          state[key] = !!field.checked;
          return;
        }

        if (field.tagName === 'SELECT' && field.multiple) {
          state[key] = Array.from(field.selectedOptions).map((option) => option.value).filter(Boolean);
          return;
        }

        state[key] = field.value;
      });

      writeState(state);
      return state;
    };

    const restoreState = () => {
      const state = readState();
      if (!Object.keys(state).length) return;

      Array.from(document.querySelectorAll('input, select, textarea'))
        .filter((field) => isElementInMain(field))
        .forEach((field) => {
          if (field.type === 'radio' && field.name) {
            const selectedId = state[`radio:${field.name}`];
            if (selectedId && field.id === selectedId) {
              field.checked = true;
            }
            return;
          }

          const key = getFieldKey(field);
          if (!key || !(key in state)) return;

          if (field.type === 'checkbox') {
            field.checked = !!state[key];
            return;
          }

          if (field.tagName === 'SELECT' && field.multiple) {
            const values = Array.isArray(state[key]) ? state[key] : [];
            Array.from(field.options).forEach((option) => {
              option.selected = values.includes(option.value);
            });
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return;
          }

          field.value = state[key] ?? '';
        });
    };

    const submitState = (status) => {
      const state = collectState();
      const payload = encodePayload(state);
      if (!payload) return;

      const form = document.createElement('form');
      form.method = 'get';
      form.action = submitPath;
      form.style.display = 'none';

      const payloadInput = document.createElement('input');
      payloadInput.type = 'hidden';
      payloadInput.name = 'payload';
      payloadInput.value = payload;
      form.appendChild(payloadInput);

      if (status === 'draft') {
        const draftInput = document.createElement('input');
        draftInput.type = 'hidden';
        draftInput.name = 'draft';
        draftInput.value = '1';
        form.appendChild(draftInput);
      } else {
        const publishInput = document.createElement('input');
        publishInput.type = 'hidden';
        publishInput.name = 'publish';
        publishInput.value = '1';
        form.appendChild(publishInput);
      }

      document.body.appendChild(form);
      localStorage.removeItem(storageKey);
      form.submit();
    };

    restoreState();

    const saveHandlers = ['input', 'change', 'blur'];
    saveHandlers.forEach((eventName) => {
      document.addEventListener(eventName, (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) return;
        if (!isElementInMain(target)) return;
        collectState();
      }, true);
    });

    document.querySelectorAll(`a[href^="${modulePathPrefix}"], a[href^="add-"]`).forEach((link) => {
      link.addEventListener('click', () => {
        collectState();
      });
    });

    const saveDraftButton = Array.from(document.querySelectorAll('button.btn.btn-lg.btn-outline-secondary'))
      .find((button) => (button.textContent || '').trim().toLowerCase() === 'save draft');

    if (saveDraftButton) {
      saveDraftButton.addEventListener('click', (event) => {
        event.preventDefault();
        submitState('draft');
      });
    }

    if (path.endsWith(finalPath)) {
      const publishButton = isProperty
        ? Array.from(document.querySelectorAll('a.btn.btn-lg.btn-primary'))
            .find((button) => (button.textContent || '').toLowerCase().includes('publish property listing'))
        : Array.from(document.querySelectorAll('button.btn.btn-lg.btn-primary'))
            .find((button) => (button.textContent || '').toLowerCase().includes('publish listing'));

      if (publishButton) {
        publishButton.addEventListener('click', (event) => {
          event.preventDefault();
          submitState('publish');
        });
      }
    }
  };

  const setupProfilePhotoButton = () => {
    if (path.startsWith('/account')) return;
    const updateBtn = Array.from(document.querySelectorAll('button, a'))
      .find((el) => (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() === 'update photo');
    if (!updateBtn) return;
    if (updateBtn.dataset.monaclickPhotoBound === '1') return;
    updateBtn.dataset.monaclickPhotoBound = '1';

    const section = updateBtn.closest('.d-flex') || updateBtn.closest('.row') || document;
    const avatar = section.querySelector('img.rounded-circle') || section.querySelector('img');
    if (!avatar) return;

    let input = document.getElementById('monaclick-profile-photo-input');
    if (!input) {
      input = document.createElement('input');
      input.id = 'monaclick-profile-photo-input';
      input.type = 'file';
      input.accept = 'image/*';
      input.className = 'd-none';
      document.body.appendChild(input);
    }
    if (input.dataset.monaclickPhotoInputBound === '1') return;
    input.dataset.monaclickPhotoInputBound = '1';

    updateBtn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      input.click();
    });

    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      avatar.src = URL.createObjectURL(file);
    });
  };

  const setupRequiredAsterisks = () => {
    if (!path.startsWith('/add-')) return;

    const labels = document.querySelectorAll('main label.form-label, main label.form-check-label');
    labels.forEach((label) => {
      if (!(label instanceof HTMLElement)) return;
      if (label.dataset.mcRequiredAsteriskReady === '1') return;

      const html = String(label.innerHTML || '');
      if (!html.includes('*')) return;
      if (html.includes('text-danger') && html.includes('*')) {
        label.dataset.mcRequiredAsteriskReady = '1';
        return;
      }

      const updated = html.replace(/\s\*(?=(?:\s|&nbsp;|<|$))/g, ' <span class="text-danger">*</span>');
      if (updated !== html) {
        label.innerHTML = updated;
      }

      label.dataset.mcRequiredAsteriskReady = '1';
    });
  };

  const setupAccountBilling = () => {
    const isPaymentPage = path === '/account/payment';
    const isSubscriptionsPage = path === '/account/subscriptions';
    if (!isPaymentPage && !isSubscriptionsPage) return;

    const csrfToken = String(window.__MC_CSRF__ || '');
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

    const fetchJson = async (url, options = {}) => {
      const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
          ...headers,
          ...(options.headers || {}),
        },
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch {
        payload = null;
      }

      if (!response.ok) {
        const validationErrors = Object.values(payload?.errors || {}).reduce((all, entry) => {
          if (Array.isArray(entry)) return all.concat(entry);
          if (entry) all.push(entry);
          return all;
        }, []);
        const message = payload?.message
          || validationErrors.find(Boolean)
          || 'Something went wrong. Please try again.';
        throw new Error(String(message));
      }

      return payload;
    };

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    const showInlineNotice = (root, message, type = 'success') => {
      if (!(root instanceof Element)) return;
      root.querySelectorAll('[data-mc-billing-notice]').forEach((node) => node.remove());
      if (!message) return;
      const notice = document.createElement('div');
      notice.className = `alert alert-${type}`;
      notice.setAttribute('data-mc-billing-notice', '1');
      notice.textContent = message;
      root.prepend(notice);
    };

    const brandLabel = (value) => {
      const normalized = String(value || '').trim().toLowerCase();
      return ({
        visa: 'Visa',
        mastercard: 'Mastercard',
        paypal: 'PayPal',
        card: 'Card',
      })[normalized] || (normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Card');
    };

    const paymentGradient = (method) => {
      if (method.type === 'paypal') {
        return 'linear-gradient(90deg, #d6ecff 0%, #f4fbff 100%)';
      }
      if ((method.brand || '').toLowerCase() === 'mastercard') {
        return 'linear-gradient(90deg, #fcb69f 0%, #ffe8c9 100%)';
      }
      return 'linear-gradient(90deg, #accbee 0%, #dbeafe 100%)';
    };

    const paymentIcon = (method) => {
      if (method.type === 'paypal') {
        return '<span class="fw-semibold text-primary fs-5">PayPal</span>';
      }
      if ((method.brand || '').toLowerCase() === 'mastercard') {
        return '<span class="fw-semibold fs-5">Mastercard</span>';
      }
      if ((method.brand || '').toLowerCase() === 'visa') {
        return '<span class="fw-semibold text-dark-emphasis fs-5">Visa</span>';
      }
      return '<span class="fw-semibold fs-5">Card</span>';
    };

    const maskCardNumber = (method) => {
      if (method.type === 'paypal') {
        return escapeHtml(method.paypal_email || '');
      }
      const lastFour = String(method.last_four || '').replace(/\D+/g, '').slice(-4);
      return lastFour ? `**** **** **** ${escapeHtml(lastFour)}` : 'Card on file';
    };

    const openPaymentModal = () => {
      const modalEl = document.getElementById('addPayment');
      if (!modalEl) return null;
      if (window.bootstrap && window.bootstrap.Modal) {
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
      }
      return null;
    };

    if (isPaymentPage) {
      const pageRoot = document.querySelector('.col-lg-9');
      const cardsRoot = document.getElementById('account-payment-cards');
      const modalEl = document.getElementById('addPayment');
      if (!(cardsRoot instanceof HTMLElement) || !(modalEl instanceof HTMLElement)) return;

      const cardForm = modalEl.querySelector('#add-card form');
      const paypalForm = modalEl.querySelector('#add-paypal form');
      const cardNumberInput = modalEl.querySelector('#card-number');
      const cardNameInput = modalEl.querySelector('#card-name');
      const cardExpiryInput = modalEl.querySelector('#card-expiration');
      const paypalEmailInput = modalEl.querySelector('#paypal-email');
      const modalTitleTabs = modalEl.querySelectorAll('[data-bs-target]');
      if (!(cardForm instanceof HTMLFormElement) || !(paypalForm instanceof HTMLFormElement)) return;

      let methods = [];
      let editingMethodId = null;

      const resetModal = () => {
        editingMethodId = null;
        cardForm.reset();
        paypalForm.reset();
        modalEl.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        modalEl.querySelectorAll('[data-mc-billing-notice]').forEach((node) => node.remove());
        const cardSubmit = cardForm.querySelector('button[type="submit"]');
        const paypalSubmit = paypalForm.querySelector('button[type="submit"]');
        if (cardSubmit) {
          cardSubmit.dataset.originalLabel = 'Add card';
          cardSubmit.textContent = 'Add card';
        }
        if (paypalSubmit) {
          paypalSubmit.dataset.originalLabel = 'Continue';
          paypalSubmit.textContent = 'Continue';
        }
      };

      const setSavingState = (form, isSaving) => {
        const submit = form.querySelector('button[type="submit"]');
        if (!(submit instanceof HTMLButtonElement)) return;
        submit.disabled = isSaving;
        submit.dataset.originalLabel = submit.dataset.originalLabel || submit.textContent || '';
        submit.textContent = isSaving ? 'Saving...' : submit.dataset.originalLabel;
      };

      const validateCardPayload = () => {
        const number = String(cardNumberInput?.value || '').replace(/\D+/g, '');
        const name = String(cardNameInput?.value || '').trim();
        const expiry = String(cardExpiryInput?.value || '').trim();
        let hasError = false;

        [cardNumberInput, cardNameInput, cardExpiryInput].forEach((input) => input?.classList.remove('is-invalid'));

        if (number.length < 13) {
          cardNumberInput?.classList.add('is-invalid');
          hasError = true;
        }
        if (name.length < 2) {
          cardNameInput?.classList.add('is-invalid');
          hasError = true;
        }
        if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(expiry)) {
          cardExpiryInput?.classList.add('is-invalid');
          hasError = true;
        }

        if (hasError) {
          throw new Error('Please complete the card details correctly.');
        }

        return { type: 'card', number, name, expiry };
      };

      const validatePaypalPayload = () => {
        const paypalEmail = String(paypalEmailInput?.value || '').trim();
        paypalEmailInput?.classList.remove('is-invalid');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(paypalEmail)) {
          paypalEmailInput?.classList.add('is-invalid');
          throw new Error('Please provide a valid PayPal email address.');
        }

        return { type: 'paypal', paypal_email: paypalEmail };
      };

      const populateModalForEdit = (methodId) => {
        const method = methods.find((item) => String(item.id) === String(methodId));
        if (!method) return;

        resetModal();
        editingMethodId = String(method.id);
        const modal = openPaymentModal();
        const activeTab = modalEl.querySelector(method.type === 'paypal' ? '#add-paypal-tab' : '#add-card-tab');
        if (activeTab && window.bootstrap?.Tab) {
          window.bootstrap.Tab.getOrCreateInstance(activeTab).show();
        }

        if (method.type === 'paypal') {
          if (paypalEmailInput) paypalEmailInput.value = method.paypal_email || '';
          const submit = paypalForm.querySelector('button[type="submit"]');
          if (submit) {
            submit.dataset.originalLabel = 'Update PayPal';
            submit.textContent = 'Update PayPal';
          }
        } else {
          if (cardNumberInput) cardNumberInput.value = method.number || '';
          if (cardNameInput) cardNameInput.value = method.name || '';
          if (cardExpiryInput) cardExpiryInput.value = method.expiry || '';
          const submit = cardForm.querySelector('button[type="submit"]');
          if (submit) {
            submit.dataset.originalLabel = 'Update card';
            submit.textContent = 'Update card';
          }
        }

        modal?.show();
      };

      const renderMethods = () => {
        if (!methods.length) {
          cardsRoot.innerHTML = `
            <div class="card border-0 bg-body-secondary">
              <div class="card-body py-4">
                <h3 class="h5 mb-2">No payment methods yet</h3>
                <p class="text-body-secondary mb-0">Add a card or PayPal account so your listing subscriptions have a billing method on file.</p>
              </div>
            </div>
          `;
          return;
        }

        cardsRoot.innerHTML = methods.map((method) => `
          <div class="card w-100 border-0 overflow-hidden" data-payment-method-id="${escapeHtml(method.id)}">
            <div class="card-body position-relative z-2">
              <div class="d-flex align-items-center pb-4 mb-2 mb-md-3">
                ${paymentIcon(method)}
                ${method.is_primary ? '<span class="badge text-bg-light ms-3">Primary</span>' : ''}
                <div class="dropdown ms-auto">
                  <button type="button" class="btn btn-icon btn-sm fs-xl text-dark-emphasis border-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                    <i class="fi-more-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" style="--fn-dropdown-min-width: 8rem">
                    <li>
                      <button type="button" class="dropdown-item" data-action="edit-payment" data-id="${escapeHtml(method.id)}">
                        <i class="fi-edit opacity-75 me-2"></i>
                        Edit
                      </button>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item text-danger" data-action="delete-payment" data-id="${escapeHtml(method.id)}">
                        <i class="fi-trash opacity-75 me-2"></i>
                        Delete
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="h5 pt-md-1 pb-2 pb-md-3" style="letter-spacing: 1.25px">${maskCardNumber(method)}</div>
              <div class="d-flex justify-content-between gap-3">
                <div class="me-3">
                  <div class="fs-xs text-body mb-1">${method.type === 'paypal' ? 'Account' : 'Name'}</div>
                  <div class="h6 fs-sm mb-0">${escapeHtml(method.type === 'paypal' ? brandLabel(method.brand || method.type) : (method.name || 'Cardholder'))}</div>
                </div>
                <div class="text-end">
                  <div class="fs-xs text-body mb-1">${method.type === 'paypal' ? 'Status' : 'Expiry date'}</div>
                  <div class="h6 fs-sm mb-0">${escapeHtml(method.type === 'paypal' ? (method.status || 'active') : (method.expiry || ''))}</div>
                </div>
              </div>
            </div>
            <span class="position-absolute top-0 start-0 w-100 h-100 rounded-4" style="background:${paymentGradient(method)}"></span>
          </div>
        `).join('');
      };

      const loadMethods = async (message = '') => {
        try {
          const payload = await fetchJson('/account/api/payment-methods');
          methods = Array.isArray(payload?.data) ? payload.data : [];
          renderMethods();
          if (message) showInlineNotice(pageRoot || cardsRoot, message, 'success');
        } catch (error) {
          cardsRoot.innerHTML = `
            <div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to load payment methods.')}</div>
          `;
        }
      };

      const submitPaymentMethod = async (payload) => {
        const isEdit = !!editingMethodId;
        const url = isEdit ? `/account/api/payment-methods/${encodeURIComponent(editingMethodId)}` : '/account/api/payment-methods';
        const method = isEdit ? 'PATCH' : 'POST';
        const activeForm = payload.type === 'paypal' ? paypalForm : cardForm;
        setSavingState(activeForm, true);
        showInlineNotice(modalEl.querySelector('.modal-body') || modalEl, '');

        try {
          await fetchJson(url, {
            method,
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
          });
          openPaymentModal()?.hide();
          resetModal();
          await loadMethods(isEdit ? 'Payment method updated.' : 'Payment method saved.');
        } catch (error) {
          showInlineNotice(modalEl.querySelector('.modal-body') || modalEl, error.message || 'Unable to save payment method.', 'danger');
        } finally {
          setSavingState(activeForm, false);
        }
      };

      cardForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
          await submitPaymentMethod(validateCardPayload());
        } catch (error) {
          showInlineNotice(modalEl.querySelector('.modal-body') || modalEl, error.message || 'Unable to save payment method.', 'danger');
        }
      });

      paypalForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
          await submitPaymentMethod(validatePaypalPayload());
        } catch (error) {
          showInlineNotice(modalEl.querySelector('.modal-body') || modalEl, error.message || 'Unable to save payment method.', 'danger');
        }
      });

      modalEl.addEventListener('hidden.bs.modal', resetModal);
      modalTitleTabs.forEach((tab) => {
        tab.addEventListener('shown.bs.tab', () => {
          showInlineNotice(modalEl.querySelector('.modal-body') || modalEl, '');
        });
      });

      cardsRoot.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-action="edit-payment"]');
        if (editButton) {
          event.preventDefault();
          populateModalForEdit(editButton.getAttribute('data-id'));
          return;
        }

        const deleteButton = event.target.closest('[data-action="delete-payment"]');
        if (!deleteButton) return;
        event.preventDefault();
        const methodId = deleteButton.getAttribute('data-id');
        if (!methodId || !window.confirm('Delete this payment method?')) return;

        try {
          await fetchJson(`/account/api/payment-methods/${encodeURIComponent(methodId)}`, { method: 'DELETE' });
          await loadMethods('Payment method deleted.');
        } catch (error) {
          showInlineNotice(pageRoot || cardsRoot, error.message || 'Unable to delete payment method.', 'danger');
        }
      });

      loadMethods();
      return;
    }

    if (isSubscriptionsPage) {
      const summary = document.getElementById('subscriptionsSummary');
      const list = document.getElementById('subscriptionsList');
      if (!(summary instanceof HTMLElement) || !(list instanceof HTMLElement)) return;

      const formatDate = (value) => {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
        }).format(date);
      };

      const paymentLabel = (paymentMethod) => {
        if (!paymentMethod) return 'No payment method on file';
        if (paymentMethod.type === 'paypal') {
          return paymentMethod.paypal_email || 'PayPal';
        }
        const lastFour = String(paymentMethod.last_four || '').replace(/\D+/g, '').slice(-4);
        return `${brandLabel(paymentMethod.brand)}${lastFour ? ` ending in ${lastFour}` : ''}`;
      };

      const renderSubscriptions = (items) => {
        const activeCount = items.filter((item) => String(item.status || '').toLowerCase() === 'active').length;
        summary.textContent = items.length
          ? `${activeCount} active subscription${activeCount === 1 ? '' : 's'} across ${items.length} total order${items.length === 1 ? '' : 's'}.`
          : 'No subscription orders yet. Published promotions will appear here after you select a package.';

        if (!items.length) {
          list.innerHTML = `
            <div class="card border-0 bg-body-secondary">
              <div class="card-body py-4">
                <h3 class="h5 mb-2">Nothing here yet</h3>
                <p class="text-body-secondary mb-0">As soon as a listing has a promotion package or add-on service, its subscription history will show up here.</p>
              </div>
            </div>
          `;
          return;
        }

        list.innerHTML = items.map((item) => {
          const services = Array.isArray(item.selected_services_details) ? item.selected_services_details : [];
          const statusClass = String(item.status || '').toLowerCase() === 'active' ? 'success' : 'secondary';
          return `
            <article class="card border-0 shadow-sm">
              <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                  <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                      <span class="badge text-bg-${statusClass} text-uppercase">${escapeHtml(item.status || '')}</span>
                      <span class="badge text-bg-light border">${escapeHtml(item.module_label || item.module || '')}</span>
                    </div>
                    <h3 class="h5 mb-1">${escapeHtml(item.title || 'Listing subscription')}</h3>
                    <p class="text-body-secondary mb-0">Order ${escapeHtml(item.order_number || '')}</p>
                  </div>
                  <div class="text-lg-end">
                    <div class="fw-semibold">${escapeHtml(item.package_label || 'Promotion services')}</div>
                    <div class="text-body-secondary">${escapeHtml(item.package_price || '')}</div>
                    ${item.manage_url ? `<a class="btn btn-sm btn-outline-dark mt-3" href="${escapeHtml(item.manage_url)}">Manage listing</a>` : ''}
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="fs-sm text-body-secondary mb-1">Started</div>
                    <div>${escapeHtml(formatDate(item.started_at || item.created_at) || 'Not available')}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="fs-sm text-body-secondary mb-1">Payment method</div>
                    <div>${escapeHtml(paymentLabel(item.payment_method))}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="fs-sm text-body-secondary mb-1">Admin status</div>
                    <div>${escapeHtml(item.admin_status || '')}</div>
                  </div>
                </div>
                <div class="pt-3 mt-3 border-top">
                  <div class="fs-sm text-body-secondary mb-2">Included services</div>
                  ${services.length ? `
                    <div class="d-flex flex-column gap-2">
                      ${services.map((service) => `
                        <div class="d-flex justify-content-between gap-3">
                          <span>${escapeHtml(service.label || '')}</span>
                          <span class="text-body-secondary text-nowrap">${escapeHtml(service.price || '')}</span>
                        </div>
                      `).join('')}
                    </div>
                  ` : '<div class="text-body-secondary">Package only</div>'}
                </div>
              </div>
            </article>
          `;
        }).join('');
      };

      fetchJson('/account/api/subscriptions')
        .then((payload) => renderSubscriptions(Array.isArray(payload?.data) ? payload.data : []))
        .catch((error) => {
          summary.textContent = 'Unable to load subscriptions right now.';
          list.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to load subscriptions.')}</div>`;
        });
    }
  };

  setupUsLocationFields();
  setupCarMakeModelSync();
  setupDriveEngineSync();
  setupJoinProNetworkGate();
  setupNewsletterPopup();
  setupRestaurantForm();
  setupWizardSubmission();
  setupProfilePhotoButton();
  setupRequiredAsterisks();
  setupAccountBilling();
})();

(() => {
  const path = window.location.pathname.toLowerCase();
  if (path !== '/signin' && path !== '/signup' && path !== '/password-recovery') return;

  const forms = Array.from(document.querySelectorAll('main form.needs-validation'));
  if (!forms.length) return;
  const primaryForm = forms[0];

  const showNotice = (message, type = 'success') => {
    const old = primaryForm.parentElement?.querySelector('.monaclick-auth-notice');
    if (old) old.remove();

    const note = document.createElement('div');
    note.className = 'alert alert-' + type + ' monaclick-auth-notice';
    note.role = 'alert';
    note.textContent = message;
    primaryForm.parentElement?.insertBefore(note, primaryForm);
  };

  const params = new URLSearchParams(window.location.search);
  const email = params.get('email');
  const next = params.get('next');
  forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    if (email) {
      const emailInput = form.querySelector('input[type="email"]');
      if (emailInput && !emailInput.value) emailInput.value = email;
    }
    if (!next) return;
    let hidden = form.querySelector('input[type="hidden"][name="next"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'next';
      form.prepend(hidden);
    }
    hidden.value = next;
  });

  if (path === '/signin') {
    if (params.get('created') === '1') showNotice('Account created successfully. Please sign in.', 'success');
    if (params.get('error') === 'invalid') showNotice('Invalid email or password.', 'danger');
  }

  if (path === '/signup' && params.get('error') === 'exists') {
    showNotice('This email is already registered. Please sign in.', 'warning');
  }

  if (path === '/password-recovery') {
    if (params.get('status') === 'sent') showNotice('Password reset request received. Please check your email.', 'success');
    if (params.get('status') === 'missing') showNotice('No account found with this email.', 'warning');
  }
})();

(function () {
  if (document.getElementById('globalLogoutModal')) return;

  const modal = document.createElement('div');
  modal.id = 'globalLogoutModal';
  modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;align-items:center;justify-content:center;padding:16px;';
  modal.innerHTML = `
    <div style="width:min(520px,95vw);background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.2);padding:22px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h5 style="margin:0;">Confirm logout</h5>
        <button type="button" data-logout-close style="border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;">×</button>
      </div>
      <p style="margin:0 0 18px 0;color:#5b6475;">Are you sure you want to log out?</p>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-outline-secondary" data-logout-close>Cancel</button>
        <a href="/signin" class="btn btn-primary" id="logoutConfirmAction" data-mc-no-loader="1">Yes, log out</a>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

  const open = () => (modal.style.display = 'flex');
  const close = () => (modal.style.display = 'none');

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-logout-trigger]');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      const href = trigger.getAttribute('href') || '/signin';
      document.getElementById('logoutConfirmAction').setAttribute('href', href);
      open();
      return;
    }

    const confirmLink = e.target.closest('#logoutConfirmAction');
    if (confirmLink) {
      e.preventDefault();
      const href = confirmLink.getAttribute('href') || '/signin';
      close();
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
        window.__MC_HIDE_PAGE_LOADER__();
      }
      window.location.assign(href);
      return;
    }

    if (e.target.closest('[data-logout-close]') || e.target === modal) {
      e.preventDefault();
      close();
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();
