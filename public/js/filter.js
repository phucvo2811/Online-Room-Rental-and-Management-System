/**
 * FBar — Modern Filter Bar System
 * Handles: dropdowns, chip selection, active tags, mobile modal, form submit
 */
(function () {
  'use strict';

  /* ── Labels ───────────────────────────────────────────────── */
  const PRICE_LABELS = {
    '0,1000000':       'Dưới 1 triệu',
    '1000000,3000000': '1 – 3 triệu',
    '3000000,5000000': '3 – 5 triệu',
    '5000000,':        'Trên 5 triệu',
  };
  const AREA_LABELS = {
    '0,20': 'Dưới 20m²',
    '20,40': '20 – 40m²',
    '40,': 'Trên 40m²',
  };
  const SORT_LABELS = {
    'newest':     'Mới nhất',
    'price_asc':  'Giá tăng dần',
    'price_desc': 'Giá giảm dần',
    'popular':    'Phổ biến nhất',
    'area':       'Diện tích lớn',
  };
  const AMENITY_LABELS = {
    has_wifi:      'WiFi',
    has_ac:        'Máy lạnh',
    has_parking:   'Gửi xe',
    allow_pet:     'Thú cưng',
    allow_cooking: 'Nấu ăn',
  };
  const STATUS_LABELS = {
    available: 'Còn phòng',
    full:      'Hết phòng',
  };
  const TYPE_KEYS = ['type', 'room_type'];

  /* ── State ─────────────────────────────────────────────────── */
  let _openDrop = null;    // currently open dropdown id
  let _debTimer = null;    // search debounce
  let _typeLabels = {};    // injected from page: { boarding_house: 'Nhà trọ', ... }

  /* ── Helpers ─────────────────────────────────────────────────*/
  function $id(id)       { return document.getElementById(id); }
  function $qs(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $qsa(sel, ctx){ return (ctx || document).querySelectorAll(sel); }

  function getInput(name) { return $qs(`input[name="${name}"], select[name="${name}"]`, $id('fbarForm')); }

  function getVal(name) {
    const el = getInput(name);
    return el ? el.value.trim() : '';
  }

  function setVal(name, value) {
    const el = getInput(name);
    if (el) el.value = value;
  }

  /* ── Teleport dropdowns to <body> to escape stacking contexts ─*/
  function teleportDropdowns() {
    document.querySelectorAll('.fdrop').forEach(drop => {
      document.body.appendChild(drop);
    });
  }

  /* ── Dropdown open / close ───────────────────────────────────*/
  function positionDrop(btn, drop) {
    const rect = btn.getBoundingClientRect();
    // Make briefly visible (off-screen) to measure real width
    drop.style.visibility    = 'hidden';
    drop.style.opacity       = '0';
    drop.style.display       = 'block';
    drop.style.pointerEvents = 'none';
    const dropW = drop.offsetWidth || parseInt(drop.style.minWidth) || 220;
    // Clear inline overrides so CSS classes (.open) work properly
    drop.style.display       = '';
    drop.style.visibility    = '';
    drop.style.opacity       = '';
    drop.style.pointerEvents = '';

    drop.style.position = 'fixed';
    drop.style.top      = (rect.bottom + 8) + 'px';
    drop.style.bottom   = 'auto';

    if (drop.classList.contains('fdrop-right')) {
      let rightVal = window.innerWidth - rect.right;
      if (rect.right - dropW < 8) rightVal = window.innerWidth - rect.left - dropW;
      drop.style.right = Math.max(8, rightVal) + 'px';
      drop.style.left  = 'auto';
    } else {
      let leftVal = rect.left;
      const maxLeft = window.innerWidth - dropW - 8;
      drop.style.left  = Math.min(leftVal, maxLeft) + 'px';
      drop.style.right = 'auto';
    }
  }

  function openDrop(id) {
    if (_openDrop && _openDrop !== id) closeDrop(_openDrop);
    const btn  = $id('fpill-' + id);
    const drop = $id('fdrop-' + id);
    if (!btn || !drop) return;
    positionDrop(btn, drop);
    btn.classList.add('open');
    drop.classList.add('open');
    _openDrop = id;
  }

  function closeDrop(id) {
    const btn  = $id('fpill-' + id);
    const drop = $id('fdrop-' + id);
    if (btn)  btn.classList.remove('open');
    if (drop) drop.classList.remove('open');
    if (_openDrop === id) _openDrop = null;
  }

  function toggleDrop(id) {
    _openDrop === id ? closeDrop(id) : openDrop(id);
  }

  function closeAll() {
    if (_openDrop) closeDrop(_openDrop);
  }

  /* ── Pill label & active state ───────────────────────────────*/
  function updatePill(id, label, isActive) {
    const pill = $id('fpill-' + id);
    if (!pill) return;
    const labelEl = pill.querySelector('.fpill-label');
    if (labelEl) labelEl.textContent = label;
    pill.classList.toggle('active', !!isActive);
  }

  /* ── Price ───────────────────────────────────────────────────*/
  function setPrice(min, max, label) {
    setVal('price_min', min);
    setVal('price_max', max);
    // update option selected states
    $qsa('.fdrop-option[data-price]').forEach(el => {
      el.classList.toggle('selected', el.dataset.price === `${min},${max}`);
    });
    const hasVal = (min !== '' || max !== '');
    updatePill('price', label || 'Giá', hasVal);
    closeDrop('price');
    renderTags();
    submitForm();
  }

  /* ── Area ────────────────────────────────────────────────────*/
  function setArea(min, max, label) {
    setVal('area_min', min);
    setVal('area_max', max);
    $qsa('.fdrop-option[data-area]').forEach(el => {
      el.classList.toggle('selected', el.dataset.area === `${min},${max}`);
    });
    const hasVal = (min !== '' || max !== '');
    updatePill('area', label || 'Diện tích', hasVal);
    closeDrop('area');
    renderTags();
    submitForm();
  }

  /* ── Type / room_type ────────────────────────────────────────*/
  function setType(value) {
    const typeKey = $id('fbarForm').dataset.typeKey || 'type';
    setVal(typeKey, value);
    const label = value ? (_typeLabels[value] || value) : 'Loại phòng';
    $qsa('.fchip[data-type]').forEach(el => {
      el.classList.toggle('selected', el.dataset.type === value);
    });
    updatePill('type', label, !!value);
    renderTags();
    submitForm();
  }

  /* ── Sort ────────────────────────────────────────────────────*/
  function setSort(value) {
    setVal('sort', value);
    const label = SORT_LABELS[value] || 'Sắp xếp';
    const isActive = value && value !== 'newest';
    $qsa('.fdrop-option[data-sort]').forEach(el => {
      el.classList.toggle('selected', el.dataset.sort === value);
    });
    updatePill('sort', label, isActive);
    closeDrop('sort');
    renderTags();
    submitForm();
  }

  /* ── Status ──────────────────────────────────────────────────*/
  function setStatus(value) {
    setVal('status', value);
    $qsa('.fchip[data-status], .famenity-item[data-status]').forEach(el => {
      el.classList.toggle('selected', el.dataset.status === value);
    });
    updateMoreBadge();
    renderTags();
  }

  /* ── Amenity toggle ──────────────────────────────────────────*/
  function toggleAmenity(el) {
    const key = el.dataset.amenity;
    if (!key) return;
    const cur = getVal(key);
    const next = cur === '1' ? '' : '1';
    setVal(key, next);
    el.classList.toggle('selected', next === '1');
    // also sync counterpart in modal
    $qsa(`[data-amenity="${key}"]`).forEach(e => e.classList.toggle('selected', next === '1'));
    updateMoreBadge();
    renderTags();
  }

  function updateMoreBadge() {
    const amenityKeys = Object.keys(AMENITY_LABELS);
    const count = amenityKeys.filter(k => getVal(k) === '1').length
                + (getVal('status') ? 1 : 0);
    $qsa('#fpill-more .fpill-label').forEach(el => {
      el.textContent = count > 0 ? `Bộ lọc (${count})` : 'Bộ lọc';
    });
    const pill = $id('fpill-more');
    if (pill) pill.classList.toggle('active', count > 0);
  }

  /* ── District ────────────────────────────────────────────────*/
  function setDistrict(id, name) {
    setVal('district_id', id);
    const isActive = !!id;
    updatePill('district', name || 'Quận / Huyện', isActive);
    closeDrop('district');
    renderTags();
    submitForm();
  }

  /* ── Active filter tags ──────────────────────────────────────*/
  function buildTagList() {
    const tags = [];
    const form = $id('fbarForm');
    if (!form) return tags;

    // Location / Keyword
    const loc = getVal('location') || getVal('keyword');
    if (loc) tags.push({ key: 'location', label: `🔍 ${loc}` });

    // Price
    const pMin = getVal('price_min');
    const pMax = getVal('price_max');
    if (pMin !== '' || pMax !== '') {
      const k = `${pMin},${pMax}`;
      tags.push({ key: 'price', label: PRICE_LABELS[k] || `${pMin}–${pMax}` });
    }

    // Type
    const typeKey = form.dataset.typeKey || 'type';
    const typeVal = getVal(typeKey);
    if (typeVal) {
      tags.push({ key: typeKey, label: _typeLabels[typeVal] || typeVal });
    }

    // Area
    const aMin = getVal('area_min');
    const aMax = getVal('area_max');
    if (aMin !== '' || aMax !== '') {
      const k = `${aMin},${aMax}`;
      tags.push({ key: 'area', label: AREA_LABELS[k] || `${aMin}–${aMax}m²` });
    }

    // Sort (only if not default)
    const sortVal = getVal('sort');
    if (sortVal && sortVal !== 'newest') {
      tags.push({ key: 'sort', label: SORT_LABELS[sortVal] || sortVal });
    }

    // Amenities
    Object.entries(AMENITY_LABELS).forEach(([k, l]) => {
      if (getVal(k) === '1') tags.push({ key: k, label: l });
    });

    // Status
    const status = getVal('status');
    if (status) tags.push({ key: 'status', label: STATUS_LABELS[status] || status });

    // District
    const districtEl = $qs('[data-district-name]');
    if (getVal('district_id') && districtEl) {
      tags.push({ key: 'district_id', label: `📍 ${districtEl.dataset.districtName}` });
    }

    return tags;
  }

  function renderTags() {
    const container = $id('fbarTags');
    if (!container) return;

    const tags = buildTagList();
    container.innerHTML = '';

    tags.forEach(({ key, label }) => {
      const span = document.createElement('span');
      span.className = 'ftag';
      span.dataset.key = key;
      span.innerHTML = `${escHtml(label)}<button type="button" class="ftag-x" aria-label="Xoá lọc"><i class="bi bi-x"></i></button>`;
      span.querySelector('.ftag-x').addEventListener('click', () => removeTag(key));
      container.appendChild(span);
    });

    // Update mobile badge
    const badge = $id('fbarMobileBadge');
    if (badge) {
      badge.textContent = tags.length;
      badge.style.display = tags.length > 0 ? 'flex' : 'none';
    }
  }

  function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Remove a single tag ─────────────────────────────────────*/
  function removeTag(key) {
    const tag = $qs(`.ftag[data-key="${key}"]`);
    if (tag) {
      tag.classList.add('removing');
      tag.addEventListener('animationend', () => {
        // clear value
        clearKey(key);
        renderTags();
        syncPillsFromState();
        submitForm();
      }, { once: true });
    } else {
      clearKey(key);
      renderTags();
      syncPillsFromState();
      submitForm();
    }
  }

  function clearKey(key) {
    const form = $id('fbarForm');
    switch (key) {
      case 'location':
      case 'keyword':
        setVal('location', ''); setVal('keyword', '');
        const inp = $qs('.fpill-search input');
        if (inp) inp.value = '';
        break;
      case 'price':
        setVal('price_min', ''); setVal('price_max', '');
        break;
      case 'type':
      case 'room_type':
        setVal('type', ''); setVal('room_type', '');
        $qsa('.fchip[data-type]').forEach(el => el.classList.remove('selected'));
        break;
      case 'area':
        setVal('area_min', ''); setVal('area_max', '');
        break;
      case 'sort':
        setVal('sort', 'newest');
        break;
      case 'status':
        setVal('status', '');
        $qsa('[data-status]').forEach(el => el.classList.remove('selected'));
        break;
      case 'district_id':
        setVal('district_id', '');
        setVal('ward_id', '');
        break;
      default:
        // amenity
        setVal(key, '');
        $qsa(`[data-amenity="${key}"]`).forEach(el => el.classList.remove('selected'));
        break;
    }
  }

  /* ── Sync pill labels/states from form values ─────────────── */
  function syncPillsFromState() {
    // Price
    const pMin = getVal('price_min'), pMax = getVal('price_max');
    const pKey = `${pMin},${pMax}`;
    const pLabel = PRICE_LABELS[pKey] || 'Giá';
    updatePill('price', pLabel, pMin !== '' || pMax !== '');
    $qsa('.fdrop-option[data-price]').forEach(el => {
      el.classList.toggle('selected', el.dataset.price === pKey);
    });

    // Type
    const form = $id('fbarForm');
    const typeKey = form ? (form.dataset.typeKey || 'type') : 'type';
    const typeVal = getVal(typeKey);
    updatePill('type', typeVal ? (_typeLabels[typeVal] || typeVal) : 'Loại phòng', !!typeVal);
    $qsa('.fchip[data-type]').forEach(el => el.classList.toggle('selected', el.dataset.type === typeVal));

    // Area
    const aMin = getVal('area_min'), aMax = getVal('area_max');
    const aKey = `${aMin},${aMax}`;
    updatePill('area', AREA_LABELS[aKey] || 'Diện tích', aMin !== '' || aMax !== '');

    // Sort
    const sortVal = getVal('sort');
    updatePill('sort', SORT_LABELS[sortVal] || 'Sắp xếp', !!sortVal && sortVal !== 'newest');

    // More
    updateMoreBadge();
  }

  /* ── Submit (form GET or URL update) ─────────────────────────*/
  function submitForm() {
    const form = $id('fbarForm');
    if (!form) return;
    showProgress();
    form.submit();
  }

  function showProgress() {
    const bar = $id('fbarProgress');
    if (bar) { bar.classList.add('active'); }
  }

  /* ── Reset all ───────────────────────────────────────────────*/
  function resetAll() {
    const form = $id('fbarForm');
    if (!form) return;
    // Clear all hidden inputs
    form.querySelectorAll('input[type="hidden"]').forEach(el => {
      if (el.name === 'sort') el.value = 'newest';
      else el.value = '';
    });
    // Clear search inputs
    $qsa('.fpill-search input').forEach(el => el.value = '');
    // Clear modal inputs
    $qsa('#fmodalSheet input, #fmodalSheet select').forEach(el => el.value = '');
    renderTags();
    syncPillsFromState();
    submitForm();
  }

  /* ── Mobile modal ────────────────────────────────────────────*/
  function openMobile() {
    closeDrop(_openDrop);
    syncModalFromState();
    $id('fmodalBg')?.classList.add('open');
    $id('fmodalSheet')?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeMobile() {
    $id('fmodalBg')?.classList.remove('open');
    $id('fmodalSheet')?.classList.remove('open');
    document.body.style.overflow = '';
  }

  function applyModal() {
    // Read values from modal and write to hidden form inputs
    readModalIntoForm();
    closeMobile();
    renderTags();
    syncPillsFromState();
    submitForm();
  }

  function readModalIntoForm() {
    const modal = $id('fmodalSheet');
    if (!modal) return;
    // All named inputs inside modal → sync to form
    modal.querySelectorAll('[name]').forEach(el => {
      const name = el.name;
      if (!name || el.type === 'button') return;
      if (el.type === 'checkbox') {
        setVal(name, el.checked ? '1' : '');
      } else {
        setVal(name, el.value);
      }
    });
    // Also sync amenity items (visual state → input)
    modal.querySelectorAll('[data-amenity]').forEach(el => {
      setVal(el.dataset.amenity, el.classList.contains('selected') ? '1' : '');
    });
    // status chips in modal
    modal.querySelectorAll('[data-status]').forEach(el => {
      if (el.classList.contains('selected')) setVal('status', el.dataset.status);
    });
    // type chips in modal
    modal.querySelectorAll('.fchip[data-type].selected').forEach(el => {
      const typeKey = ($id('fbarForm') || {}).dataset?.typeKey || 'type';
      setVal(typeKey, el.dataset.type);
    });
  }

  function syncModalFromState() {
    const modal = $id('fmodalSheet');
    if (!modal) return;
    // amenities
    Object.keys(AMENITY_LABELS).forEach(k => {
      modal.querySelectorAll(`[data-amenity="${k}"]`).forEach(el => {
        el.classList.toggle('selected', getVal(k) === '1');
      });
    });
    // status
    const statusVal = getVal('status');
    modal.querySelectorAll('[data-status]').forEach(el => {
      el.classList.toggle('selected', el.dataset.status === statusVal);
    });
    // type
    const form = $id('fbarForm');
    const typeKey = form ? (form.dataset.typeKey || 'type') : 'type';
    const typeVal = getVal(typeKey);
    modal.querySelectorAll('.fchip[data-type]').forEach(el => {
      el.classList.toggle('selected', el.dataset.type === typeVal);
    });
    // keyword/location
    const kwVal = getVal('keyword') || getVal('location');
    const kwInput = modal.querySelector('[name="m_keyword"]');
    if (kwInput) kwInput.value = kwVal;
    // price
    const pMin = modal.querySelector('[name="m_price_min"]');
    const pMax = modal.querySelector('[name="m_price_max"]');
    if (pMin) pMin.value = getVal('price_min');
    if (pMax) pMax.value = getVal('price_max');
    // sort
    const sortSel = modal.querySelector('[name="m_sort"]');
    if (sortSel) sortSel.value = getVal('sort') || 'newest';
    // area
    const aMin = modal.querySelector('[name="m_area_min"]');
    const aMax = modal.querySelector('[name="m_area_max"]');
    if (aMin) aMin.value = getVal('area_min');
    if (aMax) aMax.value = getVal('area_max');
  }

  /* ── Click outside to close dropdowns ───────────────────────*/
  function setupOutsideClick() {
    document.addEventListener('click', (e) => {
      if (!_openDrop) return;
      const pill = $id('fpill-' + _openDrop);
      const drop = $id('fdrop-' + _openDrop);
      if (pill && !pill.contains(e.target) && drop && !drop.contains(e.target)) {
        closeDrop(_openDrop);
      }
    }, true);
  }

  /* ── Search input debounce ───────────────────────────────────*/
  function setupSearchInput() {
    $qsa('.fpill-search input').forEach(input => {
      input.addEventListener('input', () => {
        clearTimeout(_debTimer);
        const key = input.dataset.key || 'location';
        _debTimer = setTimeout(() => {
          setVal(key, input.value.trim());
          setVal(key === 'keyword' ? 'location' : 'keyword', ''); // clear the other
          renderTags();
        }, 600);
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(_debTimer);
          const key = input.dataset.key || 'location';
          setVal(key, input.value.trim());
          renderTags();
          submitForm();
        }
      });
    });
  }

  /* ── Public API (called from Twig via onclick) ───────────────*/
  window.fbarToggle   = toggleDrop;
  window.fbarSetPrice = setPrice;
  window.fbarSetArea  = setArea;
  window.fbarSetType  = setType;
  window.fbarSetSort  = setSort;
  window.fbarSetStatus = setStatus;
  window.fbarToggleAmenity = toggleAmenity;
  window.fbarSetDistrict = setDistrict;
  window.fbarApply    = submitForm;
  window.fbarReset    = resetAll;
  window.fbarOpenMobile  = openMobile;
  window.fbarCloseMobile = closeMobile;
  window.fbarApplyModal  = applyModal;

  /* ── Init ─────────────────────────────────────────────────── */
  function init() {
    const cfg = window.FBAR_CONFIG || {};
    _typeLabels = cfg.typeLabels || {};

    teleportDropdowns();
    setupOutsideClick();
    setupSearchInput();
    renderTags();
    syncPillsFromState();

    // Mobile backdrop click → close
    const bg = $id('fmodalBg');
    if (bg) bg.addEventListener('click', closeMobile);

    // Close progress after navigation restore
    window.addEventListener('pageshow', () => {
      const bar = $id('fbarProgress');
      if (bar) bar.classList.remove('active');
    });
  }

  document.addEventListener('DOMContentLoaded', init);

})();
