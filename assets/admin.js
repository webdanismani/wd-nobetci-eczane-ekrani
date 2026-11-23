/* WD Nöbetçi Eczane Ekranı · Web Danışmanı */
(function () {
	'use strict';
	var doc = document.documentElement;
	var KEY = doc.getAttribute('data-slug') || 'wdeczane';

	function $(s, c) { return (c || document).querySelector(s); }
	function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }

	/* Tema */
	$$('[data-theme-toggle]').forEach(function (b) {
		b.addEventListener('click', function () {
			var next = doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			doc.setAttribute('data-theme', next);
			try { localStorage.setItem(KEY + '-theme', next); } catch (e) {}
		});
	});

	/* Bildirimler */
	$$('.toast').forEach(function (t) {
		setTimeout(function () { t.remove(); }, t.classList.contains('is-error') ? 8000 : 4500);
	});

	/* Mobil menü */
	var side = $('[data-side]');
	var menu = $('[data-menu]');
	if (side && menu) {
		menu.addEventListener('click', function (e) { e.stopPropagation(); side.classList.toggle('is-open'); });
		document.addEventListener('click', function (e) {
			if (side.classList.contains('is-open') && !side.contains(e.target)) side.classList.remove('is-open');
		});
	}

	/* Onay penceresi */
	var modal = $('[data-modal]');
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-confirm]');
		if (!btn || !modal || btn.dataset.confirmed) return;
		e.preventDefault();
		$('[data-modal-text]', modal).textContent = btn.dataset.confirm;
		modal.hidden = false;
		var yes = $('[data-modal-yes]', modal), no = $('[data-modal-no]', modal);
		yes.focus();
		function close() { modal.hidden = true; yes.onclick = no.onclick = null; document.removeEventListener('keydown', key); }
		function key(ev) { if (ev.key === 'Escape') close(); }
		document.addEventListener('keydown', key);
		no.onclick = close;
		yes.onclick = function () {
			close();
			btn.dataset.confirmed = '1';
			if (btn.form) { btn.form.requestSubmit ? btn.form.requestSubmit(btn) : btn.form.submit(); } else { btn.click(); }
		};
	});

	/* Kopyalama */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-copy]');
		if (!b) return;
		var text = b.parentNode.querySelector('code').textContent;
		var label = b.querySelector('span');
		var old = label ? label.textContent : '';
		function ok() {
			if (label) label.textContent = doc.lang === 'tr' ? 'Kopyalandı' : 'Copied';
			setTimeout(function () { if (label) label.textContent = old; }, 1500);
		}
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(ok);
		} else {
			var ta = document.createElement('textarea');
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); ok(); } catch (err) {}
			ta.remove();
		}
	});

	/* Liste arama */
	$$('[data-filter-input]').forEach(function (fi) {
		fi.addEventListener('input', function () {
			var q = fi.value.toLocaleLowerCase(doc.lang || 'tr').trim();
			var any = false;
			$$('[data-filter-text]').forEach(function (r) {
				var show = !q || r.dataset.filterText.indexOf(q) !== -1;
				r.hidden = !show;
				if (show) any = true;
			});
			var empty = $('[data-filter-empty]');
			if (empty) empty.hidden = any;
		});
	});

	/* Kurulum: veritabanı türü */
	var mysql = $('[data-mysql]');
	$$('[data-db-switch]').forEach(function (r) {
		r.addEventListener('change', function () { if (mysql) mysql.hidden = r.value !== 'mysql'; });
	});

	/* Koşullu alanlar: data-when="alan=değer1,değer2" */
	$$('[data-when]').forEach(function (el) {
		var parts = el.dataset.when.split('=');
		var form = el.closest('form') || document;
		var inputs = $$('[name="' + parts[0] + '"]', form);
		var vals = parts[1].split(',');
		function sync() {
			var cur = '';
			inputs.forEach(function (i) {
				if ((i.type === 'radio' || i.type === 'checkbox')) { if (i.checked) cur = i.value; } else cur = i.value;
			});
			el.hidden = vals.indexOf(cur) === -1;
		}
		inputs.forEach(function (i) { i.addEventListener('change', sync); });
		sync();
	});

	/* Ekran önizlemesi: 1920x1080 sayfayı kutuya sığdırır */
	function fit() {
		$$('.preview iframe').forEach(function (f) {
			var box = f.parentNode;
			var w = f.offsetWidth;
			f.style.transform = 'scale(' + (box.clientWidth / w) + ')';
		});
	}
	if ($('.preview')) {
		fit();
		window.addEventListener('resize', fit);
	}

	/* Değişen satırları işaretle (toplu kaydetmede kullanıcı neyin değiştiğini görür) */
	$$('[data-dirty-form]').forEach(function (form) {
		form.addEventListener('input', function (e) {
			var tr = e.target.closest('tr');
			if (tr) tr.classList.add('is-dirty');
			var bar = $('[data-dirty-bar]', form);
			if (bar) bar.hidden = false;
		});
	});
})();
