(() => {
  window.__MC_ENTRY_DYNAMIC_VERSION__ = '2026-03-27-r5';
  try { console.log('[Monaclick] entry dynamic', window.__MC_ENTRY_DYNAMIC_VERSION__); } catch (e) {}

  const path = window.location.pathname;
  if (!path.startsWith('/entry/')) return;

  const moduleFromPath = path.split('/')[2] || 'contractors';
  const allowedModules = new Set(['contractors', 'real-estate', 'cars', 'restaurants']);
  const selectedModule = allowedModules.has(moduleFromPath) ? moduleFromPath : 'contractors';
  const params = new URLSearchParams(window.location.search);

  const setReady = () => {
    if (document.body?.classList.contains('monaclick-entry-shell')) {
      document.body.setAttribute('data-entry-ready', '1');
    }
    if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
      window.__MC_HIDE_PAGE_LOADER__();
    }
  };

  const ensureEntryLoaderStyles = () => {
    const styleId = 'mc-entry-loader-style';
    if (document.getElementById(styleId)) return;
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
      .mc-entry-loader-wrap{
        min-height: clamp(320px, 58vh, 560px);
        display:flex;
        align-items:center;
        justify-content:center;
        padding:24px 0;
      }
      .mc-entry-loader-card{
        width:72px;
        height:72px;
        display:flex;
        align-items:center;
        justify-content:center;
      }
      .mc-entry-loader-spinner{
        position:relative;
        width:52px;
        height:52px;
        filter:drop-shadow(0 6px 18px rgba(249, 115, 22, .18));
      }
      .mc-entry-inline-loader{
        width:28px;
        height:28px;
        position:relative;
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
      [data-mc-car-features="1"].text-body-secondary.small{
        width:28px;
        height:28px;
        font-size:0;
        line-height:0;
        color:transparent !important;
        position:relative;
        overflow:hidden;
      }
      .mc-entry-loader-spinner::before,
      .mc-entry-loader-spinner::after,
      .mc-entry-inline-loader::before,
      .mc-entry-inline-loader::after,
      .mc-inline-loading-text::before,
      .mc-inline-loading-text::after,
      [data-mc-car-features="1"].text-body-secondary.small::before,
      [data-mc-car-features="1"].text-body-secondary.small::after{
        content:"";
        position:absolute;
        inset:0;
        border-radius:50%;
      }
      .mc-entry-loader-spinner::before{
        border:4px solid rgba(15, 23, 42, .10);
        border-top-color:#fd7e14;
        border-right-color:#f59e0b;
        animation:mc-entry-loader-spin .9s linear infinite;
      }
      .mc-entry-loader-spinner::after{
        inset:9px;
        border:4px solid rgba(249, 115, 22, .20);
        border-bottom-color:#f97316;
        border-left-color:#fb923c;
        animation:mc-entry-loader-spin-reverse .72s linear infinite;
      }
      .mc-entry-inline-loader::before{
        border:3px solid rgba(15, 23, 42, .10);
        border-top-color:#fd7e14;
        border-right-color:#f59e0b;
        animation:mc-entry-loader-spin .9s linear infinite;
      }
      .mc-entry-inline-loader::after{
        inset:6px;
        border:3px solid rgba(249, 115, 22, .20);
        border-bottom-color:#f97316;
        border-left-color:#fb923c;
        animation:mc-entry-loader-spin-reverse .72s linear infinite;
      }
      .mc-inline-loading-text::before{
        border:3px solid rgba(15, 23, 42, .10);
        border-top-color:#fd7e14;
        border-right-color:#f59e0b;
        animation:mc-entry-loader-spin .9s linear infinite;
      }
      .mc-inline-loading-text::after{
        inset:6px;
        border:3px solid rgba(249, 115, 22, .20);
        border-bottom-color:#f97316;
        border-left-color:#fb923c;
        animation:mc-entry-loader-spin-reverse .72s linear infinite;
      }
      [data-mc-car-features="1"].text-body-secondary.small::before{
        border:3px solid rgba(15, 23, 42, .10);
        border-top-color:#fd7e14;
        border-right-color:#f59e0b;
        animation:mc-entry-loader-spin .9s linear infinite;
      }
      [data-mc-car-features="1"].text-body-secondary.small::after{
        inset:6px;
        border:3px solid rgba(249, 115, 22, .20);
        border-bottom-color:#f97316;
        border-left-color:#fb923c;
        animation:mc-entry-loader-spin-reverse .72s linear infinite;
      }
      @keyframes mc-entry-loader-spin{
        to{transform:rotate(360deg)}
      }
      @keyframes mc-entry-loader-spin-reverse{
        to{transform:rotate(-360deg)}
      }
    `;
    document.head.appendChild(style);
  };

  const ensureEntryLightboxStyles = () => {
    const styleId = 'mc-entry-lightbox-style';
    if (document.getElementById(styleId)) return;
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
      .glightbox-container .gloader{
        display:none !important;
        opacity:0 !important;
        visibility:hidden !important;
      }
    `;
    document.head.appendChild(style);
  };

  const renderEntryLoader = () => {
    ensureEntryLoaderStyles();
    container.innerHTML = `
      <div class="mc-entry-loader-wrap" aria-live="polite" aria-busy="true">
        <div class="mc-entry-loader-card" role="status" aria-live="polite" aria-busy="true">
          <div class="mc-entry-loader-spinner" aria-hidden="true"></div>
        </div>
      </div>
    `;
    try { container.style.opacity = '1'; } catch (e) {}
  };

  const wireDeadPlaceholderLinks = () => {
    const target = `/listings/${selectedModule}`;
    document.querySelectorAll('a[href="#!"], a[href="#"]').forEach((link) => {
      const isUiToggle = link.hasAttribute('data-bs-toggle') || link.getAttribute('role') === 'button';
      if (isUiToggle) return;
      link.setAttribute('href', target);
    });
  };

  wireDeadPlaceholderLinks();

  const container = document.querySelector('main.content-wrapper > .container');
  if (!container) {
    setReady();
    return;
  }

  // Prevent the static template's default content from flashing before we render fetched data.
  try {
    container.style.opacity = '0';
    container.style.transition = 'opacity 140ms ease';
    container.innerHTML = '';
  } catch (e) {
    // ignore
  }

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const formatDate = (iso) => {
    if (!iso) return 'N/A';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return 'N/A';
    return d.toLocaleString();
  };

  const moduleLabel = (value) => ({
    contractors: 'Contractors',
    'real-estate': 'Real Estate',
    cars: 'Cars',
    restaurants: 'Restaurants',
  }[value] || 'Listings');

  const formatTime = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    if (/[ap]m/i.test(raw)) return raw.replace(/\s+/g, ' ').toUpperCase();
    const m = raw.match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return raw;
    let h = Number(m[1]);
    const min = m[2];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    return `${String(h).padStart(2, '0')}:${min}\u00A0${ampm}`;
  };

  const formatRange = (from, to) => {
    const f = formatTime(from);
    const t = formatTime(to);
    if (f && t) return `${f}\u00A0-\u00A0${t}`;
    return f || t || '';
  };

  const numberFormatter = (() => {
    try {
      return new Intl.NumberFormat('en-US');
    } catch (e) {
      return null;
    }
  })();

  const formatNumber = (value) => {
    if (value === null || typeof value === 'undefined') return '';
    const raw = String(value).trim();
    if (!raw) return '';
    const cleaned = raw.replace(/,/g, '');
    if (!/^\d+(\.\d+)?$/.test(cleaned)) return raw;
    const n = Number(cleaned);
    if (!Number.isFinite(n)) return raw;
    return numberFormatter ? numberFormatter.format(n) : raw;
  };

  const reviewStats = (item) => ({
    rating: Number(item?.rating || 0),
    reviews: Number(item?.reviews_count || 0),
  });

  const formatMoneyDisplay = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const m = raw.match(/^(\$?\s*)?(from\s+)?([\d,]+(?:\.\d+)?)$/i);
    if (!m) return raw;
    const currency = String(m[1] || '').replace(/\s+/g, '');
    const fromPrefix = m[2] ? 'From ' : '';
    const formatted = formatNumber(m[3]);
    if (!formatted) return raw;
    return `${currency || '$'}${fromPrefix}${formatted}`.trim();
  };

  const capitalizeFirstLetter = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    return raw.charAt(0).toUpperCase() + raw.slice(1);
  };

  const formatContractorAddress = (item) => {
    const contractor = item?.details?.contractor || {};
    const address = String(contractor.address || '').trim();
    const city = String(item?.city?.name || '').trim();
    const state = String(contractor.state || '').trim();
    const zip = String(contractor.zip_code || '').trim();

    if (!address) {
      return [city, state, zip].filter(Boolean).join(', ');
    }

    const addressLower = address.toLowerCase();
    const hasCity = city && addressLower.includes(city.toLowerCase());
    const hasState = state && addressLower.includes(state.toLowerCase());
    const hasZip = zip && address.includes(zip);

    return [
      address,
      !hasCity ? city : '',
      !hasState ? state : '',
      !hasZip ? zip : '',
    ].filter(Boolean).join(', ');
  };

  const formatHours = (hours) => {
    if (!hours) return '';

    if (typeof hours === 'string') {
      const raw = hours.trim();
      if (!raw) return '';
      return raw
        .replace(/\bMon\s*-\s*Fri\b/gi, 'Mon - Fri')
        .split(/\s*,\s*/g)
        .map((p) => p.trim())
        .filter(Boolean)
        .join('\n')
        .replace(/\sAM\b/g, '\u00A0AM')
        .replace(/\sPM\b/g, '\u00A0PM')
        .replace(/\s-\s/g, '\u00A0-\u00A0');
    }

    if (typeof hours !== 'object') return '';

    const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    const dayShort = {
      monday: 'Mon',
      tuesday: 'Tue',
      wednesday: 'Wed',
      thursday: 'Thu',
      friday: 'Fri',
      saturday: 'Sat',
      sunday: 'Sun',
    };

    const valueToRange = (value) => {
      if (!value) return '';
      if (typeof value === 'string') return formatHours(value);
      if (typeof value === 'boolean') return value ? 'Open' : '';
      if (typeof value === 'object') {
        if (value.enabled === false) return '';
        const from = value.from ?? value.start ?? value.opens ?? '';
        const to = value.to ?? value.end ?? value.closes ?? '';
        return formatRange(from, to);
      }
      return String(value).trim();
    };

    const keys = Object.keys(hours).map((k) => String(k).trim().toLowerCase());
    const hasDayKeys = keys.some((k) => dayOrder.includes(k));

    if (!hasDayKeys) {
      return Object.entries(hours)
        .map(([k, v]) => {
          const label = String(k ?? '').trim();
          const range = valueToRange(v);
          if (!label || !range) return '';
          return `${label}: ${range}`;
        })
        .filter(Boolean)
        .join('\n');
    }

    const dayRanges = dayOrder.map((day) => ({
      day,
      range: valueToRange(hours[day]),
    })).filter((x) => x.range);

    if (!dayRanges.length) return '';

    const groups = [];
    let start = null;
    let end = null;
    let currentRange = null;

    dayRanges.forEach((item) => {
      if (currentRange === null) {
        start = item.day;
        end = item.day;
        currentRange = item.range;
        return;
      }

      const prevIdx = dayOrder.indexOf(end);
      const nextIdx = dayOrder.indexOf(item.day);
      const contiguous = nextIdx === prevIdx + 1;

      if (contiguous && item.range === currentRange) {
        end = item.day;
        return;
      }

      groups.push({ start, end, range: currentRange });
      start = item.day;
      end = item.day;
      currentRange = item.range;
    });

    groups.push({ start, end, range: currentRange });

    return groups
      .map((g) => {
        const left = g.start === g.end
          ? `${dayShort[g.start]}`
          : `${dayShort[g.start]}\u00A0-\u00A0${dayShort[g.end]}`;
        return `${left}: ${g.range}`;
      })
      .join('\n');
  };

  const normalizeRestaurantExcerpt = (item) => {
    if (item.module !== 'restaurants') return item.excerpt || '';
    // Server now returns a clean excerpt for v1 restaurant payloads, but keep a fallback for older data.
    const raw = String(item.excerpt || '').trim();
    if (!raw.startsWith('{') || !raw.endsWith('}')) return raw;
    try {
      const meta = JSON.parse(raw);
      if (meta && meta._mc_restaurant_v1) return '';
    } catch (e) {
      // ignore
    }
    return raw;
  };

  const titleCaseToken = (token) => {
    const upper = String(token || '').toUpperCase();
    const keepUpper = new Set(['ABS', 'MPG', 'GPS', 'USB', 'A/C', 'AC', 'AWD', 'FWD', 'RWD', '4WD', '2WD', 'LED']);
    if (keepUpper.has(upper)) return upper === 'AC' ? 'A/C' : upper;
    return upper.slice(0, 1) + upper.slice(1).toLowerCase();
  };

  const humanizeFeature = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const cleaned = raw.replace(/^service:\s*/i, '').trim();
    if (!cleaned) return '';
    if (/[A-Z]/.test(cleaned) && cleaned.includes(' ')) return cleaned;
    return cleaned
      .replaceAll('_', ' ')
      .replaceAll('-', ' ')
      .split(/\s+/g)
      .filter(Boolean)
      .map(titleCaseToken)
      .join(' ');
  };

  const humanizeListLabel = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    if (/[A-Z]/.test(raw) && raw.includes(' ')) return raw;
    return raw
      .replaceAll('_', ' ')
      .split(/\s+/g)
      .filter(Boolean)
      .map((word) => word
        .split('-')
        .filter(Boolean)
        .map(titleCaseToken)
        .join('-'))
      .join(' ');
  };

  const normalizedFeatures = (item) => {
    const base = Array.isArray(item?.features) ? item.features : [];
    const carWizard =
      item?.module === 'cars' && Array.isArray(item?.details?.car?.features)
        ? item.details.car.features
        : [];

    const values = [...base, ...carWizard]
      .map((f) => String(f ?? '').trim())
      .filter((f) => !!f && !/^service:\s*/i.test(f) && !/^promo-package:/i.test(f) && !/^promo-service:/i.test(f));

    const seen = new Set();
    return values.filter((f) => {
      const key = f.toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  };

  const renderFeatures = (item) => {
    const features = normalizedFeatures(item);
    if (!features.length) return '';

    if (item?.module === 'real-estate') {
      const priceFeatureKeys = new Set([
        'negotiable',
        'negotiated',
        'no credit',
        'no-credit',
        'agent friendly',
        'agent-friendly',
        'exchange',
      ]);

      const priceBadges = [];
      const amenityBadges = [];

      features.forEach((feature) => {
        const label = humanizeFeature(feature);
        if (!label) return;
        const key = String(feature || '').trim().toLowerCase().replace(/[_\s]+/g, '-');
        const badge = `<span class="badge bg-body-secondary text-body">${escapeHtml(label)}</span>`;
        if (priceFeatureKeys.has(key)) {
          priceBadges.push(badge);
        } else {
          amenityBadges.push(badge);
        }
      });

      const sections = [];
      if (item.price || priceBadges.length) {
        sections.push(`
          <div class="mb-4">
            <h2 class="h4 mb-3">Price</h2>
            <div class="row g-3 mb-3">
              <div class="col-12 col-sm-6 col-lg-3">
                <div class="border rounded p-3 h-100">
                  <div class="text-body-secondary small">Price</div>
                  <div class="fw-semibold">${escapeHtml(item.price || 'Price on request')}</div>
                </div>
              </div>
            </div>
            ${priceBadges.length ? `<div class="d-flex flex-wrap gap-2">${priceBadges.join('')}</div>` : ''}
          </div>
        `);
      }
      if (amenityBadges.length) {
        sections.push(`
          <div>
            <h2 class="h4 mb-3">Amenities</h2>
            <div class="d-flex flex-wrap gap-2">${amenityBadges.join('')}</div>
          </div>
        `);
      }

      if (!sections.length) return '';

      return `
        <section data-mc-features="1" class="pb-sm-2 pb-lg-3 mb-5">
          ${sections.join('')}
        </section>
      `;
    }

    const badges = features
      .map(humanizeFeature)
      .filter(Boolean)
      .map((label) => `<span class="badge bg-body-secondary text-body">${escapeHtml(label)}</span>`)
      .join('');

    if (!badges) return '';

    return `
      <section data-mc-features="1" class="pb-sm-2 pb-lg-3 mb-5">
        <h2 class="h4 mb-3">Features</h2>
        <div class="d-flex flex-wrap gap-2">${badges}</div>
      </section>
    `;
  };

  const getCarFeatureGroupMap = async () => {
    const cacheKey = 'mc:car-feature-group-map:v1';
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (parsed && typeof parsed === 'object') return parsed;
      }
    } catch (e) {
      // ignore
    }

    try {
      const res = await fetch('/add-car', { credentials: 'same-origin' });
      if (!res.ok) return {};
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const acc = doc.getElementById('features');
      if (!acc) return {};

      const map = {};
      acc.querySelectorAll('.accordion-item').forEach((item) => {
        const heading = item.querySelector('.accordion-header button, .accordion-header');
        const groupRaw = (heading?.textContent || '').replace(/\s+/g, ' ').trim();
        const group = groupRaw || 'Other';
        item.querySelectorAll('label.form-check-label[for]').forEach((label) => {
          const txt = (label.textContent || '').replace(/\s+/g, ' ').trim();
          if (!txt) return;
          map[txt] = group;
        });
      });

      try {
        sessionStorage.setItem(cacheKey, JSON.stringify(map));
      } catch (e) {
        // ignore
      }

      return map;
    } catch (e) {
      return {};
    }
  };

  const enhanceCarFeaturesSection = async (item) => {
    if (item.module !== 'cars') return;
    const section = container.querySelector('section[data-mc-features="1"]');
    if (!section) return;

    const features = normalizedFeatures(item).map(humanizeFeature).filter(Boolean);
    if (!features.length) return;

    const map = await getCarFeatureGroupMap();
    const groups = {};
    features.forEach((label) => {
      const key = String(label || '').trim();
      const group = String(map[key] || '').trim() || 'Other';
      if (!groups[group]) groups[group] = [];
      groups[group].push(key);
    });

    const order = ['Exterior', 'Interior', 'Safety', 'Other'];
    const groupNames = Array.from(new Set([...order, ...Object.keys(groups)]));

    const renderGroup = (name) => {
      const items = groups[name] || [];
      if (!items.length) return '';
      const badges = items
        .map((txt) => `<span class="badge bg-body-secondary text-body">${escapeHtml(txt)}</span>`)
        .join('');
      return `
        <div class="mb-3">
          <h3 class="h6 mb-2">${escapeHtml(name)}</h3>
          <div class="d-flex flex-wrap gap-2">${badges}</div>
        </div>
      `;
    };

    const body = groupNames.map(renderGroup).filter(Boolean).join('');
    if (!body) return;

    section.innerHTML = `
      <h2 class="h4 mb-3">Features</h2>
      ${body}
    `;
  };

  // Some deployed templates/JS variants don't include ${renderFeatures(item)} in the main HTML string.
  // Ensure the Features section appears right after the Details section whenever features exist.
  const ensureFeaturesAfterDetails = (item) => {
    const features = normalizedFeatures(item);
    if (!features.length) return;
    if (!container) return;
    if (container.querySelector('[data-mc-features="1"]')) return;

    const detailsHeading = Array.from(container.querySelectorAll('h2.h4'))
      .find((h) => (h.textContent || '').trim().toLowerCase() === 'details');
    const detailsSection = detailsHeading ? detailsHeading.closest('section') : null;
    if (!detailsSection) return;

    const html = renderFeatures(item);
    if (!html.trim()) return;

    detailsSection.insertAdjacentHTML('afterend', html);
  };

  const detailPairs = (item) => {
    const pairs = [];
    const add = (label, value, opts = {}) => {
      const v = String(value ?? '').trim();
      if (!v || v.toLowerCase() === 'n/a') return;
      pairs.push({ label, value: v, preLine: !!opts.preLine, section: opts.section || 'Details' });
    };

    add('Category', (item.module === 'real-estate' ? (item.details?.property?.selected_type || '') : '') || item.category?.name || '');
    add('City', item.city?.name || '');
    if (item.module !== 'real-estate') add('Price', formatMoneyDisplay(item.price || ''));
    if (item.module === 'cars') {
      const stats = reviewStats(item);
      add('Rating', `${stats.rating.toFixed(1)} (${stats.reviews})`);
    }

    if (item.module === 'contractors' && item.details?.contractor) {
      const d = item.details.contractor;
      add('Address', formatContractorAddress(item));
      add('State', d.state || '');
      add('Service Area', d.service_area || '');
      add('License', d.license_number || '');
      add('Verified', d.is_verified ? 'Yes' : 'No');
      const hours = formatHours(d.business_hours);
      if (hours) add('Hours', hours, { preLine: true });
    }

    if (item.module === 'real-estate' && item.details?.property) {
      const d = item.details.property;
      add('Type', d.selected_type || d.property_type || '');
      add('Listing', d.listing_type ? d.listing_type.charAt(0).toUpperCase() + d.listing_type.slice(1) : '');
      add('Bedrooms', d.bedrooms ?? '');
      add('Bathrooms', d.bathrooms ?? '');
      add('Total floors', d.floors_total ?? '');
      add('Floor', d.floor ?? '');
      add('Area (sq.ft)', d.area_sqft !== null && typeof d.area_sqft !== 'undefined' ? `${formatNumber(d.area_sqft)} ft` : '');
      add('Total area', d.total_area !== null && typeof d.total_area !== 'undefined' ? `${formatNumber(d.total_area)} ft` : '');
      add('Living area', d.living_area !== null && typeof d.living_area !== 'undefined' ? `${formatNumber(d.living_area)} ft` : '');
      add('Kitchen area', d.kitchen_area !== null && typeof d.kitchen_area !== 'undefined' ? `${formatNumber(d.kitchen_area)} ft` : '');
      add('Parking', d.parking ?? '');
      add('Address', d.address ?? '');
      add('Contact Name', d.contact_name || '', { section: 'Contact details' });
      add('Contact', [d.contact_phone, d.contact_email].filter(Boolean).join('\n'), { preLine: true, section: 'Contact details' });
      add('Contact Address', d.address ?? '', { section: 'Contact details' });
      add('City', item.city?.name || '', { section: 'Contact details' });
    }

    if (item.module === 'cars' && item.details?.car) {
      const d = item.details.car;
      add('Contact', [d.contact_phone, d.contact_email].filter(Boolean).join('\n'), { preLine: true });
      add('Car', [d.brand, d.model].filter(Boolean).join(' '));
      add('Condition', d.condition || '');
      add('Verified', d.is_verified ? 'Yes' : 'No');
      add('Year', d.year || '');
      add('Mileage', d.mileage ? formatNumber(d.mileage) : '');
      add('Body', d.body_type || '');
      add('Drive', d.drive_type || '');
      add('Engine', d.engine || '');
      add('Fuel', d.fuel_type || '');
      add('Transmission', d.transmission || '');
      if (d.city_mpg || d.highway_mpg) {
        add('MPG', [
          d.city_mpg ? `City ${formatNumber(d.city_mpg)}` : '',
          d.highway_mpg ? `Hwy ${formatNumber(d.highway_mpg)}` : '',
        ].filter(Boolean).join(' / '));
      }
      add('Lister', [d.contact_first_name, d.contact_last_name].filter(Boolean).join(' '));
      add('Seller', d.seller_type || '');
      const flags = [];
      if (d.negotiated) flags.push('Negotiable');
      if (d.installments) flags.push('Installments');
      if (d.exchange) flags.push('Exchange');
      if (d.uncleared) flags.push('Uncleared');
      if (d.dealer_ready) flags.push('Dealer ready');
      if (flags.length) add('Options', flags.join(', '));
      add('Color', [d.exterior_color, d.interior_color].filter(Boolean).join(' / '));
    }

    if (item.module === 'restaurants') {
      const d = item.details?.restaurant || {};
      add('Cuisine', item.category?.name || '');
      add('Seats', d.seating_capacity ? formatNumber(d.seating_capacity) : '');
      add('Contact Name', d.contact_name || '', { section: 'Contact details' });
      add('Contact', [d.phone, d.email].filter(Boolean).join('\n'), { preLine: true, section: 'Contact details' });
      add('Address', d.address || '', { section: 'Contact details' });
      const restHours = formatHours(d.opening_hours);
      if (restHours) add('Hours', restHours, { preLine: true });
    }

    return pairs;
  };

  const renderDetailsGrid = (item) => {
    const pairs = detailPairs(item);
    if (!pairs.length) {
      return '<div class="text-body-secondary fs-sm">Details not available.</div>';
    }

    const wideLabels = new Set(['Address', 'Hours', 'Contact', 'Services', 'Service Area', 'Contact Address']);
    const colClassFor = (label) => {
      const base = 'col-12 col-sm-6 col-lg-3';
      if (!label) return base;
      if (wideLabels.has(label)) return 'col-12 col-sm-6 col-lg-6';
      return base;
    };

    const valueStyleFor = (p) => {
      const style = ['overflow-wrap:anywhere', 'word-break:break-word'];
      if (p.preLine) style.push('white-space:pre-line');
      return style.join(';');
    };
    const grouped = pairs.reduce((acc, pair) => {
      const section = pair.section || 'Details';
      if (!acc[section]) acc[section] = [];
      acc[section].push(pair);
      return acc;
    }, {});

    return Object.entries(grouped).map(([section, sectionPairs]) => `
      <section class="${section === 'Details' ? '' : 'mt-4'}">
        ${section === 'Details' ? '' : `<h2 class="h4 mb-3">${escapeHtml(section)}</h2>`}
        <div class="row g-3">
          ${sectionPairs.map((p) => `
            <div class="${colClassFor(p.label)}">
              <div class="border rounded p-3 h-100">
                <div class="text-body-secondary small">${escapeHtml(p.label)}</div>
                <div class="fw-semibold" style="${valueStyleFor(p)}">${escapeHtml(p.value)}</div>
              </div>
            </div>
          `).join('')}
        </div>
      </section>
    `).join('');
  };

  const renderReviewsSection = (item) => {
    const moduleName = moduleLabel(item?.module || selectedModule).replace(/s$/, '').toLowerCase();
    const auth = window.MonaclickAuth || {};
    const isAuthenticated = Boolean(auth && auth.isAuthenticated);
    const isUserAccount = String(auth.accountType || '') === 'user';
    const canSubmitReview = isAuthenticated && isUserAccount;
    const reviewerLabel = escapeHtml(auth.userName || 'Monaclick User');
    const nextUrl = encodeURIComponent(`${window.location.pathname}${window.location.search}${window.location.hash || ''}`);
    return `
      <section class="pb-sm-2 pb-lg-3 mb-5" data-mc-reviews-section="${escapeHtml(item?.module || selectedModule)}">
        <div class="border rounded-4 p-4">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="fi-edit fs-5 text-primary"></i>
            <h2 class="h4 mb-0">Write a review</h2>
          </div>
          <p class="text-body-secondary fs-sm mb-4">${canSubmitReview ? 'Leave your review for this listing.' : (isAuthenticated ? 'Only Monaclick user accounts can leave reviews.' : 'Sign in first to leave a review for this listing.')}</p>
          ${canSubmitReview ? `
          <form data-mc-review-form="${escapeHtml(item?.module || selectedModule)}" novalidate>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Signed in as</label>
                <input type="text" class="form-control" value="${reviewerLabel}" readonly>
              </div>
              <div class="col-12">
                <label class="form-label d-block mb-2">Your rating</label>
                <div class="d-flex flex-wrap gap-3">
                  ${[5, 4, 3, 2, 1].map((score) => `
                    <label class="form-check form-check-inline m-0">
                      <input class="form-check-input" type="radio" name="rating" value="${score}" ${score === 5 ? 'checked' : ''}>
                      <span class="form-check-label">${score} star${score === 1 ? '' : 's'}</span>
                    </label>
                  `).join('')}
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Your review</label>
                <textarea class="form-control" name="message" rows="5" placeholder="Write your review about this ${escapeHtml(moduleName)} listing..." required></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Submit review</button>
              </div>
            </div>
            <div class="alert alert-success mt-3 mb-0 d-none" data-mc-review-success="${escapeHtml(item?.module || selectedModule)}">
              Thanks! Your review has been received.
            </div>
            <div class="alert alert-danger mt-3 mb-0 d-none" data-mc-review-error="${escapeHtml(item?.module || selectedModule)}"></div>
          </form>
          ` : `
          <div class="card border-0 bg-body-tertiary">
            <div class="card-body p-4">
              <h3 class="h6 mb-2">${isAuthenticated ? 'User account required' : 'User account required'}</h3>
              <p class="text-body-secondary mb-3">${isAuthenticated ? 'Business owner accounts cannot submit reviews. Please use a Monaclick user account to leave a review.' : 'Reviews can only be submitted after signing in with a Monaclick user account.'}</p>
              ${isAuthenticated ? '' : `<a href="${escapeHtml(`/signin?next=${nextUrl}`)}" class="btn btn-primary">Sign in to review</a>`}
            </div>
          </div>
          `}
        </div>
      </section>
    `;
  };

  const renderContractorServicesSection = (item) => {
    if (item?.module !== 'contractors') return '';
    const services = Array.isArray(item?.details?.contractor?.services_provided)
      ? item.details.contractor.services_provided
      : [];

    if (!services.length) return '';

    const badges = services
      .map((label) => `<span class="badge bg-body-secondary text-body">${escapeHtml(humanizeListLabel(label))}</span>`)
      .join('');

    if (!badges) return '';

    return `
      <section class="pb-sm-2 pb-lg-3 mb-5">
        <h2 class="h4 mb-3">Services I provide:</h2>
        <div class="d-flex flex-wrap gap-2">${badges}</div>
      </section>
    `;
  };

  const renderRestaurantServicesSection = (item) => {
    if (item?.module !== 'restaurants') return '';
    const services = Array.isArray(item?.details?.restaurant?.services)
      ? item.details.restaurant.services.filter(Boolean)
      : [];

    if (!services.length) return '';

    const badges = services
      .map((label) => `<span class="badge bg-body-secondary text-body">${escapeHtml(humanizeListLabel(label))}</span>`)
      .join('');

    if (!badges) return '';

    return `
      <section class="pb-sm-2 pb-lg-3 mb-5">
        <h2 class="h4 mb-3">Services</h2>
        <div class="d-flex flex-wrap gap-2">${badges}</div>
      </section>
    `;
  };

  const updateEntryReviewSummary = (item) => {
    const stats = reviewStats(item);
    const ratingNode = container.querySelector('[data-mc-entry-rating-value]');
    const countNode = container.querySelector('[data-mc-entry-rating-count]');
    if (ratingNode) ratingNode.textContent = stats.rating.toFixed(1);
    if (countNode) countNode.textContent = `(${stats.reviews})`;
  };

  const bindReviewForms = (item) => {
    container.querySelectorAll('[data-mc-review-form]').forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      if (form.dataset.mcReviewBound === '1') return;
      form.dataset.mcReviewBound = '1';

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const success = form.querySelector('[data-mc-review-success]');
        const error = form.querySelector('[data-mc-review-error]');
        if (success) success.classList.add('d-none');
        if (error) {
          error.classList.add('d-none');
          error.textContent = '';
        }

        const selectedRating = Number(form.querySelector('input[name="rating"]:checked')?.value || 0);
        const comment = String(form.querySelector('textarea[name="message"]')?.value || '').trim();
        if (!selectedRating || comment.length < 10 || !item?.slug) {
          if (error) {
            error.textContent = 'Please choose a rating and write at least 10 characters.';
            error.classList.remove('d-none');
          }
          return;
        }

        const submit = form.querySelector('button[type="submit"]');
        const originalLabel = submit?.textContent || 'Submit review';
        if (submit) {
          submit.disabled = true;
          submit.textContent = 'Submitting...';
        }

        try {
          const auth = window.MonaclickAuth || {};
          const response = await window.fetch(`/entry/${encodeURIComponent(item.slug)}/reviews`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': String(auth.csrfToken || ''),
            },
            body: JSON.stringify({
              rating: selectedRating,
              comment,
            }),
          });

          if (response.status === 401) {
            const nextUrl = encodeURIComponent(`${window.location.pathname}${window.location.search}${window.location.hash || ''}`);
            window.location.href = `/signin?next=${nextUrl}`;
            return;
          }

          const payload = await response.json().catch(() => null);
          if (!response.ok || !payload?.listing) {
            throw new Error(payload?.message || 'Review submit failed');
          }

          item.reviews_count = Number(payload.listing.reviews_count || 0);
          item.rating = Number(payload.listing.rating || 0);
          updateEntryReviewSummary(item);

          if (success) {
            success.textContent = String(payload.message || 'Thanks! Your review has been received.');
            success.classList.remove('d-none');
          }

          form.reset();
          const defaultRating = form.querySelector('input[name="rating"][value="5"]');
          if (defaultRating instanceof HTMLInputElement) defaultRating.checked = true;
        } catch (err) {
          if (error) {
            error.textContent = err instanceof Error ? err.message : 'We could not submit your review right now.';
            error.classList.remove('d-none');
          }
        } finally {
          if (submit) {
            submit.disabled = false;
            submit.textContent = originalLabel;
          }
        }
      });
    });
  };

  const renderEntry = (item, related = []) => {
    document.title = `Monaclick | ${moduleLabel(item.module)} - ${item.title}`;
    const stats = reviewStats(item);

    const images = [item.image_url, ...(item.images || []).map((img) => img.image_url)].filter(Boolean).slice(0, 5);
    const primaryImage = images[0] || '/finder/assets/img/placeholders/preview-square.svg';
    const thumbs = images.slice(1, 5);
    const titleBadges = (() => {
      if (item.module !== 'cars') return '';
      const badges = [];
      const cond = String(item.details?.car?.condition || '').trim().toLowerCase();
      if (cond) {
        const label = cond.includes('new') ? 'New' : 'Used';
        badges.push(`<span class="badge text-bg-warning">${escapeHtml(label)}</span>`);
      }
      const hasVerified = Array.isArray(item.features)
        && item.features.some((f) => String(f || '').trim().toLowerCase().includes('verified'));
      if (hasVerified) {
        badges.unshift('<span class="badge text-bg-success">Verified</span>');
      }
      if (!badges.length) return '';
      return `<div class="d-flex flex-wrap gap-2 mb-2">${badges.join('')}</div>`;
    })();

    const renderCarSections = (car) => {
      const d = car || {};
      const blocks = [];

      const section = (title, inner) => {
        const html = String(inner || '').trim();
        if (!html) return;
        blocks.push(`
          <section class="pb-sm-2 pb-lg-3 mb-5">
            <h2 class="h4 mb-3">${escapeHtml(title)}</h2>
            ${html}
          </section>
        `);
      };

      const rows = (items) => {
        const visible = items.filter((it) => it && String(it.value ?? '').trim() !== '');
        if (!visible.length) return '';
        return `
          <div class="row g-3">
            ${visible.map((it) => `
              <div class="${it.wide ? 'col-12' : 'col-12 col-sm-6'}">
                <div class="border rounded p-3 h-100">
                  <div class="text-body-secondary small">${escapeHtml(it.label)}</div>
                  <div class="fw-semibold"${it.preLine ? ' style="white-space:pre-line;overflow-wrap:anywhere;word-break:break-word"' : ' style="overflow-wrap:anywhere;word-break:break-word"'}>${escapeHtml(it.value)}</div>
                </div>
              </div>
            `).join('')}
          </div>
        `;
      };

      const contactName = [d.contact_first_name, d.contact_last_name].filter(Boolean).join(' ').trim();

      section('Basic information', rows([
        { label: 'Condition of the car', value: d.condition || '' },
        { label: 'Car brand', value: d.brand || '' },
        { label: 'Car model', value: d.model || '' },
        { label: 'Manufacturing year', value: d.year || '' },
        { label: 'Mileage', value: d.mileage ? formatNumber(d.mileage) : '' },
        { label: 'Body type', value: d.body_type || '' },
        { label: 'Radius', value: d.radius ? formatNumber(d.radius) : '' },
        { label: 'City', value: item.city?.name || '' },
      ]));

      section('Specifications', rows([
        { label: 'Drive type', value: d.drive_type || '' },
        { label: 'Engine', value: d.engine || '' },
        { label: 'Fuel type', value: d.fuel_type || '' },
        { label: 'Transmission', value: d.transmission || '' },
        { label: 'City MPG', value: d.city_mpg ? formatNumber(d.city_mpg) : '' },
        { label: 'Highway MPG', value: d.highway_mpg ? formatNumber(d.highway_mpg) : '' },
        { label: 'Exterior color', value: d.exterior_color || '' },
        { label: 'Interior color', value: d.interior_color || '' },
        { label: 'Description', value: item.excerpt || '', wide: true },
      ]));

      // Features (with subheadings based on add-car form groups)
      const featureLabels = normalizedFeatures(item).map(humanizeFeature).filter(Boolean);
      if (featureLabels.length) {
        section('Features', `
          <div data-mc-car-features="1" class="text-body-secondary small">Loading feature groups…</div>
        `);
      }

      const priceOptions = [];
      if (d.negotiated) priceOptions.push('Negotiated price');
      if (d.installments) priceOptions.push('Payment in installments is possible');
      if (d.exchange) priceOptions.push('Exchange for a car is possible');
      if (d.uncleared) priceOptions.push('Uncleared car');
      if (d.dealer_ready) priceOptions.push('Ready to cooperate with dealers');

      section('Price', rows([
        { label: 'Price', value: formatMoneyDisplay(item.price || '') },
        { label: 'Selected options', value: priceOptions.join('\n'), preLine: true, wide: true },
      ]));

      section('Contacts', rows([
        { label: 'Seller type', value: d.seller_type || '' },
        { label: 'First name', value: d.contact_first_name || '' },
        { label: 'Last name', value: d.contact_last_name || '' },
        { label: 'Email', value: d.contact_email || '' },
        { label: 'Phone number', value: d.contact_phone || '' },
        { label: 'Contact', value: [d.contact_phone, d.contact_email].filter(Boolean).join('\n'), preLine: true, wide: true },
        { label: 'Lister', value: contactName || '' },
      ]));

      return blocks.join('');
    };

    container.innerHTML = `
      <nav class="pb-2 pb-md-3" aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="/${item.module}">Home</a></li>
          <li class="breadcrumb-item"><a href="/listings/${item.module}">${moduleLabel(item.module)}</a></li>
          <li class="breadcrumb-item active" aria-current="page">${escapeHtml(item.title)}</li>
        </ol>
      </nav>

      <div class="d-flex align-items-start align-items-sm-center justify-content-between pb-3 mb-3">
        <div>
          ${titleBadges}
          <h1 class="h4 mb-2">${escapeHtml(item.title)}</h1>
          <ul class="list-inline gap-2 fs-sm ms-n2 mb-0">
            <li class="d-flex align-items-center gap-1 ms-2">
              <i class="fi-star-filled text-warning"></i>
              <span class="fs-sm text-secondary-emphasis" data-mc-entry-rating-value>${stats.rating.toFixed(1)}</span>
              <span class="fs-xs text-body-secondary align-self-end" data-mc-entry-rating-count>(${stats.reviews})</span>
            </li>
            <li class="d-flex align-items-center gap-1 ms-2">
              <i class="fi-map-pin"></i>
              ${escapeHtml(item.city?.name || 'City')}
            </li>
            <li class="d-flex align-items-center gap-1 ms-2">
              <i class="fi-credit-card"></i>
              ${escapeHtml(formatMoneyDisplay(item.price || '') || 'Price on request')}
            </li>
          </ul>
        </div>
      </div>

      <div class="row g-3 g-sm-4 g-md-3 g-xl-4 pb-sm-2 mb-5">
        <div class="col-md-8">
          <a class="hover-effect-scale hover-effect-opacity position-relative d-flex rounded overflow-hidden" href="${escapeHtml(primaryImage)}" data-glightbox data-gallery="image-gallery">
            <i class="fi-zoom-in hover-effect-target fs-3 text-white position-absolute top-50 start-50 translate-middle opacity-0 z-2"></i>
            <span class="hover-effect-target position-absolute top-0 start-0 w-100 h-100 bg-black bg-opacity-25 opacity-0 z-1"></span>
            <div class="ratio hover-effect-target bg-body-tertiary rounded" style="--fn-aspect-ratio: calc(432 / 856 * 100%)">
              <img src="${escapeHtml(primaryImage)}" alt="${escapeHtml(item.title)}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
            </div>
          </a>
        </div>
        <div class="col-md-4">
          <div class="row row-cols-2 g-3 g-sm-4 g-md-3 g-xl-4">
            ${thumbs.map((img) => `
              <div class="col">
                <a class="hover-effect-scale hover-effect-opacity position-relative d-flex rounded overflow-hidden" href="${escapeHtml(img)}" data-glightbox data-gallery="image-gallery">
                  <i class="fi-zoom-in hover-effect-target fs-3 text-white position-absolute top-50 start-50 translate-middle opacity-0 z-2"></i>
                  <span class="hover-effect-target position-absolute top-0 start-0 w-100 h-100 bg-black bg-opacity-25 opacity-0 z-1"></span>
                  <div class="ratio hover-effect-target bg-body-tertiary rounded" style="--fn-aspect-ratio: calc(204 / 196 * 100%)">
                    <img src="${escapeHtml(img)}" alt="${escapeHtml(item.title)}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
                  </div>
                </a>
              </div>
            `).join('')}
          </div>
        </div>
      </div>

      <div class="row pb-2 pb-sm-3 pb-md-4 pb-lg-5">
        <div class="col-lg-8 col-xl-7">
          <section class="pb-sm-2 pb-lg-3 mb-5">
            <h2 class="h4 mb-lg-4">${item.module === 'contractors' ? 'My work' : 'About'}</h2>
            <p class="fs-sm mb-0">${escapeHtml(capitalizeFirstLetter(normalizeRestaurantExcerpt(item) || 'No description available yet.'))}</p>
          </section>
          ${item.module === 'cars'
            ? `${renderCarSections(item.details?.car || {})}${renderReviewsSection(item)}`
            : `
              <section class="pb-sm-2 pb-lg-3 mb-5">
                <h2 class="h4 mb-3">Details</h2>
                ${renderDetailsGrid(item)}
              </section>
              ${renderContractorServicesSection(item)}
              ${renderRestaurantServicesSection(item)}
              ${renderFeatures(item)}
              ${renderReviewsSection(item)}
            `}
        </div>
      </div>
    `;

    ensureFeaturesAfterDetails(item);

    if (window.GLightbox) {
      try {
        ensureEntryLightboxStyles();

        if (window.__MC_ENTRY_LIGHTBOX__ && typeof window.__MC_ENTRY_LIGHTBOX__.destroy === 'function') {
          try {
            window.__MC_ENTRY_LIGHTBOX__.destroy();
          } catch (e) {
            // ignore stale instance cleanup failures
          }
        }

        const lightbox = window.GLightbox({
          selector: '[data-glightbox]',
          openEffect: 'fade',
          closeEffect: 'fade',
          slideEffect: 'slide',
          loop: false,
          touchNavigation: true,
        });
        window.__MC_ENTRY_LIGHTBOX__ = lightbox;

        if (lightbox && typeof lightbox.on === 'function') {
          const hidePageLoader = () => {
            if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
              window.__MC_HIDE_PAGE_LOADER__();
            }
          };

          lightbox.on('open', hidePageLoader);
          lightbox.on('slide_before_load', hidePageLoader);
          lightbox.on('slide_after_load', hidePageLoader);
          lightbox.on('slide_changed', hidePageLoader);
          lightbox.on('close', () => {
            hidePageLoader();
            window.setTimeout(hidePageLoader, 0);
            window.setTimeout(hidePageLoader, 80);
          });
        }
      } catch (e) {
        // no-op
      }
    }

    bindReviewForms(item);

    // Enhance car features into sub-headings (Exterior / Interior / Safety) once the car sections are present.
    if (item.module === 'cars') {
      const carFeaturesTarget = container.querySelector('[data-mc-car-features="1"]');
      if (carFeaturesTarget) {
        enhanceCarFeaturesSection(item).then(() => {
          // If legacy features section was rendered, keep it; otherwise swap the placeholder.
          const legacy = container.querySelector('section[data-mc-features="1"]');
          if (legacy) return;
          const features = normalizedFeatures(item).map(humanizeFeature).filter(Boolean);
          if (!features.length) return;
          getCarFeatureGroupMap().then((map) => {
            const groups = {};
            features.forEach((label) => {
              const group = String(map[label] || '').trim() || 'Other';
              if (!groups[group]) groups[group] = [];
              groups[group].push(label);
            });
            const order = ['Exterior', 'Interior', 'Safety', 'Other'];
            const groupNames = Array.from(new Set([...order, ...Object.keys(groups)]));
            const body = groupNames.map((name) => {
              const items = groups[name] || [];
              if (!items.length) return '';
              return `
                <div class="mb-3">
                  <h3 class="h6 mb-2">${escapeHtml(name)}</h3>
                  <div class="d-flex flex-wrap gap-2">
                    ${items.map((txt) => `<span class="badge bg-body-secondary text-body">${escapeHtml(txt)}</span>`).join('')}
                  </div>
                </div>
              `;
            }).filter(Boolean).join('');
            if (body) carFeaturesTarget.outerHTML = body;
          });
        });
      } else {
        enhanceCarFeaturesSection(item);
      }
    } else {
      enhanceCarFeaturesSection(item);
    }

    renderRelatedSection(item, related);

    try { container.style.opacity = '1'; } catch (e) {}
    setReady();
  };

  const relatedDetailUrl = (item) => {
    const module = encodeURIComponent(item?.module || selectedModule);
    const slug = String(item?.slug || '').trim();
    if (slug) return `/entry/${module}?slug=${encodeURIComponent(slug)}`;
    return `/listings/${module}`;
  };

  const relatedSectionTarget = () => {
    const sections = Array.from(document.querySelectorAll('main.content-wrapper > section.container'));
    return sections.find((section) => {
      const heading = section.querySelector('h2');
      return heading && /you may be interested in/i.test(heading.textContent || '');
    }) || null;
  };

  const relatedStorage = {
    favorites: 'mc_related_favorites_v1',
    favoriteItems: 'mc_related_favorite_items_v1',
    alerts: 'mc_related_alerts_v1',
    compare: 'mc_related_compare_v1',
    compareItems: 'mc_related_compare_items_v1',
  };

  const readStoredSlugs = (key) => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(key) || '[]');
      return Array.isArray(parsed) ? parsed.map((value) => String(value || '').trim()).filter(Boolean) : [];
    } catch (e) {
      return [];
    }
  };

  const writeStoredSlugs = (key, values) => {
    try {
      window.localStorage.setItem(key, JSON.stringify(Array.from(new Set(values.map((value) => String(value || '').trim()).filter(Boolean)))));
    } catch (e) {
      // no-op
    }
  };

  const readStoredItems = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(relatedStorage.favoriteItems) || '{}');
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (e) {
      return {};
    }
  };

  const writeStoredItems = (items) => {
    try {
      window.localStorage.setItem(relatedStorage.favoriteItems, JSON.stringify(items || {}));
    } catch (e) {
      // no-op
    }
  };

  const showRelatedToast = (message) => {
    const id = 'mc-related-actions-toast';
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
    clearTimeout(showRelatedToast._timer);
    showRelatedToast._timer = setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
    }, 1800);
  };

  const syncActionButtonState = (button, active) => {
    if (!button) return;
    button.dataset.active = active ? '1' : '0';
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.classList.toggle('btn-primary', active);
    button.classList.toggle('btn-outline-secondary', !active);
    button.classList.toggle('text-white', active);
  };

  const syncRelatedActionStates = (section) => {
    if (!section) return;
    const favorites = new Set(readStoredSlugs(relatedStorage.favorites));
    const alerts = new Set(readStoredSlugs(relatedStorage.alerts));
    const compare = new Set(readStoredSlugs(relatedStorage.compare));
    section.querySelectorAll('[data-mc-action]').forEach((button) => {
      const slug = String(button.getAttribute('data-mc-slug') || '').trim();
      const action = String(button.getAttribute('data-mc-action') || '').trim();
      const active = action === 'favorite'
        ? favorites.has(slug)
        : action === 'notify'
          ? alerts.has(slug)
          : compare.has(slug);
      syncActionButtonState(button, active);
    });
  };

  const handleRelatedAction = (button, section) => {
    if (!button || !section) return;
    const action = String(button.getAttribute('data-mc-action') || '').trim();
    const slug = String(button.getAttribute('data-mc-slug') || '').trim();
    const title = String(button.getAttribute('data-mc-title') || 'Listing').trim();
    const payloadRaw = button.getAttribute('data-mc-item') || '';
    if (!action || !slug) return;

    const storageKey = action === 'favorite'
      ? relatedStorage.favorites
      : action === 'notify'
        ? relatedStorage.alerts
        : relatedStorage.compare;

    const current = readStoredSlugs(storageKey);
    const exists = current.includes(slug);
    let next = current.slice();

    if (exists) {
      next = current.filter((value) => value !== slug);
    } else if (action === 'compare') {
      next = [...current.filter((value) => value !== slug), slug].slice(-4);
    } else {
      next = [...current, slug];
    }

    writeStoredSlugs(storageKey, next);

    if (action === 'favorite') {
      const items = readStoredItems();
      if (exists) {
        delete items[slug];
      } else if (payloadRaw) {
        try {
          items[slug] = JSON.parse(payloadRaw);
        } catch (e) {
          // no-op
        }
      }
      writeStoredItems(items);
    }

    if (action === 'compare') {
      const key = relatedStorage.compareItems;
      const items = (() => {
        try {
          const parsed = JSON.parse(window.localStorage.getItem(key) || '{}');
          return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (e) {
          return {};
        }
      })();
      if (exists) {
        delete items[slug];
      } else if (payloadRaw) {
        try {
          items[slug] = JSON.parse(payloadRaw);
        } catch (e) {
          // no-op
        }
      }
      try {
        window.localStorage.setItem(key, JSON.stringify(items));
      } catch (e) {
        // no-op
      }
    }

    syncRelatedActionStates(section);

    if (action === 'favorite') {
      showRelatedToast(exists ? `${title} removed from favorites.` : `${title} saved to favorites.`);
      return;
    }
    if (action === 'notify') {
      showRelatedToast(exists ? `Alerts turned off for ${title}.` : `Alerts turned on for ${title}.`);
      return;
    }
    showRelatedToast(exists ? `${title} removed from compare.` : `${title} added to compare.`);
  };

  const bindRelatedActions = (section) => {
    if (!section || section.dataset.mcActionsBound === '1') return;
    section.dataset.mcActionsBound = '1';
    section.addEventListener('click', (event) => {
      const button = event.target.closest('[data-mc-action]');
      if (!button || !section.contains(button)) return;
      event.preventDefault();
      event.stopPropagation();
      handleRelatedAction(button, section);
    });
  };

  const renderRelatedCard = (item) => {
    const year = item?.details?.car?.year || '';
    const conditionRaw = String(item?.details?.car?.condition || '').trim().toLowerCase();
    const conditionLabel = conditionRaw
      ? (conditionRaw.includes('new') ? 'New' : 'Used')
      : '';
    const features = Array.isArray(item?.features) ? item.features : [];
    const verified = features.some((feature) => String(feature || '').trim().toLowerCase().includes('verified'));
    const image = item?.image_url || '/finder/assets/img/placeholders/preview-square.svg';
    const city = item?.city?.name || '';
    const mileage = item?.details?.car?.mileage ? `${formatNumber(item.details.car.mileage)} mi` : '';
    const fuelType = item?.details?.car?.fuel_type || '';
    const transmission = item?.details?.car?.transmission || '';
    const createdAt = (() => {
      const raw = item?.created_at || item?.published_at || item?.updated_at || '';
      if (!raw) return '';
      const d = new Date(raw);
      if (Number.isNaN(d.getTime())) return '';
      return d.toLocaleDateString('en-GB');
    })();
    const itemPayload = escapeHtml(JSON.stringify({
      slug: item?.slug || '',
      module: item?.module || selectedModule,
      title: item?.title || '',
      price: item?.price || '',
      image_url: image,
      city: city,
      year,
      mileage,
      fuel_type: fuelType,
      transmission,
      detail_url: relatedDetailUrl(item),
    }));

    return `
      <div class="col">
        <article class="card h-100 hover-effect-scale bg-body-tertiary border-0">
          <div class="card-img-top position-relative overflow-hidden">
            <div class="d-flex flex-column gap-2 align-items-start position-absolute top-0 start-0 z-1 pt-1 pt-sm-0 ps-1 ps-sm-0 mt-2 mt-sm-3 ms-2 ms-sm-3">
              ${verified ? `
                <span class="badge text-bg-info d-inline-flex align-items-center">
                  Verified
                  <i class="fi-shield ms-1"></i>
                </span>
              ` : ''}
              ${conditionLabel ? `<span class="badge ${conditionLabel === 'New' ? 'text-bg-primary' : 'text-bg-warning'}">${escapeHtml(conditionLabel)}</span>` : ''}
            </div>
            <div class="ratio hover-effect-target bg-body rounded" style="--fn-aspect-ratio: calc(204 / 306 * 100%)">
              <img src="${escapeHtml(image)}" alt="${escapeHtml(item?.title || 'Listing image')}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';" style="object-fit:cover;">
            </div>
          </div>
          <div class="card-body pb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fs-xs text-body-secondary me-3">${escapeHtml(createdAt || 'Recently added')}</div>
              <div class="d-flex gap-2 position-relative z-2">
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary animate-pulse rounded-circle" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-sm" title="Wishlist" aria-label="Add to wishlist" data-mc-action="favorite" data-mc-slug="${escapeHtml(item?.slug || '')}" data-mc-title="${escapeHtml(item?.title || 'Listing')}" data-mc-item="${itemPayload}">
                  <i class="fi-heart animate-target fs-sm"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary animate-shake rounded-circle" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-sm" title="Notify" aria-label="Notify" data-mc-action="notify" data-mc-slug="${escapeHtml(item?.slug || '')}" data-mc-title="${escapeHtml(item?.title || 'Listing')}">
                  <i class="fi-bell animate-target fs-sm"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary animate-rotate rounded-circle" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-sm" title="Compare" aria-label="Compare" data-mc-action="compare" data-mc-slug="${escapeHtml(item?.slug || '')}" data-mc-title="${escapeHtml(item?.title || 'Listing')}" data-mc-item="${itemPayload}">
                  <i class="fi-repeat animate-target fs-sm"></i>
                </button>
              </div>
            </div>
            <h3 class="h6 mb-2">
              <a class="hover-effect-underline stretched-link me-1" href="${escapeHtml(relatedDetailUrl(item))}">${escapeHtml(item?.title || 'Listing')}</a>
              ${year ? `<span class="fs-xs fw-normal text-body-secondary">(${escapeHtml(year)})</span>` : ''}
            </h3>
            <div class="h6 mb-0">${escapeHtml(formatMoneyDisplay(item?.price || '') || 'Price on request')}</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-4">
            <div class="border-top pt-3">
              <div class="row row-cols-2 g-2 fs-sm">
                <div class="col d-flex align-items-center gap-2">
                  <i class="fi-map-pin"></i>
                  ${escapeHtml(city || 'Location')}
                </div>
                <div class="col d-flex align-items-center gap-2">
                  <i class="fi-tachometer"></i>
                  ${escapeHtml(mileage || 'N/A')}
                </div>
                <div class="col d-flex align-items-center gap-2">
                  <i class="fi-gas-pump"></i>
                  ${escapeHtml(fuelType || 'N/A')}
                </div>
                <div class="col d-flex align-items-center gap-2">
                  <i class="fi-gearbox"></i>
                  ${escapeHtml(transmission || 'N/A')}
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    `;
  };

  const renderRelatedSection = (item, related = []) => {
    const section = relatedSectionTarget();
    if (!section) return;

    const cards = Array.isArray(related) ? related.filter((entry) => entry && entry.slug && entry.slug !== item?.slug) : [];

    section.innerHTML = `
      <div class="d-flex align-items-start justify-content-between gap-4 pb-3 mb-2 mb-sm-3">
        <h2 class="mb-0">You may be interested in</h2>
        <div class="nav">
          <a class="nav-link position-relative text-nowrap py-1 px-0" href="/listings/${encodeURIComponent(item?.module || selectedModule)}">
            <span class="hover-effect-underline stretched-link me-1">View all</span>
            <i class="fi-chevron-right fs-lg"></i>
          </a>
        </div>
      </div>
      ${cards.length ? `
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-4">
          ${cards.slice(0, 4).map((entry) => renderRelatedCard(entry)).join('')}
        </div>
      ` : `
        <div class="alert alert-secondary mb-0">No related listings available right now.</div>
      `}
    `;

    if (window.bootstrap?.Tooltip) {
      try {
        section.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((node) => {
          window.bootstrap.Tooltip.getOrCreateInstance(node, {
            trigger: 'hover',
          });
        });
      } catch (e) {
        // no-op
      }
    }

    bindRelatedActions(section);
    syncRelatedActionStates(section);
  };

  const showNoPreview = () => {
    container.innerHTML = `
      <div class="alert alert-warning mb-0">
        Preview data is missing. Please open detailed preview from the listing form again.
      </div>
    `;
    try { container.style.opacity = '1'; } catch (e) {}
    setReady();
  };

  const redirectToModuleListings = () => {
    const target = `/listings/${encodeURIComponent(selectedModule)}`;
    try {
      window.location.replace(target);
      return;
    } catch (e) {
      window.location.href = target;
    }
  };

  if (selectedModule === 'cars' && params.get('preview') === '1') {
    const truthyParam = (key) => {
      if (!params.has(key)) return false;
      const raw = String(params.get(key) ?? '').trim().toLowerCase();
      if (!raw) return true;
      if (['0', 'false', 'no', 'off'].includes(raw)) return false;
      return true;
    };

    const parseJsonArray = (raw) => {
      const text = String(raw ?? '').trim();
      if (!text) return [];
      try {
        const decoded = JSON.parse(text);
        return Array.isArray(decoded) ? decoded : [];
      } catch (e) {
        return [];
      }
    };

    const uniq = (values) => {
      const out = [];
      const seen = new Set();
      values.forEach((v) => {
        const s = String(v ?? '').trim();
        if (!s) return;
        const key = s.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        out.push(s);
      });
      return out;
    };

    const title = params.get('title') || 'Car listing';
    const city = params.get('city') || '';
    const price = params.get('price') || '$0';
    const image = params.get('image') || '/finder/assets/img/placeholders/preview-square.svg';
    const brand = params.get('brand') || '';
    const model = params.get('model') || '';
    const condition = params.get('condition') || '';
    const year = params.get('year') || '';
    const mileage = params.get('mileage') || '';
    const radius = params.get('radius') || '';
    const driveType = params.get('drive_type') || '';
    const engine = params.get('engine') || '';
    const fuelType = params.get('fuel_type') || '';
    const transmission = params.get('transmission') || '';
    const bodyType = params.get('body_type') || params.get('body') || '';
    const exteriorColor = params.get('exterior_color') || '';
    const interiorColor = params.get('interior_color') || '';
    const sellerType = params.get('seller_type') || params.get('seller') || '';
    const contactPhone = params.get('contact_phone') || params.get('phone') || '';
    const contactEmail = params.get('contact_email') || params.get('email') || '';
    const features = uniq([
      ...parseJsonArray(params.get('features_json')),
      ...params.getAll('car_features[]'),
    ]);

    renderEntry({
      module: 'cars',
      title,
      excerpt: '',
      price,
      features,
      rating: 0,
      reviews_count: 0,
      image_url: image,
      images: [],
      city: { name: city },
      details: {
        car: {
          brand,
          model,
          condition,
          year,
          mileage,
          radius,
          drive_type: driveType,
          engine,
          fuel_type: fuelType,
          transmission,
          body_type: bodyType,
          exterior_color: exteriorColor,
          interior_color: interiorColor,
          seller_type: sellerType,
          contact_phone: contactPhone,
          contact_email: contactEmail,
          negotiated: truthyParam('negotiated'),
          installments: truthyParam('installments'),
          exchange: truthyParam('exchange'),
          uncleared: truthyParam('uncleared'),
          dealer_ready: truthyParam('dealer_ready'),
          features,
        },
      },
    }, []);
    return;
  }

  if (!params.get('slug') && selectedModule !== 'restaurants') {
    redirectToModuleListings();
    return;
  }

  if (selectedModule === 'restaurants' && !params.get('slug')) {
    setReady();
    return;
  }

  renderEntryLoader();

  const apiQuery = new URLSearchParams({ module: selectedModule });
  if (params.get('slug')) apiQuery.set('slug', params.get('slug'));

  fetch(`/api/monaclick/entry?${apiQuery.toString()}`)
    .then((res) => {
      if (!res.ok) throw new Error('Entry API failed');
      return res.json();
    })
    .then((payload) => {
      const item = payload?.data;
      const related = Array.isArray(payload?.related) ? payload.related : [];
      if (!item) throw new Error('No entry payload');
      renderEntry(item, related);
    })
    .catch(() => {
      container.innerHTML = `
        <div class="alert alert-danger mb-0">
          Unable to load listing details.
        </div>
      `;
      setReady();
    });
})();
