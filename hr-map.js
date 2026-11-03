// Shared Leaflet helpers for HomeRent (Tayabas City). Needs Leaflet loaded first.
window.HRMap = (function () {
  var COLORS = { boarding_house: '#0b3a8c', apartment: '#2563eb', dormitory: '#3b82f6', bedspace: '#7cb8ff' };

  function loadBoundary() {
    return fetch('map-boundary.php', { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('boundary'); return r.json();
    });
  }
  function ringContains(ring, lng, lat) {
    var inside = false;
    for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
      var xi = ring[i][0], yi = ring[i][1], xj = ring[j][0], yj = ring[j][1];
      if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / ((yj - yi) || 1e-12) + xi)) inside = !inside;
    }
    return inside;
  }
  function inside(geom, lat, lng) {
    var polys = geom.type === 'Polygon' ? [geom.coordinates] : geom.coordinates;
    return polys.some(function (rings) {
      if (!ringContains(rings[0], lng, lat)) return false;
      for (var k = 1; k < rings.length; k++) if (ringContains(rings[k], lng, lat)) return false;
      return true;
    });
  }

  function baseMap(el, boundary) {
    var map = L.map(el, { zoomControl: true, maxBoundsViscosity: 1.0 });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    var outline = L.geoJSON(boundary.geometry, { style: { color: '#1e40af', weight: 2, fillColor: '#3b82f6', fillOpacity: 0.04 }, interactive: false }).addTo(map);
    var bounds = outline.getBounds();
    map.fitBounds(bounds);
    map.setMaxBounds(bounds.pad(0.08));
    map.setMinZoom(Math.max(8, map.getBoundsZoom(bounds.pad(0.08))));
    setTimeout(function () { map.invalidateSize(); }, 200);
    return map;
  }

  function pinIcon(color) {
    return L.divIcon({ className: 'hr-pin', html: '<span style="background:' + color + '"></span>', iconSize: [26, 26], iconAnchor: [13, 26], popupAnchor: [0, -24] });
  }
  function mk(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }

  function popupFor(p) {
    var box = mk('div', 'pin-popup');
    if (p.image) { var img = mk('img'); img.src = p.image; img.alt = p.name; box.appendChild(img); } else box.appendChild(mk('div', 'noimg'));
    box.appendChild(mk('h4', null, p.name));
    box.appendChild(mk('p', 'p-price', p.price_label + ' / month'));
    box.appendChild(mk('p', null, p.type_label + ' · ' + (p.rooms_available || 0) + ' open'));
    box.appendChild(mk('p', null, p.address));
    box.appendChild(mk('p', null, 'Availability: ' + p.availability.charAt(0).toUpperCase() + p.availability.slice(1)));
    var a = mk('a', 'btn btn-small', 'View details'); a.href = 'property-details.php?id=' + p.id; box.appendChild(a);
    return box;
  }

  // Student map: markers from map-data.php, optional filter form
  function studentMap(opts) {
    var statusEl = opts.statusEl ? document.getElementById(opts.statusEl) : null;
    return loadBoundary().then(function (b) {
      var map = baseMap(document.getElementById(opts.el), b);
      var layer = L.layerGroup().addTo(map);
      function params() {
        var q = new URLSearchParams(opts.extraParams || '');
        if (opts.form) {
          var f = document.getElementById(opts.form);
          [].forEach.call(f.querySelectorAll('input[name="type[]"]:checked'), function (c) { q.append('type[]', c.value); });
          if (f.min_price.value) q.set('min_price', f.min_price.value);
          if (f.max_price.value) q.set('max_price', f.max_price.value);
          if (f.open_rooms.checked) q.set('open_rooms', '1');
        }
        return q.toString();
      }
      function reload() {
        if (opts.form) {
          var f = document.getElementById(opts.form);
          var mn = parseFloat(f.min_price.value), mx = parseFloat(f.max_price.value);
          if (!isNaN(mn) && !isNaN(mx) && mn > mx) { if (statusEl) statusEl.textContent = 'Minimum price cannot be higher than the maximum price.'; return; }
          if (f.querySelectorAll('input[name="type[]"]:checked').length === 0) { layer.clearLayers(); if (statusEl) statusEl.textContent = 'Select at least one property type.'; return; }
        }
        if (statusEl) statusEl.textContent = 'Loading properties...';
        fetch('map-data.php?' + params(), { credentials: 'same-origin' }).then(function (r) {
          if (!r.ok) throw new Error('data'); return r.json();
        }).then(function (d) {
          layer.clearLayers();
          d.properties.forEach(function (p) {
            if (!inside(b.geometry, p.latitude, p.longitude)) return;
            L.marker([p.latitude, p.longitude], { icon: pinIcon(COLORS[p.property_type] || '#2563eb'), title: p.name })
              .bindPopup(popupFor(p)).addTo(layer);
          });
          if (statusEl) statusEl.textContent = d.count === 0
            ? 'No available rentals with a map pin match these filters yet.'
            : d.count + ' available rental' + (d.count === 1 ? '' : 's') + ' on the map.';
        }).catch(function () { if (statusEl) statusEl.textContent = 'Could not load properties. Please refresh.'; });
      }
      if (opts.form) {
        var f = document.getElementById(opts.form);
        f.addEventListener('change', reload);
        f.addEventListener('submit', function (e) { e.preventDefault(); reload(); });
        var reset = f.querySelector('[data-reset]');
        if (reset) reset.addEventListener('click', function () {
          [].forEach.call(f.querySelectorAll('input[name="type[]"]'), function (c) { c.checked = true; });
          f.min_price.value = ''; f.max_price.value = ''; f.open_rooms.checked = false; reload();
        });
      }
      reload();
      return map;
    }).catch(function () { if (statusEl) statusEl.textContent = 'The map could not be loaded. Check your internet connection.'; });
  }

  // Landlord pin picker: click or drag, only inside the boundary
  function picker(opts) {
    var latIn = document.getElementById(opts.lat), lngIn = document.getElementById(opts.lng), out = document.getElementById(opts.readout);
    function say(msg, cls) { out.textContent = msg; out.className = 'pin-readout ' + (cls || ''); }
    return loadBoundary().then(function (b) {
      var map = baseMap(document.getElementById(opts.el), b);
      var marker = null, last = null;
      function place(lat, lng) {
        if (!inside(b.geometry, lat, lng)) { say('That spot is outside Tayabas City. Pick a location inside the blue outline.', 'err'); return false; }
        if (!marker) {
          marker = L.marker([lat, lng], { draggable: true, icon: pinIcon('#0b3a8c') }).addTo(map);
          marker.on('dragend', function () {
            var p = marker.getLatLng();
            if (!place(p.lat, p.lng)) marker.setLatLng(last);
          });
        } else marker.setLatLng([lat, lng]);
        last = [lat, lng];
        latIn.value = lat.toFixed(7); lngIn.value = lng.toFixed(7);
        say('Pinned at ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '. Drag the pin to adjust.', 'ok');
        return true;
      }
      map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng); });
      var la = parseFloat(latIn.value), ln = parseFloat(lngIn.value);
      if (!isNaN(la) && !isNaN(ln)) {
        if (place(la, ln)) map.setView([la, ln], Math.max(map.getZoom(), 16));
        else say('The saved pin is outside Tayabas City. Please choose a new location.', 'err');
      } else say('No pin yet. Click the map to place one.');
      return map;
    }).catch(function () { say('The map could not be loaded. Check your internet connection.', 'err'); });
  }

  return { studentMap: studentMap, picker: picker, COLORS: COLORS };
})();
