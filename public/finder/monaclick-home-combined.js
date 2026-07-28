(() => {
  const CACHE_KEY = 'monaclick.combined.home.cache.v5';
  const CACHE_TTL_MS = 10 * 60 * 1000;
  const swipers = {};

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const fetchListingsPayload = async (module, params = {}) => {
    const query = new URLSearchParams({ module, per_page: '8', ...params });
    const response = await fetch(`/api/monaclick/listings?${query.toString()}`);
    if (!response.ok) throw new Error(`Failed ${module}`);
    return response.json();
  };

  const fetchListings = async (module, params = {}) => {
    const payload = await fetchListingsPayload(module, params);
    return Array.isArray(payload?.data) ? payload.data : [];
  };

  const entryUrl = (item) => `/entry/${encodeURIComponent(item.module)}?slug=${encodeURIComponent(item.slug)}`;
  const carActionsStorage = {
    favorites: 'mc_related_favorites_v1',
    favoriteItems: 'mc_related_favorite_items_v1',
    alerts: 'mc_related_alerts_v1',
    compare: 'mc_related_compare_v1',
    compareItems: 'mc_related_compare_items_v1',
  };
  const slugify = (value) =>
    String(value ?? '')
      .trim()
      .toLowerCase()
      .replaceAll('&', ' and ')
      .replaceAll(/[^a-z0-9]+/g, '-')
      .replaceAll(/-+/g, '-')
      .replaceAll(/^-|-$/g, '');

  const readStoredSlugs = (key) => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(key) || '[]');
      return Array.isArray(parsed) ? parsed.map((value) => String(value || '').trim()).filter(Boolean) : [];
    } catch (_) {
      return [];
    }
  };

  const writeStoredSlugs = (key, values) => {
    try {
      const unique = Array.from(new Set((values || []).map((value) => String(value || '').trim()).filter(Boolean)));
      window.localStorage.setItem(key, JSON.stringify(unique));
    } catch (_) {
      // Ignore storage failures.
    }
  };

  const readStoredMap = (key) => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(key) || '{}');
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (_) {
      return {};
    }
  };

  const writeStoredMap = (key, value) => {
    try {
      window.localStorage.setItem(key, JSON.stringify(value || {}));
    } catch (_) {
      // Ignore storage failures.
    }
  };

  const showCarActionToast = (message) => {
    const id = 'mc-home-combined-car-actions-toast';
    let toast = document.getElementById(id);
    if (!toast) {
      toast = document.createElement('div');
      toast.id = id;
      toast.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:1085;max-width:320px;background:#1f2937;color:#fff;padding:12px 14px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:14px;line-height:1.45;opacity:0;transform:translateY(8px);transition:opacity .18s ease, transform .18s ease;';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
    clearTimeout(showCarActionToast._timer);
    showCarActionToast._timer = setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
    }, 1800);
  };

  const syncCarActionButtonState = (button, active) => {
    if (!button) return;
    button.dataset.active = active ? '1' : '0';
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.classList.toggle('btn-primary', active);
    button.classList.toggle('btn-outline-secondary', !active);
    button.classList.toggle('text-white', active);
  };

  const syncCombinedCarActionStates = (scope = document) => {
    const favorites = new Set(readStoredSlugs(carActionsStorage.favorites));
    const alerts = new Set(readStoredSlugs(carActionsStorage.alerts));
    const compare = new Set(readStoredSlugs(carActionsStorage.compare));

    scope.querySelectorAll('[data-mc-home-car-action]').forEach((button) => {
      const slug = String(button.getAttribute('data-mc-slug') || '').trim();
      const action = String(button.getAttribute('data-mc-home-car-action') || '').trim();
      const active = action === 'favorite'
        ? favorites.has(slug)
        : action === 'notify'
          ? alerts.has(slug)
          : compare.has(slug);
      syncCarActionButtonState(button, active);
    });
  };

  const carComparePayload = (item) => ({
    slug: item?.slug || '',
    module: item?.module || 'cars',
    title: item?.title || '',
    price: item?.price || '',
    image_url: item?.image_url || '/finder/assets/img/placeholders/preview-square.svg',
    city: item?.city?.name || '',
    year: item?.details?.car?.year || '',
    mileage: item?.details?.car?.mileage ? `${item.details.car.mileage} mi` : '',
    fuel_type: item?.details?.car?.fuel_type || '',
    transmission: item?.details?.car?.transmission || '',
    detail_url: entryUrl(item),
  });

  const handleCombinedCarAction = (button) => {
    const slug = String(button.getAttribute('data-mc-slug') || '').trim();
    const action = String(button.getAttribute('data-mc-home-car-action') || '').trim();
    const title = String(button.getAttribute('data-mc-title') || 'Listing').trim();
    const payloadRaw = button.getAttribute('data-mc-item') || '';
    if (!slug || !action) return;

    if (action === 'favorite') {
      const slugs = readStoredSlugs(carActionsStorage.favorites);
      const exists = slugs.includes(slug);
      const next = exists ? slugs.filter((value) => value !== slug) : [...slugs, slug];
      writeStoredSlugs(carActionsStorage.favorites, next);
      const items = readStoredMap(carActionsStorage.favoriteItems);
      if (exists) {
        delete items[slug];
      } else if (payloadRaw) {
        try { items[slug] = JSON.parse(payloadRaw); } catch (_) {}
      }
      writeStoredMap(carActionsStorage.favoriteItems, items);
      syncCombinedCarActionStates();
      showCarActionToast(exists ? `${title} removed from favorites.` : `${title} saved to favorites.`);
      return;
    }

    if (action === 'notify') {
      const slugs = readStoredSlugs(carActionsStorage.alerts);
      const exists = slugs.includes(slug);
      const next = exists ? slugs.filter((value) => value !== slug) : [...slugs, slug];
      writeStoredSlugs(carActionsStorage.alerts, next);
      syncCombinedCarActionStates();
      showCarActionToast(exists ? `Alerts turned off for ${title}.` : `Alerts turned on for ${title}.`);
      return;
    }

    const slugs = readStoredSlugs(carActionsStorage.compare);
    const exists = slugs.includes(slug);
    const next = exists ? slugs.filter((value) => value !== slug) : [...slugs.filter((value) => value !== slug), slug].slice(-4);
    const items = readStoredMap(carActionsStorage.compareItems);
    if (exists) {
      delete items[slug];
    } else if (payloadRaw) {
      try { items[slug] = JSON.parse(payloadRaw); } catch (_) {}
    }
    writeStoredSlugs(carActionsStorage.compare, next);
    writeStoredMap(carActionsStorage.compareItems, items);
    syncCombinedCarActionStates();
    showCarActionToast(exists ? `${title} removed from compare.` : `${title} added to compare.`);
  };

  const bindCombinedCarActions = () => {
    if (document.body.dataset.mcHomeCombinedCarActionsBound === '1') return;
    document.body.dataset.mcHomeCombinedCarActionsBound = '1';
    document.body.addEventListener('click', (event) => {
      const button = event.target.closest('[data-mc-home-car-action]');
      if (!button) return;
      event.preventDefault();
      event.stopPropagation();
      handleCombinedCarAction(button);
    });
  };

  const buildCombinedSearchParams = (module, serviceValue, cityValue) => {
    const params = { per_page: '3' };
    const serviceSlug = slugify(serviceValue);

    if (module === 'contractors') {
      if (serviceSlug) params.category = serviceSlug;
      if (cityValue) params.q = cityValue;
      return params;
    }

    const q = [serviceValue, cityValue].filter(Boolean).join(' ').trim();
    if (q) params.q = q;
    return params;
  };

  const readCache = () => {
    const parseCache = (raw) => {
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      if (parsed.ts && parsed.data) {
        if (Date.now() - Number(parsed.ts) > CACHE_TTL_MS) return null;
        return parsed.data;
      }
      return parsed; // Backward compatibility with old cache shape.
    };

    try {
      const sessionData = parseCache(window.sessionStorage.getItem(CACHE_KEY));
      if (sessionData) return sessionData;

      const localData = parseCache(window.localStorage.getItem(CACHE_KEY));
      if (localData) return localData;

      return null;
    } catch (_) {
      return null;
    }
  };

  const writeCache = (payload) => {
    try {
      const packet = JSON.stringify({ ts: Date.now(), data: payload });
      window.sessionStorage.setItem(CACHE_KEY, packet);
      window.localStorage.setItem(CACHE_KEY, packet);
    } catch (_) {
      // Ignore storage failures.
    }
  };

  const initOrUpdateSwiper = (key, selector, options) => {
    const el = document.querySelector(selector);
    if (!el || typeof Swiper === 'undefined') return;

    if (swipers[key]) {
      swipers[key].update();
      return;
    }

    swipers[key] = new Swiper(selector, options);
  };

  const initAllSwipers = () => {
    initOrUpdateSwiper('topOffers', '#topOffersSwiper', {
      slidesPerView: 1,
      spaceBetween: 24,
      speed: 550,
      navigation: { prevEl: '#topOffersPrev', nextEl: '#topOffersNext' },
      breakpoints: {
        576: { slidesPerView: 2 },
        1200: { slidesPerView: 4 },
      },
    });

    initOrUpdateSwiper('cars', '#carsSwiper', {
      slidesPerView: 1,
      spaceBetween: 24,
      speed: 550,
      navigation: { prevEl: '#carsPrev', nextEl: '#carsNext' },
      breakpoints: {
        576: { slidesPerView: 2 },
        1200: { slidesPerView: 4 },
      },
    });

    initOrUpdateSwiper('homeProjects', '#homeProjectsSwiper', {
      slidesPerView: 1,
      spaceBetween: 24,
      speed: 550,
      navigation: { prevEl: '#homeProjectsPrev', nextEl: '#homeProjectsNext' },
      breakpoints: {
        768: { slidesPerView: 2 },
        1200: { slidesPerView: 3 },
      },
    });

    initOrUpdateSwiper('restaurantCities', '#combinedRestaurantCitiesSwiper', {
      slidesPerView: 1.1,
      spaceBetween: 24,
      speed: 550,
      navigation: { prevEl: '#combinedRestaurantCitiesPrev', nextEl: '#combinedRestaurantCitiesNext' },
      breakpoints: {
        576: { slidesPerView: 1.6 },
        768: { slidesPerView: 2.2 },
        1200: { slidesPerView: 3.2 },
      },
    });
  };

  const renderRealEstate = (items) => {
    const wrap = document.getElementById('realEstateOffers');
    if (!wrap) return;
    wrap.innerHTML = items.slice(0, 6).map((item, index) => `
      <div class="swiper-slide h-auto">
        <article class="card h-100">
          <img class="card-img-top module-card-img" src="${escapeHtml(item.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.title)}" loading="${index === 0 ? 'eager' : 'lazy'}" fetchpriority="${index === 0 ? 'high' : 'auto'}" decoding="async" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          <div class="card-body">
            <span class="badge text-bg-secondary mb-2">For ${item.price?.includes('/mo') ? 'rent' : 'sale'}</span>
            <h3 class="h5 mb-1">${escapeHtml(item.price || 'Price on request')}</h3>
            <a class="stretched-link text-body text-decoration-none" href="${entryUrl(item)}">${escapeHtml(item.title)}</a>
            <div class="fs-sm text-body-secondary mt-2">${escapeHtml(item.city?.name || 'City not set')}</div>
          </div>
        </article>
      </div>
    `).join('');
  };

  const renderContractorNear = (items) => {
    const wrap = document.getElementById('contractorNearList');
    if (!wrap) return;
    wrap.innerHTML = items.slice(0, 6).map((item) => `
      <div class="col-md-6">
        <article class="card border-0 bg-body-tertiary h-100">
          <div class="card-body d-flex gap-3 align-items-center">
            <img src="${escapeHtml(item.image_url || '/finder/assets/img/listings/contractors/04.jpg')}" alt="${escapeHtml(item.title)}" width="96" height="96" loading="lazy" decoding="async" class="rounded-3 object-fit-cover" onerror="this.onerror=null;this.src='/finder/assets/img/listings/contractors/04.jpg';">
            <div>
              <a class="stretched-link text-decoration-none" href="${entryUrl(item)}"><h3 class="h5 mb-1">${escapeHtml(item.title)}</h3></a>
              <div class="fs-sm text-warning mb-1"><i class="fi-star-filled"></i> ${Number(item.rating || 0).toFixed(1)} (${Number(item.reviews_count || 0)})</div>
              <div class="fw-medium">${escapeHtml(item.price || '')}</div>
            </div>
          </div>
        </article>
      </div>
    `).join('');
  };

  const renderRestaurantCities = (items) => {
    const wrap = document.getElementById('combinedRestaurantCitiesGrid');
    if (!wrap) return;

    const cards = (Array.isArray(items) ? items : []).slice(0, 8);
    if (!cards.length) {
      wrap.innerHTML = `
        <div class="swiper-slide h-auto">
          <article class="card border-0 shadow-sm h-100">
            <div class="card-body py-5 text-center text-body-secondary">
              No restaurant listings available yet.
            </div>
          </article>
        </div>
      `;
      return;
    }

    wrap.innerHTML = cards.map((item, index) => {
      const city = item?.city?.name || 'City not set';
      const rating = Number(item?.rating || 0);
      const reviews = Number(item?.reviews_count || 0);
      const price = String(item?.price || '').trim() || 'Price on request';
      return `
        <div class="swiper-slide h-auto">
          <article class="card border-0 shadow-sm h-100">
            <img class="card-img-top object-fit-cover" src="${escapeHtml(item?.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item?.title || city)}" style="height: 260px;" loading="${index === 0 ? 'eager' : 'lazy'}" fetchpriority="${index === 0 ? 'high' : 'auto'}" decoding="async" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
            <div class="card-body">
              <div class="text-body-secondary small">${escapeHtml(city)}</div>
              <h3 class="h5 mb-1">
                <a class="stretched-link text-decoration-none" href="${entryUrl(item)}">${escapeHtml(item?.title || 'Restaurant listing')}</a>
              </h3>
              <div class="small text-body-secondary">${escapeHtml(rating.toFixed(1))} rating${reviews ? ` - (${reviews})` : ''} - ${escapeHtml(price)}</div>
            </div>
          </article>
        </div>
      `;
    }).join('');
  };

  const renderCars = (items) => {
    const wrap = document.getElementById('latestCarsGrid');
    if (!wrap) return;
    wrap.innerHTML = items.slice(0, 6).map((item, index) => `
      <div class="swiper-slide h-auto">
        <article class="card h-100 hover-effect-scale bg-body-tertiary border-0">
          <div class="card-img-top position-relative overflow-hidden">
          <img class="card-img-top module-card-img" src="${escapeHtml(item.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.title)}" loading="${index === 0 ? 'eager' : 'lazy'}" fetchpriority="${index === 0 ? 'high' : 'auto'}" decoding="async" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          ${(() => {
            const badges = [];
            const conditionRaw = String(item?.details?.car?.condition || '').toLowerCase();
            const stock = conditionRaw.includes('used') ? 'Used' : (conditionRaw.includes('new') ? 'New' : '');
            const features = Array.isArray(item?.features) ? item.features : [];
            const isVerified = features.some((f) => String(f || '').toLowerCase().includes('verified'));
            if (isVerified) badges.push('<span class="badge text-bg-info d-inline-flex align-items-center">Verified<i class="fi-shield ms-1"></i></span>');
            if (stock) badges.push(`<span class="badge ${stock === 'New' ? 'text-bg-primary' : 'text-bg-warning'}">${escapeHtml(stock)}</span>`);
            if (!badges.length) return '';
            return `<div class="d-flex flex-column gap-2 align-items-start position-absolute top-0 start-0 z-1 pt-1 pt-sm-0 ps-1 ps-sm-0 mt-2 mt-sm-3 ms-2 ms-sm-3" style="pointer-events:none">${badges.join('')}</div>`;
          })()}
          </div>
          <div class="card-body pb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fs-xs text-body-secondary me-3">Recently added</div>
              <div class="d-flex gap-2 position-relative z-2">
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary animate-pulse rounded-circle" data-mc-home-car-action="favorite" data-mc-slug="${escapeHtml(item.slug || '')}" data-mc-title="${escapeHtml(item.title || 'Listing')}" data-mc-item="${escapeHtml(JSON.stringify(carComparePayload(item)))}" aria-label="Add to wishlist">
                  <i class="fi-heart animate-target fs-sm"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary animate-shake rounded-circle" data-mc-home-car-action="notify" data-mc-slug="${escapeHtml(item.slug || '')}" data-mc-title="${escapeHtml(item.title || 'Listing')}" data-mc-item="${escapeHtml(JSON.stringify(carComparePayload(item)))}" aria-label="Notify">
                  <i class="fi-bell animate-target fs-sm"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary animate-rotate rounded-circle" data-mc-home-car-action="compare" data-mc-slug="${escapeHtml(item.slug || '')}" data-mc-title="${escapeHtml(item.title || 'Listing')}" data-mc-item="${escapeHtml(JSON.stringify(carComparePayload(item)))}" aria-label="Compare">
                  <i class="fi-repeat animate-target fs-sm"></i>
                </button>
              </div>
            </div>
            <h3 class="h6 mb-2">
              <a class="hover-effect-underline stretched-link me-1 text-decoration-none" href="${entryUrl(item)}">${escapeHtml(item.title)}</a>
              ${item?.details?.car?.year ? `<span class="fs-xs fw-normal text-body-secondary">(${escapeHtml(item.details.car.year)})</span>` : ''}
            </h3>
            <div class="h6 mb-0">${escapeHtml(item.price || '')}</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-4">
            <div class="border-top pt-3">
              <div class="row row-cols-2 g-2 fs-sm">
                <div class="col d-flex align-items-center gap-2"><i class="fi-map-pin"></i>${escapeHtml(item.city?.name || 'Location')}</div>
                <div class="col d-flex align-items-center gap-2"><i class="fi-tachometer"></i>${escapeHtml(item?.details?.car?.mileage ? `${item.details.car.mileage} mi` : 'N/A')}</div>
                <div class="col d-flex align-items-center gap-2"><i class="fi-gas-pump"></i>${escapeHtml(item?.details?.car?.fuel_type || 'N/A')}</div>
                <div class="col d-flex align-items-center gap-2"><i class="fi-gearbox"></i>${escapeHtml(item?.details?.car?.transmission || 'N/A')}</div>
              </div>
            </div>
          </div>
        </article>
      </div>
    `).join('');
  };

  const renderContractorHome = (items) => {
    const wrap = document.getElementById('contractorHomeList');
    if (!wrap) return;
    wrap.innerHTML = items.slice(0, 6).map((item, index) => `
      <div class="swiper-slide h-auto">
        <article class="card h-100">
          <img class="card-img-top module-card-img" src="${escapeHtml(item.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.title)}" loading="${index === 0 ? 'eager' : 'lazy'}" fetchpriority="${index === 0 ? 'high' : 'auto'}" decoding="async" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          <div class="card-body">
            <a class="stretched-link text-decoration-none" href="${entryUrl(item)}"><h3 class="h4 mb-2">${escapeHtml(item.title)}</h3></a>
            <div class="d-flex justify-content-between align-items-center">
              <div><i class="fi-star-filled text-warning"></i> ${Number(item.rating || 0).toFixed(1)} (${Number(item.reviews_count || 0)})</div>
              <div class="fw-medium">${escapeHtml(item.price || '')}</div>
            </div>
          </div>
        </article>
      </div>
    `).join('');
  };

  const renderContractorCategories = (items) => {
    const wrap = document.getElementById('contractorCategoriesGrid');
    if (!wrap) return;

    const iconMap = {
      remodeling: 'fi-home',
      renovation: 'fi-home',
      interior: 'fi-armchair',
      design: 'fi-armchair',
      landscaping: 'fi-tree',
      roofing: 'fi-home',
      handyman: 'fi-tool',
      plumbing: 'fi-tool',
      painting: 'fi-pen-tool',
      cleaning: 'fi-shower',
      electrical: 'fi-power',
      flooring: 'fi-home',
      hvac: 'fi-settings',
      lawn: 'fi-scissors',
      service: 'fi-briefcase',
      deck: 'fi-home',
      porch: 'fi-home',
      addition: 'fi-home',
    };

    const cards = (Array.isArray(items) ? items : [])
      .filter((item) => Number(item?.count || 0) > 0)
      .sort((a, b) => Number(b?.count || 0) - Number(a?.count || 0))
      .slice(0, 8);

    if (!cards.length) {
      wrap.innerHTML = `
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body py-5 text-center text-body-secondary">
              No contractor categories available yet.
            </div>
          </div>
        </div>
      `;
      return;
    }

    wrap.innerHTML = cards.map((item) => {
      const slug = String(item?.slug || '').trim();
      const name = String(item?.name || 'Category').trim();
      const count = Number(item?.count || 0);
      const icon = Object.entries(iconMap).find(([token]) => slug.includes(token) || name.toLowerCase().includes(token))?.[1] || 'fi-briefcase';
      return `
        <div class="col-sm-6 col-xl-3">
          <a class="card h-100 text-decoration-none border shadow-sm hover-effect-scale" href="/listings/contractors?category=${encodeURIComponent(slug)}">
            <div class="card-body d-flex align-items-center gap-3 p-4">
              <span class="btn btn-icon btn-lg btn-outline-secondary rounded-circle pe-none flex-shrink-0">
                <i class="${icon} fs-4"></i>
              </span>
              <span class="d-block">
                <span class="d-block h5 mb-1 text-body-emphasis">${escapeHtml(name)}</span>
                <span class="d-block fs-sm text-body-secondary">${escapeHtml(String(count))} listing${count === 1 ? '' : 's'} available</span>
              </span>
            </div>
          </a>
        </div>
      `;
    }).join('');
  };

  const renderLoadingSkeletons = () => {
    const make = (count) =>
      Array.from({ length: count })
        .map(() => `<div class="swiper-slide h-auto"><div class="placeholder-glow"><div class="placeholder col-12 rounded-4" style="height: 220px"></div></div></div>`)
        .join('');
    const makeGrid = (count, colClass) =>
      Array.from({ length: count })
        .map(() => `<div class="${colClass}"><div class="placeholder-glow"><div class="placeholder col-12 rounded-4" style="height: 110px"></div></div></div>`)
        .join('');

    const set = (id, html) => {
      const el = document.getElementById(id);
      if (el && !el.children.length) el.innerHTML = html;
    };

    set('contractorCategoriesGrid', makeGrid(8, 'col-sm-6 col-xl-3'));
    set('realEstateOffers', make(4));
    set('contractorNearList', makeGrid(4, 'col-md-6'));
    set('latestCarsGrid', make(4));
    set('contractorHomeList', make(3));
  };

  const renderSearchResults = (items) => {
    const wrap = document.getElementById('combinedSearchResults');
    const grid = document.getElementById('combinedSearchResultsGrid');
    const viewAll = document.getElementById('combinedSearchViewAll');
    const meta = document.getElementById('combinedSearchMeta');
    const summary = document.getElementById('combinedSearchSummary');
    if (!wrap || !grid) return;

    const serviceValue = (document.getElementById('serviceQuery')?.value || '').trim();
    const cityValue = (document.getElementById('cityZip')?.value || '').trim();

    if (meta) {
      meta.innerHTML = [
        serviceValue ? `<span class="combined-search-chip"><i class="fi-search"></i>${escapeHtml(serviceValue)}</span>` : '',
        cityValue ? `<span class="combined-search-chip"><i class="fi-map-pin"></i>${escapeHtml(cityValue)}</span>` : '',
      ].filter(Boolean).join('');
    }

    if (!items.length) {
      wrap.classList.remove('d-none');
      if (summary) summary.textContent = 'No listings matched this search yet. Try another service or city.';
      grid.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">No listings found for this search.</div></div>';
      return;
    }

    if (viewAll) {
      const first = items[0];
      if (first?.module === 'contractors') {
        const params = new URLSearchParams();
        const serviceSlug = slugify(serviceValue);
        if (serviceSlug) params.set('category', serviceSlug);
        viewAll.setAttribute('href', `/listings/contractors${params.toString() ? `?${params.toString()}` : ''}`);
      } else {
        viewAll.setAttribute('href', `/listings/${encodeURIComponent(first.module)}?q=${encodeURIComponent(serviceValue)}`);
      }
    }

    wrap.classList.remove('d-none');
    if (summary) {
      summary.textContent = `Showing ${items.length} result${items.length === 1 ? '' : 's'} for your current search.`;
    }
    grid.innerHTML = items.map((item) => `
      <div class="col-sm-6 col-xl-3">
        <article class="card h-100">
          <img class="card-img-top module-card-img" src="${escapeHtml(item.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.title)}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          <div class="card-body">
            <span class="badge text-bg-dark mb-2">${escapeHtml(item.module)}</span>
            <a class="stretched-link text-decoration-none" href="${entryUrl(item)}"><h3 class="h6 mb-1">${escapeHtml(item.title)}</h3></a>
            <div class="fs-sm text-body-secondary">${escapeHtml(item.city?.name || '')}</div>
          </div>
        </article>
      </div>
    `).join('');
  };

  const renderAll = (datasets) => {
    renderRealEstate(datasets.realEstate || []);
    renderContractorNear(datasets.contractorsPopular || []);
    renderCars(datasets.cars || []);
    renderContractorHome(datasets.contractorsLatest || []);
    renderRestaurantCities(datasets.restaurants || []);
    renderContractorCategories(datasets.contractorCategories || []);
    syncCombinedCarActionStates();
    initAllSwipers();
  };

  const bootstrapPage = async () => {
    bindCombinedCarActions();
    const cached = readCache();
    if (cached) {
      renderAll(cached);
    } else {
      renderLoadingSkeletons();
      initAllSwipers();
    }

    try {
      const [realEstate, contractorPayload, cars, restaurants] = await Promise.all([
        fetchListings('real-estate', { per_page: '6' }),
        fetchListingsPayload('contractors', { per_page: '8' }),
        fetchListings('cars', { per_page: '6' }),
        fetchListings('restaurants', { per_page: '8' }),
      ]);

      const contractors = Array.isArray(contractorPayload?.data) ? contractorPayload.data : [];
      const contractorCategories = Array.isArray(contractorPayload?.filters?.categories) ? contractorPayload.filters.categories : [];
      const byRatingDesc = (a, b) => Number(b?.rating || 0) - Number(a?.rating || 0);
      const datasets = {
        realEstate: [...realEstate].sort(byRatingDesc),
        realEstateLatest: realEstate,
        contractorsPopular: [...contractors].sort(byRatingDesc),
        contractorsLatest: contractors,
        contractorCategories,
        cars,
        restaurants: [...restaurants].sort(byRatingDesc),
      };
      renderAll(datasets);
      writeCache(datasets);
    } catch (_) {
      // Keep fallback rendered state.
    }
  };

  const searchForm = document.getElementById('combinedSearchForm');
  if (searchForm) {
    searchForm.setAttribute('data-mc-no-loader', '1');
    searchForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
        window.__MC_HIDE_PAGE_LOADER__();
      }
      const serviceValue = (document.getElementById('serviceQuery')?.value || '').trim();
      const cityValue = (document.getElementById('cityZip')?.value || '').trim();
      const q = [serviceValue, cityValue].filter(Boolean).join(' ').trim();

      if (!q) {
        renderSearchResults([]);
        return;
      }

      try {
        const modules = ['contractors', 'real-estate', 'cars', 'restaurants'];
        const results = await Promise.allSettled(
          modules.map((module) => fetchListings(module, buildCombinedSearchParams(module, serviceValue, cityValue)))
        );

        const merged = results
          .filter((result) => result.status === 'fulfilled')
          .flatMap((result) => result.value)
          .slice(0, 8);

        renderSearchResults(merged);
        document.getElementById('combinedSearchResults')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } finally {
        if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
          window.__MC_HIDE_PAGE_LOADER__();
        }
      }
    });
  }

  bootstrapPage();
})();
