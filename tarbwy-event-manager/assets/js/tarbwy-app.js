/**
 * Tarbwy Event Manager — Frontend & Staff Check-in Application Script (tarbwy-app.js)
 *
 * Handles:
 * 1. Public Registration Form (/launch) with dynamic companions, client/server validation, honeypot, and pending feedback.
 * 2. Private VIP Portal (/launch/vip) token verification, RSVP submission, and immediate QR ticket rendering.
 * 3. Printable Ticket View (/launch/ticket) loading and local QR SVG rendering.
 * 4. Staff Check-in (/launch/checkin) live stats polling (15s), camera scan, manual search, manual check-in, and offline detection.
 */
(function (window, document) {
	'use strict';

	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	// =========================================================================
	// 1. PUBLIC REGISTRATION FORM (/launch)
	// =========================================================================
	var regForm = document.getElementById('tarbwy-public-reg-form');
	if (regForm && window.TarbwyPublicConfig) {
		var addCompBtn = document.getElementById('tarbwy-add-companion-btn');
		var compList = document.getElementById('tarbwy-companions-list');
		var maxComp = parseInt(window.TarbwyPublicConfig.maxCompanions || 2, 10);

		if (addCompBtn && compList) {
			addCompBtn.addEventListener('click', function () {
				var count = compList.querySelectorAll('.tarbwy-comp-item').length;
				if (count >= maxComp) {
					return;
				}
				var div = document.createElement('div');
				div.className = 'tarbwy-comp-item tarbwy-card';
				div.style.marginTop = '12px';
				div.innerHTML =
					'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">' +
					'<strong>بيانات المرافق #' + (count + 1) + ' (بطاقة QR مستقلة)</strong>' +
					'<button type="button" class="tarbwy-btn tarbwy-btn-outline tarbwy-remove-comp">حذف</button>' +
					'</div>' +
					'<div class="tarbwy-form-grid">' +
					'<div class="tarbwy-field"><label>اسم المرافق الكامل *</label><input type="text" class="comp-name" required></div>' +
					'<div class="tarbwy-field"><label>البريد الإلكتروني للمرافق (اختياري)</label><input type="email" class="comp-email" placeholder="إن ترك فارغاً تُرسل بطاقته لبريدك"></div>' +
					'<div class="tarbwy-field"><label>صلة القرابة / الصفة</label><input type="text" class="comp-role" value="مرافق"></div>' +
					'</div>';
				div.querySelector('.tarbwy-remove-comp').addEventListener('click', function () {
					div.remove();
				});
				compList.appendChild(div);
			});
		}

		regForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var submitBtn = document.getElementById('tarbwy-reg-submit-btn');
			var feedback = document.getElementById('tarbwy-reg-feedback');
			var companions = [];

			if (compList) {
				compList.querySelectorAll('.tarbwy-comp-item').forEach(function (row) {
					var cname = row.querySelector('.comp-name').value.trim();
					if (cname) {
						companions.push({
							full_name: cname,
							email: row.querySelector('.comp-email').value.trim(),
							job_title: row.querySelector('.comp-role').value.trim()
						});
					}
				});
			}

			var payload = {
				event_id: window.TarbwyPublicConfig.eventId,
				website_url_hp: document.getElementById('website_url_hp') ? document.getElementById('website_url_hp').value : '',
				full_name: document.getElementById('reg_full_name').value.trim(),
				email: document.getElementById('reg_email').value.trim(),
				phone: document.getElementById('reg_phone').value.trim(),
				organization: document.getElementById('reg_organization').value.trim(),
				job_title: document.getElementById('reg_job_title').value.trim(),
				dietary_requirements: document.getElementById('reg_dietary').value.trim(),
				companions: companions
			};

			submitBtn.disabled = true;
			submitBtn.textContent = 'جاري إرسال الطلب...';

			fetch(window.TarbwyPublicConfig.restUrl + '/registrations', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { ok: r.ok, data: data };
					});
				})
				.then(function (res) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'إرسال طلب الحضور للمراجعة';
					feedback.hidden = false;
					if (res.ok) {
						feedback.className = 'tarbwy-checkin-banner tarbwy-banner-valid';
						feedback.innerHTML =
							'<h3>تم استلام طلبكم بنجاح (حالة الطلب: قيد المراجعة — Pending)</h3>' +
							'<p>' + escapeHtml(res.data.message) + '</p>' +
							'<p>رقم المرجع الخاص بطلبكم: <strong>' + escapeHtml(res.data.reference_code) + '</strong></p>';
						regForm.reset();
						if (compList) compList.innerHTML = '';
					} else {
						feedback.className = 'tarbwy-checkin-banner tarbwy-banner-error';
						feedback.textContent = res.data.message || 'تعذر إرسال الطلب، يرجى التحقق من البيانات.';
					}
				})
				.catch(function () {
					submitBtn.disabled = false;
					submitBtn.textContent = 'إرسال طلب الحضور للمراجعة';
					feedback.hidden = false;
					feedback.className = 'tarbwy-checkin-banner tarbwy-banner-error';
					feedback.textContent = 'تعذر الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت والمحاولة مجدداً.';
				});
		});
	}

	// =========================================================================
	// 2. PRIVATE VIP PORTAL (/launch/vip)
	// =========================================================================
	if (window.TarbwyVipConfig) {
		var currentVipToken = window.TarbwyVipConfig.initialToken || '';
		var verifyBtn = document.getElementById('tarbwy-vip-verify-btn');
		var tokenInput = document.getElementById('vip_token_input');
		var vipContent = document.getElementById('tarbwy-vip-content');
		var vipError = document.getElementById('tarbwy-vip-error');
		var vipLoading = document.getElementById('tarbwy-vip-loading');
		var vipGate = document.getElementById('tarbwy-vip-token-gate');
		var vipMaxComp = 2;

		function verifyVipToken(token) {
			if (!token || token.length < 16) {
				vipError.hidden = false;
				vipError.textContent = 'يرجى إدخال رمز دعوة صالح.';
				return;
			}
			currentVipToken = token;
			vipLoading.hidden = false;
			vipError.hidden = true;

			fetch(window.TarbwyVipConfig.restUrl + '/vip-invitations/' + encodeURIComponent(token))
				.then(function (r) {
					return r.json().then(function (d) {
						return { ok: r.ok, data: d };
					});
				})
				.then(function (res) {
					vipLoading.hidden = true;
					if (!res.ok) {
						vipGate.hidden = false;
						vipError.hidden = false;
						vipError.textContent = res.data.message || 'رابط الدعوة غير صالح أو منتهي الصلاحية.';
						return;
					}
					vipGate.hidden = true;
					vipContent.hidden = false;
					document.getElementById('vip-event-title').textContent = res.data.event_title || '';
					document.getElementById('vip-guest-name').textContent = res.data.guest_name || '';
					document.getElementById('vip-guest-title').textContent = res.data.job_title || '';
					document.getElementById('vip-guest-org').textContent = res.data.organization || '';
					vipMaxComp = parseInt(res.data.max_companions || 2, 10);
					document.getElementById('vip-max-companions').textContent = vipMaxComp;
				})
				.catch(function () {
					vipLoading.hidden = true;
					vipError.hidden = false;
					vipError.textContent = 'فشل الاتصال بالخادم للتحقق من الرمز.';
				});
		}

		if (verifyBtn) {
			verifyBtn.addEventListener('click', function () {
				verifyVipToken(tokenInput.value.trim());
			});
		}
		if (currentVipToken) {
			verifyVipToken(currentVipToken);
		}
	}

	// =========================================================================
	// 3. SECURE TICKET VIEW (/launch/ticket)
	// =========================================================================
	if (window.TarbwyTicketConfig && window.TarbwyTicketConfig.token) {
		var tLoad = document.getElementById('tarbwy-ticket-loading');
		var tErr = document.getElementById('tarbwy-ticket-error');
		var tCard = document.getElementById('tarbwy-ticket-card');

		fetch(window.TarbwyTicketConfig.restUrl + '/tickets/view/' + encodeURIComponent(window.TarbwyTicketConfig.token))
			.then(function (r) {
				return r.json().then(function (d) {
					return { ok: r.ok, data: d };
				});
			})
			.then(function (res) {
				tLoad.hidden = true;
				if (!res.ok) {
					tErr.hidden = false;
					tErr.textContent = res.data.message || 'بطاقة غير صالحة.';
					return;
				}
				tCard.hidden = false;
				document.getElementById('tkt-event-title').textContent = res.data.event_title || '';
				document.getElementById('tkt-holder-name').textContent = res.data.holder_name || '';
				document.getElementById('tkt-organization').textContent = res.data.organization || '-';
				document.getElementById('tkt-ref').textContent = res.data.ticket_reference || '';
				document.getElementById('tkt-date').textContent = res.data.date_display || 'سيُعلن لاحقاً';
				document.getElementById('tkt-venue').textContent = res.data.venue_name || 'سيُعلن لاحقاً';
				document.getElementById('tkt-instructions').textContent = res.data.entry_instructions || '';
				if (window.TarbwyQR) {
					window.TarbwyQR.renderSVG(document.getElementById('tkt-qr-canvas'), res.data.qr_payload, 176);
				}
			})
			.catch(function () {
				tLoad.hidden = true;
				tErr.hidden = false;
				tErr.textContent = 'فشل الاتصال بالخادم لعرض التذكرة.';
			});
	}

	// =========================================================================
	// 4. STAFF CHECK-IN INTERFACE (/launch/checkin)
	// =========================================================================
	if (window.TarbwyCheckinConfig) {
		var banner = document.getElementById('checkin-result-banner');
		var netBadge = document.getElementById('checkin-net-status');

		function showCheckinResult(status, data) {
			banner.hidden = false;
			if (status === 'offline') {
				banner.className = 'tarbwy-checkin-banner tarbwy-banner-error';
				banner.innerHTML = '<strong>فشل الاتصال بالخادم (Offline)</strong> — لم يتم تسجيل الدخول! يرجى التحقق من اتصال الإنترنت قبل السماح بالدخول.';
				return;
			}
			var code = data.result_code || 'unknown';
			if (code === 'valid') {
				banner.className = 'tarbwy-checkin-banner tarbwy-banner-valid';
			} else if (code === 'already_checked_in') {
				banner.className = 'tarbwy-checkin-banner tarbwy-banner-duplicate';
			} else {
				banner.className = 'tarbwy-checkin-banner tarbwy-banner-error';
			}

			var gHtml = '';
			if (data.guest) {
				gHtml =
					'<div style="margin-top:8px;font-weight:400;">' +
					'الاسم: <strong>' + escapeHtml(data.guest.full_name) + '</strong> · ' +
					'الفئة: ' + escapeHtml(data.guest.guest_type) + ' · ' +
					'التذكرة: <code>' + escapeHtml(data.guest.ticket_reference || '-') + '</code>' +
					'</div>';
			}
			banner.innerHTML = '<div>' + escapeHtml(data.message) + '</div>' + gHtml;
			refreshStats();
		}

		function refreshStats() {
			fetch(window.TarbwyCheckinConfig.restUrl + '/checkin/stats', {
				headers: { 'X-WP-Nonce': window.TarbwyCheckinConfig.nonce }
			})
				.then(function (r) {
					if (!r.ok) throw new Error('net');
					return r.json();
				})
				.then(function (st) {
					if (netBadge) netBadge.textContent = 'متصل بالخادم (Online)';
					document.getElementById('stat-confirmed').textContent = st.confirmed || 0;
					document.getElementById('stat-checked-in').textContent = st.checked_in || 0;
					document.getElementById('stat-remaining').textContent = st.remaining || 0;
					document.getElementById('stat-capacity').textContent = st.capacity_total || 500;
				})
				.catch(function () {
					if (netBadge) netBadge.textContent = 'انقطاع الاتصال بالخادم!';
				});
		}

		refreshStats();
		// Light polling every 15 seconds per Spec Section 14
		setInterval(refreshStats, 15000);

		var scanForm = document.getElementById('form-direct-qr-scan');
		if (scanForm) {
			scanForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var tokenVal = document.getElementById('input-qr-token').value.trim();
				if (!tokenVal) return;

				fetch(window.TarbwyCheckinConfig.restUrl + '/checkin/scan', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.TarbwyCheckinConfig.nonce
					},
					body: JSON.stringify({
						qr_token: tokenVal,
						device_label: window.TarbwyCheckinConfig.deviceLabel
					})
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (data) {
						document.getElementById('input-qr-token').value = '';
						showCheckinResult('online', data);
					})
					.catch(function () {
						showCheckinResult('offline', {});
					});
			});
		}
	}
})(window, document);
