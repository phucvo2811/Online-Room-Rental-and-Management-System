/**
 * map-picker.js  —  Leaflet + OpenStreetMap (no API key required)
 * ─────────────────────────────────────────────────────────────────────────
 * Required DOM ids:
 *   #mapPickerCanvas    – map container div
 *   #mapLoadingMsg      – shown before map initialises
 *   #mapSearchInput     – address search text input
 *   #mapClearBtn        – button to clear the current location
 *   #mapPickerError     – validation error message
 *   #mapCoordDisplay    – wrapper shown when coordinates are set
 *   #mapCoordText       – text showing "lat, lng"
 *   #mapAddrText        – text showing formatted address
 *   #mapOpenLink        – wrapper for the "Open in Google Maps" link
 *   #mapGoogleLink      – the <a> tag for the Google Maps link
 *   #inputLatitude      – hidden <input> submitted with the form
 *   #inputLongitude     – hidden <input> submitted with the form
 *   #inputMapAddress    – hidden <input> submitted with the form
 * ─────────────────────────────────────────────────────────────────────────
 */

(function () {
    'use strict';

    /* ── Default centre: Cần Thơ, Vietnam ─────────────────────────────── */
    var DEFAULT_LAT  = 10.0452;
    var DEFAULT_LNG  = 105.7469;
    var DEFAULT_ZOOM = 12;
    var SELECTED_ZOOM = 17;

    var map, marker;

    /* ── Helpers ──────────────────────────────────────────────────────── */
    function el(id) { return document.getElementById(id); }

    function setHidden(lat, lng, address) {
        el('inputLatitude').value   = lat     !== null ? lat     : '';
        el('inputLongitude').value  = lng     !== null ? lng     : '';
        el('inputMapAddress').value = address !== null ? address : '';
    }

    function showCoords(lat, lng, address) {
        var coordDisplay = el('mapCoordDisplay');
        var coordText    = el('mapCoordText');
        var addrText     = el('mapAddrText');
        var openLink     = el('mapOpenLink');
        var googleLink   = el('mapGoogleLink');
        var clearBtn     = el('mapClearBtn');

        if (!coordDisplay) return;

        coordDisplay.style.display = 'flex';
        coordText.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        if (addrText)   addrText.textContent   = address || '';
        if (openLink)   openLink.style.display  = '';
        if (googleLink) googleLink.href = 'https://www.google.com/maps?q=' + lat + ',' + lng;
        if (clearBtn)   clearBtn.style.display  = '';

        var errEl = el('mapPickerError');
        if (errEl) errEl.style.display = 'none';
        var canvas = el('mapPickerCanvas');
        if (canvas) canvas.style.borderColor = '';
    }

    function hideCoords() {
        var coordDisplay = el('mapCoordDisplay');
        var openLink     = el('mapOpenLink');
        var clearBtn     = el('mapClearBtn');
        if (coordDisplay) coordDisplay.style.display = 'none';
        if (openLink)     openLink.style.display      = 'none';
        if (clearBtn)     clearBtn.style.display      = 'none';
    }

    function placeMarker(lat, lng) {
        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                var pos = e.target.getLatLng();
                setHidden(pos.lat, pos.lng, null);
                reverseGeocode(pos.lat, pos.lng, function (address) {
                    setHidden(pos.lat, pos.lng, address);
                    showCoords(pos.lat, pos.lng, address);
                    if (el('mapSearchInput')) el('mapSearchInput').value = address;
                });
            });
        } else {
            marker.setLatLng([lat, lng]);
        }
        map.panTo([lat, lng]);
    }

    function reverseGeocode(lat, lng, callback) {
        var url = 'https://nominatim.openstreetmap.org/reverse?format=json'
                + '&lat=' + lat + '&lon=' + lng + '&accept-language=vi';
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { callback(d.display_name || ''); })
            .catch(function ()  { callback(''); });
    }

    function searchAddress(query, callback) {
        var url = 'https://nominatim.openstreetmap.org/search?format=json'
                + '&q=' + encodeURIComponent(query)
                + '&limit=1&accept-language=vi&countrycodes=vn';
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.length > 0) {
                    callback(parseFloat(d[0].lat), parseFloat(d[0].lon), d[0].display_name);
                } else {
                    callback(null, null, null);
                }
            })
            .catch(function () { callback(null, null, null); });
    }

    /* ── Public: clear selected location ─────────────────────────────── */
    window.mapPickerClearLocation = function () {
        setHidden(null, null, null);
        hideCoords();
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
        if (el('mapSearchInput')) el('mapSearchInput').value = '';
        map.setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);
    };

    /* ── Public: validate (called from wizard step validation) ───────── */
    window.validateMapPicker = function () {
        var lat   = el('inputLatitude')  && el('inputLatitude').value;
        var lng   = el('inputLongitude') && el('inputLongitude').value;
        var valid = lat && lng && !isNaN(parseFloat(lat)) && !isNaN(parseFloat(lng));
        var errEl  = el('mapPickerError');
        var canvas = el('mapPickerCanvas');
        if (!valid) {
            if (errEl)  errEl.style.display   = '';
            if (canvas) canvas.style.borderColor = '#dc3545';
        } else {
            if (errEl)  errEl.style.display   = 'none';
            if (canvas) canvas.style.borderColor = '';
        }
        return !!valid;
    };

    /* ── Main init ────────────────────────────────────────────────────── */
    window.initMapPicker = function () {
        var canvas = el('mapPickerCanvas');
        if (!canvas || typeof L === 'undefined') return;
        if (canvas._leaflet_id) return; /* already initialised — guard against double-call */

        var loadingMsg = el('mapLoadingMsg');
        if (loadingMsg) loadingMsg.style.display = 'none';

        var existingLat  = parseFloat((el('inputLatitude')   || {}).value || '');
        var existingLng  = parseFloat((el('inputLongitude')  || {}).value || '');
        var existingAddr = (el('inputMapAddress') || {}).value || '';
        var hasExisting  = !isNaN(existingLat) && !isNaN(existingLng);

        var centerLat = hasExisting ? existingLat : DEFAULT_LAT;
        var centerLng = hasExisting ? existingLng : DEFAULT_LNG;
        var zoom      = hasExisting ? SELECTED_ZOOM : DEFAULT_ZOOM;

        map = L.map(canvas).setView([centerLat, centerLng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        if (hasExisting) {
            placeMarker(existingLat, existingLng);
            showCoords(existingLat, existingLng, existingAddr);
        }

        map.on('click', function (e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;
            placeMarker(lat, lng);
            setHidden(lat, lng, null);
            showCoords(lat, lng, '');
            reverseGeocode(lat, lng, function (address) {
                setHidden(lat, lng, address);
                showCoords(lat, lng, address);
                if (el('mapSearchInput')) el('mapSearchInput').value = address;
            });
        });

        /* Search input (Enter or button click → Nominatim) */
        var searchInput = el('mapSearchInput');
        if (searchInput) {
            function doSearch() {
                var q = searchInput.value.trim();
                if (!q) return;
                searchAddress(q, function (lat, lng, address) {
                    if (lat !== null) {
                        map.setView([lat, lng], SELECTED_ZOOM);
                        placeMarker(lat, lng);
                        setHidden(lat, lng, address);
                        showCoords(lat, lng, address);
                        searchInput.value = address;
                    } else {
                        var errEl = el('mapPickerError');
                        if (errEl) {
                            errEl.textContent = 'Không tìm thấy địa điểm. Hãy thử từ khoá khác.';
                            errEl.style.display = '';
                            setTimeout(function () { errEl.style.display = 'none'; }, 4000);
                        }
                    }
                });
            }
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
            });
            /* Wire up a search button if present next to the input */
            var searchGroup = searchInput.closest('.input-group');
            if (searchGroup) {
                var btn = searchGroup.querySelector('button[data-action="map-search"]');
                if (btn) btn.addEventListener('click', doSearch);
            }
        }
    };

    /* Auto-init — handles both cases: script runs before OR after DOMContentLoaded */
    function autoInit() {
        if (typeof L !== 'undefined') { initMapPicker(); }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

}());
