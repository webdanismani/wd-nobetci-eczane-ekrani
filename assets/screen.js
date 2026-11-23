/* WD Nöbetçi Eczane Ekranı – TV ekranı · Web Danışmanı · webdanismani.com */
(function () {
	'use strict';

	var root = document.body;
	var wrap = document.querySelector('[data-screen]');
	var api = root.getAttribute('data-api');
	var CACHE = 'wdeczane-last';
	var state = null, skew = 0, tzOffset = 10800, lastOk = Date.now(), timer = null, lastDay = '';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function $(s) { return wrap.querySelector(s); }

	var PHONE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 6.1 6.1l1.4-2.3L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5A16.5 16.5 0 0 1 3.5 5.6 1.5 1.5 0 0 1 5 4Z"/></svg>';
	function arrow(deg) {
		if (deg === null || deg === undefined) return '';
		return '<svg class="e-arrow" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10.2" fill="none" stroke="currentColor" stroke-width="1.6" opacity=".35"/>' +
			'<path d="M12 4.6 16.6 18 12 15.4 7.4 18Z" fill="currentColor" transform="rotate(' + deg + ' 12 12)"/></svg>';
	}

	/* QR: yol tarifi bağlantısı (Google Haritalar) */
	function qr(url) {
		if (!window.qrcode || !url) return '';
		try {
			var q = window.qrcode(0, 'M');
			q.addData(url);
			q.make();
			return '<div class="q">' + q.createSvgTag({ cellSize: 4, margin: 0, scalable: true }) + '</div>';
		} catch (e) { return ''; }
	}

	function featured(p, showQr) {
		return '<section class="e-feat">' +
			'<span class="e-kicker">Size en yakın nöbetçi eczane</span>' +
			'<h3>' + esc(p.name) + '</h3>' +
			'<div><p class="e-addr">' + esc([p.address, p.district].filter(Boolean).join(' · ')) + '</p>' +
			(p.phone ? '<p class="e-phone" style="margin-top:1.4vh">' + PHONE + esc(p.phone) + '</p>' : '') + '</div>' +
			'<div class="e-feat-foot"><div class="e-way">' +
			(p.km ? '<strong>' + arrow(p.deg) + esc(p.km) + '</strong>' : '') +
			(p.dir || p.walk ? '<span>' + esc([p.dir, p.walk ? 'yaklaşık ' + p.walk : ''].filter(Boolean).join(' · ')) + '</span>' : '') + '</div>' +
			(showQr ? '<div class="e-qr">' + qr(p.map) + '<small>Yol tarifi için okutun</small></div>' : '') +
			'</div></section>';
	}

	function selfCard(d) {
		return '<section class="e-feat is-self">' +
			'<span class="e-kicker">Bugün nöbetçiyiz</span>' +
			'<h3>Eczanemiz nöbetçidir</h3>' +
			'<p class="e-big">Kapı kapalıysa lütfen zili çalın veya bizi arayın. Reçeteniz ve acil ilaç ihtiyacınız için hizmetinizdeyiz.</p>' +
			(d.self.phone ? '<p class="e-phone">' + PHONE + esc(d.self.phone) + '</p>' : '') +
			'</section>';
	}

	function row(p, showQr) {
		return '<li class="e-row' + (showQr ? '' : ' no-qr') + '">' +
			'<div><h3>' + esc(p.name) + (p.district ? '<small>' + esc(p.district) + '</small>' : '') + '</h3>' +
			(p.address ? '<p class="e-addr">' + esc(p.address) + '</p>' : '') +
			(p.phone ? '<p class="e-phone">' + PHONE + esc(p.phone) + '</p>' : '') + '</div>' +
			'<div class="e-dist">' + (p.km ? '<strong>' + arrow(p.deg) + esc(p.km) + '</strong><span>' + esc([p.dir, p.walk].filter(Boolean).join(' · ')) + '</span>' : '') + '</div>' +
			(showQr ? qr(p.map) : '') + '</li>';
	}

	function render(d) {
		var sc = d.screen;
		root.className = 'scr theme-' + sc.theme + (isPortrait() ? ' is-portrait' : '');
		$('.e-head h1').textContent = d.self.name || '';
		var st = $('.e-status');
		st.className = 'e-status' + (d.status.open ? '' : ' is-closed');
		st.innerHTML = '<i></i>' + esc(d.status.text);
		$('.e-title p').innerHTML = esc(d.duty.date) + ' · <b>' + esc(d.duty.until) + '</b>';
		$('.e-place').textContent = d.place || '';

		var body = $('.e-body');
		var list = d.list.slice();
		var html = '';
		if (!list.length && !d.self.on_duty) {
			html = '<div class="e-empty"><div><strong>Nöbetçi eczane bilgisi girilmemiş</strong>Lütfen 112 Acil Çağrı Merkezi\'ni veya il sağlık müdürlüğünün nöbetçi eczane hattını arayın.</div></div>';
			body.classList.add('is-single');
		} else {
			body.classList.remove('is-single');
			var feat = d.self.on_duty ? selfCard(d) : featured(list.shift(), sc.qr);
			var rows = list.map(function (p) { return row(p, sc.qr); }).join('');
			html = feat + (rows ? '<ol class="e-list' + (list.length > 4 ? ' is-dense' : '') + '">' + rows + '</ol>' : '');
			if (!rows) body.classList.add('is-single');
		}
		body.innerHTML = html;

		$('.e-note').textContent = sc.note || '';
		var tk = $('.e-ticker');
		tk.hidden = !sc.ticker;
		var sp = tk.querySelector('span');
		if (sp.textContent !== sc.ticker) sp.textContent = sc.ticker || '';
		sp.style.setProperty('--dur', Math.max(18, (sc.ticker || '').length * .3) + 's');
		$('.e-note').hidden = !!sc.ticker;
	}

	function isPortrait() { return window.innerHeight > window.innerWidth * 1.05; }
	function local() { return new Date(Date.now() + skew + tzOffset * 1000); }
	function two(n) { return ('0' + n).slice(-2); }

	function clock() {
		var t = local();
		$('.e-clock').textContent = two(t.getUTCHours()) + ':' + two(t.getUTCMinutes());
		$('.e-src').textContent = 'Güncellendi ' + (function () { var u = new Date(lastOk + skew + tzOffset * 1000); return two(u.getUTCHours()) + ':' + two(u.getUTCMinutes()); })();
	}

	function apply(d) {
		if (d.now) skew = d.now * 1000 - Date.now();
		if (typeof d.tz_offset === 'number') tzOffset = d.tz_offset;
		state = d;
		lastDay = d.duty.date;
		render(d);
		clock();
	}

	function offline(on) {
		var el = document.querySelector('.e-offline');
		if (on && !el) { el = document.createElement('div'); el.className = 'e-offline'; document.body.appendChild(el); }
		if (el) {
			if (on) el.textContent = 'Bağlantı yok · son bilgiler gösteriliyor';
			else el.remove();
		}
	}

	function poll() {
		var x = new XMLHttpRequest();
		x.open('GET', api + (api.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now());
		x.timeout = 15000;
		x.onload = function () {
			var d = null;
			try { d = JSON.parse(x.responseText); } catch (e) {}
			if (x.status === 200 && d && d.ok) {
				lastOk = Date.now();
				offline(false);
				apply(d);
				try { localStorage.setItem(CACHE, JSON.stringify(d)); } catch (e) {}
			} else offline(true);
			schedule();
		};
		x.onerror = x.ontimeout = function () { offline(true); schedule(); };
		x.send();
	}
	// Liste ve açık/kapalı durumu dakikada bir yenilenir.
	function schedule() { clearTimeout(timer); timer = setTimeout(poll, 60000); }

	var initial = null;
	try { initial = JSON.parse(document.getElementById('initial').textContent); } catch (e) {}
	if (!initial || !initial.ok) { try { initial = JSON.parse(localStorage.getItem(CACHE)); } catch (e) {} }
	if (initial && initial.ok) apply(initial);
	window.addEventListener('resize', function () { if (state) render(state); });
	setInterval(clock, 1000);
	schedule();
	setInterval(function () {
		var t = local();
		if (t.getUTCHours() === 4 && t.getUTCMinutes() === 0 && Date.now() - lastOk < 600000) location.reload();
	}, 60000);
})();
