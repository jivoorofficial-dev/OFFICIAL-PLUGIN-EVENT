/**
 * Tarbwy Event Manager — Frontend, VIP Portal, Staff Check-in & Admin Script (tarbwy-app.js)
 *
 * Handles:
 * 0. Mobile Navigation Drawer, Staff Role Sync, Scoped Email Iframe Preview Switcher, and Inline Edit Toggles.
 * 1. Public Registration Form (/launch) with dynamic companions, client/server validation, honeypot, and pending feedback.
 * 2. Private VIP Portal (/launch/vip) token verification, companion addition, RSVP submission, and immediate QR ticket rendering.
 * 3. Printable Ticket View (/launch/ticket) loading and local QR SVG rendering.
 * 4. Staff Check-in (/launch/checkin) live stats polling (15s), camera/token scan, manual search, manual check-in, and offline detection.
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
	// 0. MOBILE NAVIGATION DRAWER TOGGLE (/launch)
	// =========================================================================
	var mobileToggle = document.getElementById('tarbwy-mobile-menu-toggle');
	var mobileDrawer = document.getElementById('tarbwy-mobile-drawer');
	if (mobileToggle && mobileDrawer) {
		mobileToggle.addEventListener('click', function () {
			var isHidden = mobileDrawer.hasAttribute('hidden');
			if (isHidden) {
				mobileDrawer.removeAttribute('hidden');
				mobileToggle.setAttribute('aria-expanded', 'true');
			} else {
				mobileDrawer.setAttribute('hidden', '');
				mobileToggle.setAttribute('aria-expanded', 'false');
			}
		});
		mobileDrawer.querySelectorAll('a').forEach(function (link) {
			link.addEventListener('click', function () {
				mobileDrawer.setAttribute('hidden', '');
				mobileToggle.setAttribute('aria-expanded', 'false');
			});
		});
	}

	// =========================================================================
	// 0.1 STAFF & PERMISSIONS ROLE DROPDOWN SYNC (wp-admin)
	// =========================================================================
	var staffUserSelect = document.getElementById('tarbwy-staff-user-select');
	var staffRoleSelect = document.getElementById('tarbwy-staff-role-select');
	if (staffUserSelect && staffRoleSelect) {
		staffUserSelect.addEventListener('change', function () {
			var selectedOpt = staffUserSelect.options[staffUserSelect.selectedIndex];
			if (selectedOpt) {
				var currentRole = selectedOpt.getAttribute('data-tarbwy-role');
				if (currentRole) {
					staffRoleSelect.value = currentRole;
				}
			}
		});
	}

	// =========================================================================
	// 0.2 EMAIL TEMPLATE PREVIEW VIEWPORT SWITCHER (Desktop / Mobile / Plain-Text)
	// =========================================================================
	var prevDesktopBtn = document.getElementById('tarbwy-prev-btn-desktop');
	var prevMobileBtn = document.getElementById('tarbwy-prev-btn-mobile');
	var prevPlainBtn = document.getElementById('tarbwy-prev-btn-plain');
	var prevHtmlWrap = document.getElementById('tarbwy-email-preview-html-wrap');
	var prevPlainWrap = document.getElementById('tarbwy-email-preview-plain-wrap');
	var prevIframe = document.getElementById('tarbwy-email-preview-iframe');

	function resizePreviewIframe() {
		if (!prevIframe) return;
		try {
			var doc = prevIframe.contentDocument || (prevIframe.contentWindow && prevIframe.contentWindow.document);
			if (doc && doc.body && doc.body.scrollHeight > 200) {
				prevIframe.style.height = (doc.body.scrollHeight + 32) + 'px';
			}
		} catch (e) {
			// Ignore cross-origin or sandboxed resize warnings
		}
	}

	if (prevIframe) {
		prevIframe.addEventListener('load', resizePreviewIframe);
		setTimeout(resizePreviewIframe, 300);
	}

	if (prevDesktopBtn && prevMobileBtn && prevPlainBtn && prevHtmlWrap && prevPlainWrap) {
		prevDesktopBtn.addEventListener('click', function () {
			prevHtmlWrap.hidden = false;
			prevPlainWrap.hidden = true;
			prevHtmlWrap.style.maxWidth = '640px';
			prevDesktopBtn.className = 'button button-primary';
			prevMobileBtn.className = 'button';
			prevPlainBtn.className = 'button';
			setTimeout(resizePreviewIframe, 260);
		});
		prevMobileBtn.addEventListener('click', function () {
			prevHtmlWrap.hidden = false;
			prevPlainWrap.hidden = true;
			prevHtmlWrap.style.maxWidth = '375px';
			prevDesktopBtn.className = 'button';
			prevMobileBtn.className = 'button button-primary';
			prevPlainBtn.className = 'button';
			setTimeout(resizePreviewIframe, 260);
		});
		prevPlainBtn.addEventListener('click', function () {
			prevHtmlWrap.hidden = true;
			prevPlainWrap.hidden = false;
			prevDesktopBtn.className = 'button';
			prevMobileBtn.className = 'button';
			prevPlainBtn.className = 'button button-primary';
		});
	}

	// =========================================================================
	// 0.3 INLINE ADMIN EDIT ROW TOGGLES & COPY VIP CODE BUTTONS
	// =========================================================================
	document.querySelectorAll('[data-toggle-target]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var targetId = btn.getAttribute('data-toggle-target');
			var targetEl = targetId ? document.getElementById(targetId) : null;
			if (targetEl) {
				targetEl.hidden = !targetEl.hidden;
			}
		});
	});

	function copyTextToClipboard(text, onSuccess) {
		var clean = String(text || '').trim();
		if (!clean) return;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(clean).then(function () {
				if (onSuccess) onSuccess(clean);
			}).catch(function () {
				fallbackCopyText(clean, onSuccess);
			});
		} else {
			fallbackCopyText(clean, onSuccess);
		}
	}

	function fallbackCopyText(text, onSuccess) {
		try {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.top = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			if (onSuccess) onSuccess(text);
		} catch (e) {
			// Ignore clipboard errors
		}
	}

	document.querySelectorAll('.tarbwy-btn-copy-vip-code').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var code = btn.getAttribute('data-copy-vip-code') || '';
			var origText = btn.textContent;
			copyTextToClipboard(code, function () {
				btn.textContent = '✓ تم النسخ!';
				setTimeout(function () {
					btn.textContent = origText;
				}, 2000);
			});
		});
	});

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
	// 2. PRIVATE VIP PORTAL (/launch/vip) — Personal Invitation Code & Token Support
	// =========================================================================
	if (window.TarbwyVipConfig) {
		var currentVipToken = window.TarbwyVipConfig.initialToken || '';
		var currentPersonalCode = '';
		var verifyForm = document.getElementById('tarbwy-vip-verify-form');
		var verifyBtn = document.getElementById('btn-vip-verify-submit') || document.getElementById('tarbwy-vip-verify-btn');
		var tokenInput = document.getElementById('vip_token_input');
		var vipContent = document.getElementById('tarbwy-vip-rsvp-card') || document.getElementById('tarbwy-vip-content');
		var vipError = document.getElementById('tarbwy-vip-verify-error') || document.getElementById('tarbwy-vip-error');
		var vipLoading = document.getElementById('tarbwy-vip-loading');
		var vipGate = document.getElementById('tarbwy-vip-token-gate');
		var vipRsvpForm = document.getElementById('tarbwy-vip-rsvp-form');
		var vipCompBox = document.getElementById('vip-companions-section') || document.getElementById('tarbwy-vip-companions-box');
		var vipCompList = document.getElementById('vip-companions-list') || document.getElementById('tarbwy-vip-companions-list');
		var vipAddCompBtn = document.getElementById('btn-add-vip-companion') || document.getElementById('tarbwy-vip-add-comp-btn');
		var vipFeedback = document.getElementById('tarbwy-vip-rsvp-feedback') || document.getElementById('tarbwy-vip-feedback');
		var vipTicketsOutput = document.getElementById('tarbwy-vip-tickets-result') || document.getElementById('tarbwy-vip-tickets-output');
		var vipTicketsCardsGrid = document.getElementById('vip-tickets-cards-grid');
		var vipCopyToast = document.getElementById('tarbwy-vip-copy-toast');
		var vipCopyToastMsg = document.getElementById('tarbwy-vip-copy-toast-msg');
		var btnVipCopyInput = document.getElementById('btn-vip-copy-input-code');
		var btnVipPasteCode = document.getElementById('btn-vip-paste-code');
		var btnVipCopyPersonal = document.getElementById('btn-vip-copy-personal-code');
		var btnVipChangeCode = document.getElementById('btn-vip-change-code');
		var vipPersonalCodeDisplay = document.getElementById('vip-personal-code-display');
		var vipMaxComp = 2;

		function normalizeVipInputClient(raw) {
			var val = String(raw || '').trim();
			if (!val) return '';
			// If user pasted a full invitation URL, extract v= or code= query param automatically
			if (val.indexOf('http://') === 0 || val.indexOf('https://') === 0 || val.indexOf('?') !== -1) {
				var m = val.match(/[?&](?:v|code|token)=([A-Za-z0-9\-_]+)/i);
				if (m && m[1]) {
					val = m[1];
				}
			}
			// Format 8-char hex or VIPXXXXXXXX into VIP-XXXX-XXXX for clean display
			var cleanAlnum = val.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
			if (cleanAlnum.indexOf('VIP') === 0 && cleanAlnum.length === 11) {
				var hex8 = cleanAlnum.substring(3);
				return 'VIP-' + hex8.substring(0, 4) + '-' + hex8.substring(4, 8);
			}
			if (/^[A-F0-9]{8}$/.test(cleanAlnum)) {
				return 'VIP-' + cleanAlnum.substring(0, 4) + '-' + cleanAlnum.substring(4, 8);
			}
			return val;
		}

		function showVipCopyToast(codeText) {
			if (!vipCopyToast) return;
			if (vipCopyToastMsg) {
				vipCopyToastMsg.textContent = 'تم نسخ رمز الدعوة الشخصي (' + codeText + ') إلى الحافظة بنجاح!';
			}
			vipCopyToast.hidden = false;
			setTimeout(function () {
				vipCopyToast.hidden = true;
			}, 3800);
		}

		function renderIssuedVipTickets(tickets) {
			if (!vipTicketsOutput || !tickets || !tickets.length) return;
			vipTicketsOutput.hidden = false;
			var cardsHtml = '';
			if (!vipTicketsCardsGrid) {
				cardsHtml += '<h3 style="margin-bottom:14px;color:#0F172A;">بطاقات الدخول الفردية الصادرة (QR Passes):</h3>';
			}
			tickets.forEach(function (tkt, idx) {
				cardsHtml +=
					'<div class="tarbwy-card" style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;border:1px solid #A7F3D0;background:#F0FDF4;">' +
					'<div>' +
					'<strong style="font-size:16px;color:#064E3B;">' + escapeHtml(tkt.guest_name) + '</strong><br>' +
					'<small style="color:#0F291E;">رقم التذكرة: <code style="font-weight:700;">' + escapeHtml(tkt.ticket_reference) + '</code></small><br>' +
					'<a href="' + escapeHtml(tkt.ticket_url) + '" target="_blank" class="tarbwy-btn tarbwy-btn-gold" style="margin-top:8px;display:inline-block;">فتح وطباعة البطاقة</a>' +
					'</div>' +
					'<div id="vip-qr-slot-' + idx + '" style="background:#fff;padding:8px;border:1px solid #cbd5e1;border-radius:8px;"></div>' +
					'</div>';
			});
			if (vipTicketsCardsGrid) {
				vipTicketsCardsGrid.innerHTML = cardsHtml;
			} else {
				vipTicketsOutput.innerHTML = cardsHtml;
			}
			if (window.TarbwyQR) {
				tickets.forEach(function (tkt, idx) {
					var slot = document.getElementById('vip-qr-slot-' + idx);
					var qrPayload = tkt.ticket_reference || tkt.qr_payload || tkt.qr_token;
					if (slot && qrPayload) {
						window.TarbwyQR.renderSVG(slot, qrPayload, 120);
					}
				});
			}
		}

		function verifyVipToken(rawInput) {
			var token = normalizeVipInputClient(rawInput);
			if (tokenInput && token) {
				tokenInput.value = token;
			}
			if (!token || token.length < 6) {
				if (vipError) {
					vipError.hidden = false;
					vipError.textContent = 'يرجى إدخال رمز الدعوة الشخصي (مثال: VIP-8F4A-2C91) أو فتح رابط الدعوة.';
				}
				return;
			}
			currentVipToken = token;
			if (vipLoading) vipLoading.hidden = false;
			if (vipError) vipError.hidden = true;
			if (verifyBtn) {
				verifyBtn.disabled = true;
				verifyBtn.textContent = 'جاري التحقق من الرمز...';
			}

			fetch(window.TarbwyVipConfig.restUrl + '/vip-invitations/' + encodeURIComponent(token))
				.then(function (r) {
					return r.json().then(function (d) {
						return { ok: r.ok, data: d };
					});
				})
				.then(function (res) {
					if (vipLoading) vipLoading.hidden = true;
					if (verifyBtn) {
						verifyBtn.disabled = false;
						verifyBtn.textContent = 'التحقق من الرمز وفتح الدعوة الخاصة';
					}
					if (!res.ok) {
						if (vipGate) vipGate.hidden = false;
						if (vipContent) vipContent.hidden = true;
						if (vipError) {
							vipError.hidden = false;
							vipError.textContent = res.data.message || 'رمز الدعوة الشخصي غير صالح أو منتهي الصلاحية.';
						}
						return;
					}
					if (vipGate) vipGate.hidden = true;
					if (vipContent) vipContent.hidden = false;
					currentPersonalCode = res.data.personal_code || token;
					if (vipPersonalCodeDisplay) {
						vipPersonalCodeDisplay.textContent = currentPersonalCode;
					}
					if (tokenInput) {
						tokenInput.value = currentPersonalCode;
					}
					var elEventTitle = document.getElementById('vip-event-title');
					var elGuestName = document.getElementById('vip-guest-name');
					var elGuestTitle = document.getElementById('vip-guest-title');
					var elGuestOrg = document.getElementById('vip-guest-org');
					var elMaxComp = document.getElementById('vip-max-companions');
					if (elEventTitle) elEventTitle.textContent = res.data.event_title || '';
					if (elGuestName) elGuestName.textContent = res.data.guest_name || '';
					if (elGuestTitle) elGuestTitle.textContent = res.data.job_title || '';
					if (elGuestOrg) {
						var orgParts = [];
						if (res.data.job_title) orgParts.push(res.data.job_title);
						if (res.data.organization) orgParts.push(res.data.organization);
						elGuestOrg.textContent = orgParts.join(' — ');
					}
					vipMaxComp = parseInt(res.data.max_companions || 2, 10);
					if (elMaxComp) elMaxComp.textContent = vipMaxComp;
					if (vipCompBox) {
						vipCompBox.hidden = (vipMaxComp <= 0);
					}
					if (res.data.issued_tickets && res.data.issued_tickets.length > 0) {
						renderIssuedVipTickets(res.data.issued_tickets);
					}
					if (window.TarbwyVipConfig.autoCopy && currentPersonalCode) {
						copyTextToClipboard(currentPersonalCode, function (copied) {
							showVipCopyToast(copied);
						});
						window.TarbwyVipConfig.autoCopy = false;
					}
				})
				.catch(function () {
					if (vipLoading) vipLoading.hidden = true;
					if (verifyBtn) {
						verifyBtn.disabled = false;
						verifyBtn.textContent = 'التحقق من الرمز وفتح الدعوة الخاصة';
					}
					if (vipError) {
						vipError.hidden = false;
						vipError.textContent = 'فشل الاتصال بالخادم للتحقق من رمز الدعوة الشخصي.';
					}
				});
		}

		if (verifyForm && tokenInput) {
			verifyForm.addEventListener('submit', function (e) {
				e.preventDefault();
				verifyVipToken(tokenInput.value);
			});
		} else if (verifyBtn && tokenInput) {
			verifyBtn.addEventListener('click', function () {
				verifyVipToken(tokenInput.value);
			});
		}

		if (tokenInput) {
			tokenInput.addEventListener('paste', function () {
				setTimeout(function () {
					tokenInput.value = normalizeVipInputClient(tokenInput.value);
				}, 20);
			});
		}

		if (btnVipCopyInput && tokenInput) {
			btnVipCopyInput.addEventListener('click', function () {
				var valToCopy = normalizeVipInputClient(tokenInput.value) || currentPersonalCode;
				if (!valToCopy) {
					if (vipError) {
						vipError.hidden = false;
						vipError.textContent = 'لا يوجد رمز في الحقل لنسخه حالياً.';
					}
					return;
				}
				copyTextToClipboard(valToCopy, function (copied) {
					showVipCopyToast(copied);
				});
			});
		}

		if (btnVipPasteCode && tokenInput) {
			btnVipPasteCode.addEventListener('click', function () {
				if (navigator.clipboard && navigator.clipboard.readText) {
					navigator.clipboard.readText().then(function (clipText) {
						if (clipText) {
							var norm = normalizeVipInputClient(clipText);
							tokenInput.value = norm;
							verifyVipToken(norm);
						}
					}).catch(function () {
						tokenInput.focus();
					});
				} else {
					tokenInput.focus();
				}
			});
		}

		if (btnVipCopyPersonal) {
			btnVipCopyPersonal.addEventListener('click', function () {
				var codeToCopy = currentPersonalCode || (vipPersonalCodeDisplay ? vipPersonalCodeDisplay.textContent : '');
				copyTextToClipboard(codeToCopy, function (copied) {
					showVipCopyToast(copied);
					var orig = btnVipCopyPersonal.innerHTML;
					btnVipCopyPersonal.innerHTML = '✓ تم نسخ الرمز!';
					setTimeout(function () {
						btnVipCopyPersonal.innerHTML = orig;
					}, 2200);
				});
			});
		}

		if (btnVipChangeCode) {
			btnVipChangeCode.addEventListener('click', function () {
				if (vipContent) vipContent.hidden = true;
				if (vipGate) vipGate.hidden = false;
				if (tokenInput) {
					tokenInput.focus();
					tokenInput.select();
				}
			});
		}

		// Toggle companions section when switching between Attend and Decline
		var vipDecisionRadios = document.querySelectorAll('input[name="decision"], input[name="vip_decision"]');
		vipDecisionRadios.forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (vipCompBox) {
					vipCompBox.hidden = (radio.value !== 'attend' || vipMaxComp <= 0);
				}
			});
		});

		// Add VIP Companion Row
		if (vipAddCompBtn && vipCompList) {
			vipAddCompBtn.addEventListener('click', function () {
				var count = vipCompList.querySelectorAll('.tarbwy-vip-comp-item').length;
				if (count >= vipMaxComp) {
					return;
				}
				var div = document.createElement('div');
				div.className = 'tarbwy-vip-comp-item tarbwy-card';
				div.style.marginTop = '12px';
				div.innerHTML =
					'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">' +
					'<strong>بيانات مرافق VIP #' + (count + 1) + ' (بطاقة QR مستقلة)</strong>' +
					'<button type="button" class="tarbwy-btn tarbwy-btn-outline tarbwy-remove-vip-comp">حذف</button>' +
					'</div>' +
					'<div class="tarbwy-form-grid">' +
					'<div class="tarbwy-field"><label>اسم المرافق الكامل *</label><input type="text" class="vip-comp-name" required></div>' +
					'<div class="tarbwy-field"><label>البريد الإلكتروني للمرافق (اختياري)</label><input type="email" class="vip-comp-email" placeholder="إن ترك فارغاً تُرسل بطاقته لبريدك"></div>' +
					'</div>';
				div.querySelector('.tarbwy-remove-vip-comp').addEventListener('click', function () {
					div.remove();
				});
				vipCompList.appendChild(div);
			});
		}

		// Submit VIP RSVP Form
		if (vipRsvpForm) {
			vipRsvpForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var submitBtn = document.getElementById('btn-submit-vip-rsvp') || document.getElementById('tarbwy-vip-submit-btn');
				var checkedDecision = document.querySelector('input[name="decision"]:checked') || document.querySelector('input[name="vip_decision"]:checked');
				var decisionVal = checkedDecision ? checkedDecision.value : 'attend';
				var companions = [];

				if (decisionVal === 'attend' && vipCompList) {
					vipCompList.querySelectorAll('.tarbwy-vip-comp-item').forEach(function (row) {
						var cname = row.querySelector('.vip-comp-name').value.trim();
						if (cname) {
							companions.push({
								full_name: cname,
								email: row.querySelector('.vip-comp-email').value.trim()
							});
						}
					});
				}

				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = 'جاري معالجة الرد وإصدار البطاقات...';
				}

				fetch(window.TarbwyVipConfig.restUrl + '/vip-invitations/' + encodeURIComponent(currentVipToken) + '/rsvp', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						decision: decisionVal,
						companions: companions
					})
				})
					.then(function (r) {
						return r.json().then(function (d) {
							return { ok: r.ok, data: d };
						});
					})
					.then(function (res) {
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = 'اعتماد الرد وإصدار التذاكر فوراً';
						}
						if (vipFeedback) {
							vipFeedback.hidden = false;
							vipFeedback.className = res.ok ? 'tarbwy-checkin-banner tarbwy-banner-valid' : 'tarbwy-checkin-banner tarbwy-banner-error';
							vipFeedback.textContent = res.data.message || '';
						}
						if (res.ok && res.data.issued_tickets && vipTicketsOutput) {
							vipRsvpForm.hidden = true;
							renderIssuedVipTickets(res.data.issued_tickets);
						}
					})
					.catch(function () {
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = 'اعتماد الرد وإصدار التذاكر فوراً';
						}
						if (vipFeedback) {
							vipFeedback.hidden = false;
							vipFeedback.className = 'tarbwy-checkin-banner tarbwy-banner-error';
							vipFeedback.textContent = 'تعذر الاتصال بالخادم لإرسال الرد.';
						}
					});
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
	// 4. STAFF CHECK-IN TERMINAL (/launch/checkin) — Android App UI, Camera QR & Search
	// =========================================================================
	if (window.TarbwyCheckinConfig) {
		var banner = document.getElementById('checkin-result-banner');
		var netBadge = document.getElementById('checkin-network-status') || document.getElementById('checkin-net-status');
		var netLabel = document.getElementById('checkin-network-label') || document.getElementById('checkin-net-label');
		var soundToggleBtn = document.getElementById('btn-checkin-sound-toggle');
		var soundLabel = document.getElementById('checkin-sound-label') || document.getElementById('label-checkin-sound');
		var refreshStatsBtn = document.getElementById('btn-refresh-stats');
		var progressTextEl = document.getElementById('stat-progress-text');
		var progressPercentEl = document.getElementById('stat-progress-pct') || document.getElementById('checkin-progress-percent');
		var progressFillEl = document.getElementById('stat-progress-bar') || document.getElementById('checkin-progress-fill');
		var recentHistoryList = document.getElementById('checkin-recent-history-list');
		var recentHistoryCountEl = document.getElementById('checkin-history-count');
		var btnClearHistory = document.getElementById('btn-clear-scan-history');

		var soundEnabled = true;
		var sessionHistory = [];
		var activeCameraSession = null;
		var isScanProcessing = false;
		var activeLookupFilter = 'pending_checkin';
		var searchDebounceTimer = null;

		function guestTypeArabic(type) {
			if (type === 'vip') return 'كبار الشخصيات VIP';
			if (type === 'companion') return 'مرافق';
			return 'ضيف عام';
		}

		function rsvpStatusArabic(status) {
			if (status === 'confirmed') return 'مؤكد';
			if (status === 'pending_response') return 'بانتظار الرد';
			if (status === 'declined') return 'معتذر';
			return status || 'غير مؤكد';
		}

		// Sound Toggle
		if (soundToggleBtn) {
			soundToggleBtn.addEventListener('click', function () {
				soundEnabled = !soundEnabled;
				soundToggleBtn.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
				if (window.TarbwyQR && typeof window.TarbwyQR.setAudioMuted === 'function') {
					window.TarbwyQR.setAudioMuted(!soundEnabled);
				}
				if (soundLabel) {
					soundLabel.textContent = soundEnabled ? '🔊 الصوت مفعل' : '🔇 صامت';
				}
			});
		}

		function setOnlineState(isOnline) {
			if (!netBadge) return;
			netBadge.className = 'tarbwy-net-badge ' + (isOnline ? 'tarbwy-net-online' : 'tarbwy-net-offline');
			if (netLabel) {
				netLabel.textContent = isOnline ? 'متصل بالخادم' : 'غير متصل';
			}
		}

		window.addEventListener('online', function () {
			setOnlineState(true);
			refreshStats();
		});
		window.addEventListener('offline', function () {
			setOnlineState(false);
		});

		function renderSessionHistory() {
			if (recentHistoryCountEl) {
				recentHistoryCountEl.textContent = String(sessionHistory.length);
			}
			if (btnClearHistory) {
				btnClearHistory.hidden = (sessionHistory.length === 0);
			}
			if (!recentHistoryList) return;
			if (!sessionHistory.length) {
				recentHistoryList.innerHTML = '<div class="tarbwy-checkin-empty-state">لم يتم تسجيل أي عمليات مسح في هذه الجلسة بعد.</div>';
				return;
			}
			var html = '';
			sessionHistory.forEach(function (item) {
				var badgeColor = item.code === 'valid' ? '#059669' : (item.code === 'already_checked_in' ? '#D97706' : '#DC2626');
				var badgeLabel = item.code === 'valid' ? '✓ دخول ناجح' : (item.code === 'already_checked_in' ? '⚠ مسجل مسبقاً' : '✕ مرفوض');
				html +=
					'<div class="tarbwy-checkin-history-item">' +
					'<div>' +
					'<strong>' + escapeHtml(item.name) + '</strong>' +
					(item.ticket ? ' <code style="font-size:11px;color:#065F46;">' + escapeHtml(item.ticket) + '</code>' : '') +
					'<div style="font-size:12px;color:#64748B;">' + escapeHtml(item.msg) + '</div>' +
					'</div>' +
					'<div style="text-align:left;">' +
					'<span style="display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;color:#fff;background:' + badgeColor + ';">' + badgeLabel + '</span>' +
					'<div style="font-size:11px;color:#94A3B8;margin-top:3px;">' + escapeHtml(item.time) + '</div>' +
					'</div>' +
					'</div>';
			});
			recentHistoryList.innerHTML = html;
		}

		function pushSessionHistory(code, data) {
			var nowStr = new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
			var guestName = (data && data.guest && data.guest.full_name) ? data.guest.full_name : (data.message || 'عملية مسح');
			var ticketRef = (data && data.guest && data.guest.ticket_reference) ? data.guest.ticket_reference : '';
			sessionHistory.unshift({
				code: code,
				name: guestName,
				ticket: ticketRef,
				msg: data.message || '',
				time: nowStr
			});
			if (sessionHistory.length > 15) {
				sessionHistory.pop();
			}
			renderSessionHistory();
		}

		if (btnClearHistory) {
			btnClearHistory.addEventListener('click', function () {
				sessionHistory = [];
				renderSessionHistory();
			});
		}

		function showCheckinResult(status, data) {
			if (!banner) return;
			banner.hidden = false;
			banner.removeAttribute('hidden');
			if (status === 'offline') {
				if (navigator.onLine === false) {
					setOnlineState(false);
				}
				if (soundEnabled && window.TarbwyQR && window.TarbwyQR.playScanFeedback) {
					window.TarbwyQR.playScanFeedback('error');
				}
				banner.className = 'tarbwy-checkin-result-toast tarbwy-banner-error';
				banner.innerHTML =
					'<div class="tarbwy-checkin-toast-head">' +
					'<span class="tarbwy-checkin-toast-icon">✕</span>' +
					'<div><strong>تعذر الاتصال بالخادم لإتمام التحقق</strong><p>لم يتم تسجيل الدخول! يرجى التأكد من اتصال الإنترنت والمحاولة مرة أخرى.</p></div>' +
					'</div>';
				return;
			}

			setOnlineState(true);
			var code = (data && data.result_code) ? data.result_code : 'unknown';
			var iconChar = '✕';
			if (code === 'valid') {
				banner.className = 'tarbwy-checkin-result-toast tarbwy-banner-valid';
				iconChar = '✓';
				if (soundEnabled && window.TarbwyQR && window.TarbwyQR.playScanFeedback) {
					window.TarbwyQR.playScanFeedback('valid');
				}
			} else if (code === 'already_checked_in') {
				banner.className = 'tarbwy-checkin-result-toast tarbwy-banner-duplicate';
				iconChar = '⚠';
				if (soundEnabled && window.TarbwyQR && window.TarbwyQR.playScanFeedback) {
					window.TarbwyQR.playScanFeedback('duplicate');
				}
			} else {
				banner.className = 'tarbwy-checkin-result-toast tarbwy-banner-error';
				iconChar = '✕';
				if (soundEnabled && window.TarbwyQR && window.TarbwyQR.playScanFeedback) {
					window.TarbwyQR.playScanFeedback('error');
				}
			}

			var gHtml = '';
			if (data && data.guest) {
				gHtml =
					'<div class="tarbwy-checkin-toast-guest">' +
					'<span>الاسم: <strong>' + escapeHtml(data.guest.full_name) + '</strong></span>' +
					'<span>الفئة: <strong>' + escapeHtml(guestTypeArabic(data.guest.guest_type)) + '</strong></span>' +
					(data.guest.organization ? '<span>الجهة: ' + escapeHtml(data.guest.organization) + '</span>' : '') +
					'<span>التذكرة: <code>' + escapeHtml(data.guest.ticket_reference || '-') + '</code></span>' +
					'</div>';
			}

			banner.innerHTML =
				'<div class="tarbwy-checkin-toast-head">' +
				'<span class="tarbwy-checkin-toast-icon">' + iconChar + '</span>' +
				'<div style="flex:1;">' +
				'<div class="tarbwy-checkin-toast-title">' + escapeHtml((data && data.message) ? data.message : 'نتيجة التحقق') + '</div>' +
				gHtml +
				'</div>' +
				'<button type="button" class="tarbwy-checkin-toast-close" aria-label="إغلاق">✕</button>' +
				'</div>';

			var closeBtn = banner.querySelector('.tarbwy-checkin-toast-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function () {
					banner.hidden = true;
					banner.setAttribute('hidden', '');
				});
			}

			pushSessionHistory(code, data || {});
			refreshStats();
		}

		function refreshStats() {
			fetch(window.TarbwyCheckinConfig.restUrl + '/checkin/stats', {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': window.TarbwyCheckinConfig.nonce }
			})
				.then(function (r) {
					// Receiving any HTTP response from the server confirms the connection is online
					setOnlineState(true);
					if (!r.ok) return null;
					return r.json();
				})
				.then(function (st) {
					if (!st) return;
					var confirmed = parseInt(st.confirmed || 0, 10);
					var checkedIn = parseInt(st.checked_in || 0, 10);
					var remaining = parseInt(st.remaining || 0, 10);
					var capacity = parseInt(st.capacity_total || 500, 10);

					var elConf = document.getElementById('stat-confirmed');
					var elCheck = document.getElementById('stat-checked-in');
					var elRem = document.getElementById('stat-remaining');
					var elCap = document.getElementById('stat-capacity');
					if (elConf) elConf.textContent = confirmed;
					if (elCheck) elCheck.textContent = checkedIn;
					if (elRem) elRem.textContent = remaining;
					if (elCap) elCap.textContent = capacity;

					var pct = confirmed > 0 ? Math.min(100, Math.round((checkedIn / confirmed) * 100)) : 0;
					if (progressTextEl) {
						progressTextEl.textContent = 'نسبة الحضور الفعلي (' + checkedIn + ' / ' + confirmed + ')';
					}
					if (progressPercentEl) {
						progressPercentEl.textContent = pct + '%';
					}
					if (progressFillEl) {
						progressFillEl.style.width = pct + '%';
					}
				})
				.catch(function () {
					if (navigator.onLine === false) {
						setOnlineState(false);
					}
				});
		}

		if (refreshStatsBtn) {
			refreshStatsBtn.addEventListener('click', function () {
				refreshStats();
				executeAttendeeLookup();
			});
		}

		refreshStats();
		setInterval(refreshStats, 15000);

		// Camera QR Scanner Controls (Android & iOS Safari Compatible)
		var btnToggleCamera = document.getElementById('btn-toggle-camera');
		var btnSwitchCamera = document.getElementById('btn-switch-camera');
		var btnToggleTorch = document.getElementById('btn-toggle-torch');
		var btnPhotoQrScan = document.getElementById('btn-photo-qr-scan');
		var inputPhotoQr = document.getElementById('input-photo-qr');
		var cameraBoxEl = document.getElementById('checkin-camera-box');
		var videoEl = document.getElementById('checkin-video') || document.getElementById('qr-video-preview');
		var cameraStatePill = document.getElementById('camera-state-pill');
		var cameraStatusText = document.getElementById('camera-status-text') || document.getElementById('checkin-camera-status-pill');
		var scanErrorEl = document.getElementById('camera-error-box') || document.getElementById('checkin-scan-error');

		// Submit QR Token or Scanned Payload to Server
		function submitQrScanToServer(rawQrPayload) {
			var cleanPayload = String(rawQrPayload || '').trim();
			if (!cleanPayload || isScanProcessing) return;
			isScanProcessing = true;

			if (cameraStatusText) {
				cameraStatusText.textContent = 'جاري التحقق من الرمز عبر الخادم...';
			}

			fetch(window.TarbwyCheckinConfig.restUrl + '/checkin/scan', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.TarbwyCheckinConfig.nonce
				},
				body: JSON.stringify({
					qr_token: cleanPayload,
					device_label: window.TarbwyCheckinConfig.deviceLabel || 'Mobile-Scanner'
				})
			})
				.then(function (r) {
					setOnlineState(true);
					return r.json();
				})
				.then(function (data) {
					isScanProcessing = false;
					var qrInput = document.getElementById('input-qr-token');
					if (qrInput) qrInput.value = '';
					if (cameraStatusText && activeCameraSession) {
						cameraStatusText.textContent = 'الكاميرا نشطة — وجّه الرمز داخل الإطار';
					}
					showCheckinResult('online', data);
					executeAttendeeLookup();
				})
				.catch(function () {
					isScanProcessing = false;
					if (cameraStatusText && activeCameraSession) {
						cameraStatusText.textContent = 'الكاميرا نشطة — وجّه الرمز داخل الإطار';
					}
					showCheckinResult('offline', {});
				});
		}

		// Direct QR Token Form
		var scanForm = document.getElementById('form-qr-scan') || document.getElementById('form-direct-qr-scan');
		if (scanForm) {
			scanForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var tokenInputEl = document.getElementById('input-qr-token');
				var tokenVal = tokenInputEl ? tokenInputEl.value.trim() : '';
				if (!tokenVal) return;
				submitQrScanToServer(tokenVal);
			});
		}

		var isCameraInitializing = false;
		var cameraInitWatchdogTimer = null;

		function clearCameraWatchdog() {
			if (cameraInitWatchdogTimer) {
				clearTimeout(cameraInitWatchdogTimer);
				cameraInitWatchdogTimer = null;
			}
		}

		function showCameraErrorDiagnostic(errMsg) {
			if (!scanErrorEl) return;
			scanErrorEl.hidden = false;
			scanErrorEl.removeAttribute('hidden');
			scanErrorEl.style.display = 'block';
			scanErrorEl.innerHTML =
				'<strong>⚠️ تعذر تشغيل الكاميرا المباشرة:</strong> ' +
				escapeHtml(errMsg || 'يرجى التأكد من السماح بصلاحية الكاميرا في المتصفح والمحاولة مجدداً.') +
				'<div style="margin-top:8px;font-size:13px;color:#78350F;">💡 بديل فوري: يمكنك الضغط على زر <strong>«🖼️ التقاط صورة لرمز QR»</strong> لفتح كاميرا الهاتف والتقاط صورة الرمز مباشرة، أو البحث بالاسم أدناه.</div>';
		}

		function hideCameraErrorDiagnostic() {
			if (!scanErrorEl) return;
			scanErrorEl.hidden = true;
			scanErrorEl.setAttribute('hidden', '');
			scanErrorEl.style.display = 'none';
			scanErrorEl.innerHTML = '';
		}

		function updateCameraUiState(isRunning) {
			clearCameraWatchdog();
			isCameraInitializing = false;
			if (cameraBoxEl) {
				cameraBoxEl.hidden = !isRunning;
				if (isRunning) {
					cameraBoxEl.removeAttribute('hidden');
					cameraBoxEl.style.display = 'block';
				} else {
					cameraBoxEl.setAttribute('hidden', '');
					cameraBoxEl.style.display = 'none';
				}
				cameraBoxEl.classList.toggle('is-camera-active', isRunning);
			}
			if (cameraStatePill) {
				cameraStatePill.textContent = isRunning ? '🟢 الكاميرا نشطة (Scanning)' : 'جاهز للتشغيل';
				cameraStatePill.classList.toggle('is-active', isRunning);
				cameraStatePill.classList.toggle('is-live', isRunning);
			}
			if (btnSwitchCamera) {
				btnSwitchCamera.hidden = !isRunning;
				if (isRunning) {
					btnSwitchCamera.removeAttribute('hidden');
				} else {
					btnSwitchCamera.setAttribute('hidden', '');
				}
			}
			if (!isRunning && btnToggleTorch) {
				btnToggleTorch.hidden = true;
				btnToggleTorch.setAttribute('hidden', '');
			}
			if (btnToggleCamera) {
				btnToggleCamera.disabled = false;
				btnToggleCamera.classList.toggle('is-danger', isRunning);
				btnToggleCamera.textContent = isRunning ? '⏹ إيقاف كاميرا المسح المؤقت' : '📷 تشغيل كاميرا المسح المباشر';
			}
			if (!isRunning && cameraStatusText) {
				cameraStatusText.textContent = 'اضغط «تشغيل كاميرا المسح المباشر» لبدء المسح الضوئي لبطاقات الضيوف';
			}
		}

		if (btnToggleCamera && videoEl) {
			btnToggleCamera.addEventListener('click', function () {
				hideCameraErrorDiagnostic();

				// If camera is currently running or stuck initializing, stop cleanly and reset UI
				if (activeCameraSession || isCameraInitializing) {
					clearCameraWatchdog();
					isCameraInitializing = false;
					if (activeCameraSession) {
						try {
							activeCameraSession.stop();
						} catch (e) {}
						activeCameraSession = null;
					}
					updateCameraUiState(false);
					return;
				}

				if (!window.TarbwyQR || !window.TarbwyQR.startCameraScanner) {
					showCameraErrorDiagnostic('وحدة مسح الكاميرا غير محملة بالكامل. يرجى تحديث الصفحة (Refresh) والمحاولة مجدداً.');
					return;
				}

				isCameraInitializing = true;

				// Immediately unhide the camera viewport BEFORE requesting camera stream
				// so Android Chrome and iOS Safari can attach and play the video stream immediately
				if (cameraBoxEl) {
					cameraBoxEl.hidden = false;
					cameraBoxEl.removeAttribute('hidden');
					cameraBoxEl.style.display = 'block';
					cameraBoxEl.classList.add('is-camera-active');
				}
				videoEl.hidden = false;
				videoEl.removeAttribute('hidden');
				videoEl.style.display = 'block';

				btnToggleCamera.textContent = '⏳ جاري تشغيل الكاميرا...';
				if (cameraStatePill) {
					cameraStatePill.textContent = 'جاري تهيئة الكاميرا...';
				}
				if (cameraStatusText) {
					cameraStatusText.textContent = 'يرجى السماح بصلاحية الكاميرا عند ظهور نافذة المتصفح...';
				}

				// Hard 10.5s UI watchdog timer so the interface NEVER remains stuck on "جاري تشغيل الكاميرا..."
				clearCameraWatchdog();
				cameraInitWatchdogTimer = setTimeout(function () {
					if (!isCameraInitializing) return;
					isCameraInitializing = false;
					if (activeCameraSession) {
						try {
							activeCameraSession.stop();
						} catch (e) {}
						activeCameraSession = null;
					}
					updateCameraUiState(false);
					showCameraErrorDiagnostic('استغرق فتح الكاميرا وقتاً أطول من المعتاد (انتهت مهلة الانتظار). يرجى التأكد من السماح بإذن الكاميرا في المتصفح وعدم انشغالها بتطبيق آخر، ثم الضغط على «تشغيل كاميرا المسح المباشر» مرة أخرى.');
				}, 10500);

				function handleCameraReady(caps) {
					clearCameraWatchdog();
					isCameraInitializing = false;
					hideCameraErrorDiagnostic();
					updateCameraUiState(true);
					if (cameraStatusText) {
						var facingLabel = (caps && caps.facingMode === 'user') ? 'الكاميرا الأمامية نشطة' : 'الكاميرا الخلفية نشطة';
						cameraStatusText.textContent = facingLabel + ' — وجّه رمز QR داخل الإطار الأخضر';
					}
					if (btnToggleTorch) {
						var hasTorch = !!(caps && (caps.hasTorch || caps.torchSupported));
						btnToggleTorch.hidden = !hasTorch;
						if (hasTorch) {
							btnToggleTorch.removeAttribute('hidden');
						} else {
							btnToggleTorch.setAttribute('hidden', '');
						}
					}
				}

				activeCameraSession = window.TarbwyQR.startCameraScanner(
					videoEl,
					function (decodedText) {
						submitQrScanToServer(decodedText);
					},
					function (errMsg) {
						clearCameraWatchdog();
						isCameraInitializing = false;
						activeCameraSession = null;
						updateCameraUiState(false);
						showCameraErrorDiagnostic(errMsg);
					},
					{
						facingMode: 'environment',
						timeoutMs: 9500,
						onCameraReady: handleCameraReady,
						onStatusChange: function (st) {
							if (st && st.status === 'active') {
								handleCameraReady(st);
							} else if (st && st.status === 'requesting') {
								if (cameraStatusText) {
									cameraStatusText.textContent = st.message || 'جاري طلب إذن الكاميرا...';
								}
							}
						}
					}
				);
			});
		}

		if (btnSwitchCamera) {
			btnSwitchCamera.addEventListener('click', function () {
				if (activeCameraSession && activeCameraSession.switchCamera) {
					if (cameraStatusText) {
						cameraStatusText.textContent = '⏳ جاري تبديل الكاميرا (أمامية / خلفية)...';
					}
					activeCameraSession.switchCamera();
				}
			});
		}

		if (btnToggleTorch) {
			btnToggleTorch.addEventListener('click', function () {
				if (activeCameraSession && activeCameraSession.toggleTorch) {
					activeCameraSession.toggleTorch().then(function (torchOn) {
						btnToggleTorch.classList.toggle('is-active', !!torchOn);
					});
				}
			});
		}

		if (btnPhotoQrScan && inputPhotoQr) {
			btnPhotoQrScan.addEventListener('click', function () {
				inputPhotoQr.value = '';
				inputPhotoQr.click();
			});
			inputPhotoQr.addEventListener('change', function () {
				var file = inputPhotoQr.files && inputPhotoQr.files[0];
				if (!file || !window.TarbwyQR || !window.TarbwyQR.scanImageFile) return;
				if (scanErrorEl) {
					scanErrorEl.hidden = true;
					scanErrorEl.setAttribute('hidden', '');
				}
				btnPhotoQrScan.disabled = true;
				var origHtml = btnPhotoQrScan.innerHTML;
				btnPhotoQrScan.textContent = 'جاري تحليل صورة QR...';

				window.TarbwyQR.scanImageFile(
					file,
					function (decoded) {
						btnPhotoQrScan.disabled = false;
						btnPhotoQrScan.innerHTML = origHtml;
						submitQrScanToServer(decoded);
					},
					function (errMsg) {
						btnPhotoQrScan.disabled = false;
						btnPhotoQrScan.innerHTML = origHtml;
						if (scanErrorEl) {
							scanErrorEl.hidden = false;
							scanErrorEl.removeAttribute('hidden');
							scanErrorEl.textContent = errMsg;
						}
					}
				);
			});
		}

		// =========================================================================
		// MANUAL ATTENDEE SEARCH & DIRECTORY (Name, Email, Ticket Number, Quick Filters)
		// =========================================================================
		var lookupForm = document.getElementById('form-checkin-lookup');
		var searchInput = document.getElementById('input-checkin-search') || document.getElementById('input-lookup-query');
		var clearSearchBtn = document.getElementById('btn-clear-checkin-search');
		var lookupResults = document.getElementById('checkin-search-results') || document.getElementById('checkin-lookup-results');
		var filterButtons = document.querySelectorAll('[data-lookup-filter]');
		var resultsCountEl = document.getElementById('search-results-count') || document.getElementById('checkin-results-count');

		function renderLookupResults(list) {
			if (!lookupResults) return;
			if (resultsCountEl) {
				resultsCountEl.textContent = list.length + (list.length === 1 ? ' نتيجة' : ' نتائج');
			}

			if (!list.length) {
				lookupResults.innerHTML =
					'<div class="tarbwy-checkin-empty-state">' +
					'<p><strong>لا توجد نتائج مطابقة للبحث الحالي.</strong></p>' +
					'<p style="font-size:13px;color:#64748B;margin-top:4px;">جرّب البحث بجزء من الاسم، أو البريد الإلكتروني، أو رقم التذكرة (TKT-...)، أو اختر فلتر «👥 كل المسجلين».</p>' +
					'</div>';
				return;
			}

			var html = '';
			list.forEach(function (item) {
				var checked = item.checkin_status === 'checked_in';
				var isConfirmed = item.rsvp_status === 'confirmed' || item.review_status === 'approved';
				var typeBadgeClass = item.guest_type === 'vip' ? 'is-vip' : (item.guest_type === 'companion' ? 'is-companion' : 'is-public');

				html +=
					'<div class="tarbwy-checkin-attendee-card ' + (checked ? 'is-checked-in' : '') + '">' +
					'<div class="tarbwy-checkin-attendee-info">' +
					'<div class="tarbwy-checkin-attendee-top">' +
					'<strong class="tarbwy-checkin-attendee-name">' + escapeHtml(item.full_name) + '</strong>' +
					'<span class="tarbwy-checkin-type-badge ' + typeBadgeClass + '">' + escapeHtml(guestTypeArabic(item.guest_type)) + '</span>' +
					'<span class="tarbwy-checkin-rsvp-badge ' + (isConfirmed ? 'is-confirmed' : 'is-unconfirmed') + '">' + escapeHtml(rsvpStatusArabic(item.rsvp_status)) + '</span>' +
					'</div>' +
					'<div class="tarbwy-checkin-attendee-meta">' +
					(item.ticket_reference ? '<span>التذكرة: <code dir="ltr">' + escapeHtml(item.ticket_reference) + '</code></span>' : '<span>بدون تذكرة مصدرة</span>') +
					(item.email ? '<span>البريد: <span dir="ltr">' + escapeHtml(item.email) + '</span></span>' : '') +
					(item.organization ? '<span>الجهة: ' + escapeHtml(item.organization) + '</span>' : '') +
					(item.parent_name ? '<span>مرافق لـ: ' + escapeHtml(item.parent_name) + '</span>' : '') +
					'</div>' +
					'</div>' +
					'<div class="tarbwy-checkin-attendee-action">' +
					(checked
						? '<div class="tarbwy-checkin-done-pill">✓ تم الدخول<small>' + escapeHtml(item.checked_in_at || '') + '</small></div>'
						: '<button type="button" class="tarbwy-checkin-manual-btn btn-manual-checkin" data-guest-id="' + parseInt(item.guest_id, 10) + '" data-ticket-ref="' + escapeHtml(item.ticket_reference || '') + '">تسجيل دخول يدوي</button>') +
					'</div>' +
					'</div>';
			});

			lookupResults.innerHTML = html;

			lookupResults.querySelectorAll('.btn-manual-checkin').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var gid = parseInt(btn.getAttribute('data-guest-id'), 10);
					var tref = btn.getAttribute('data-ticket-ref') || '';
					btn.disabled = true;
					btn.textContent = 'جاري التسجيل...';

					fetch(window.TarbwyCheckinConfig.restUrl + '/checkin/manual', {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.TarbwyCheckinConfig.nonce
						},
						body: JSON.stringify({
							guest_id: gid,
							ticket_reference: tref,
							device_label: window.TarbwyCheckinConfig.deviceLabel || 'Manual-Reception'
						})
					})
						.then(function (r) {
							setOnlineState(true);
							return r.json();
						})
						.then(function (data) {
							showCheckinResult('online', data);
							executeAttendeeLookup();
						})
						.catch(function () {
							btn.disabled = false;
							btn.textContent = 'تسجيل دخول يدوي';
							showCheckinResult('offline', {});
						});
				});
			});
		}

		function executeAttendeeLookup() {
			if (!lookupResults) return;
			var qVal = searchInput ? searchInput.value.trim() : '';
			if (clearSearchBtn) {
				clearSearchBtn.hidden = !qVal;
			}
			var url = window.TarbwyCheckinConfig.restUrl +
				'/checkin/lookup?q=' + encodeURIComponent(qVal) +
				'&filter=' + encodeURIComponent(activeLookupFilter);

			lookupResults.innerHTML = '<div class="tarbwy-checkin-loading">جاري البحث في سجلات المشاركين...</div>';

			fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': window.TarbwyCheckinConfig.nonce }
			})
				.then(function (r) {
					setOnlineState(true);
					if (!r.ok) throw new Error('lookup_failed');
					return r.json();
				})
				.then(function (res) {
					renderLookupResults(res.results || []);
				})
				.catch(function () {
					lookupResults.innerHTML =
						'<div class="tarbwy-checkin-empty-state" style="color:#DC2626;">تعذر تحميل نتائج البحث حالياً. اضغط «بحث» للمحاولة مرة أخرى.</div>';
				});
		}

		if (lookupForm) {
			lookupForm.addEventListener('submit', function (e) {
				e.preventDefault();
				if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
				executeAttendeeLookup();
			});
		}

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				if (clearSearchBtn) {
					clearSearchBtn.hidden = !searchInput.value.trim();
				}
				if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
				searchDebounceTimer = setTimeout(function () {
					executeAttendeeLookup();
				}, 220);
			});
		}

		if (clearSearchBtn && searchInput) {
			clearSearchBtn.addEventListener('click', function () {
				searchInput.value = '';
				clearSearchBtn.hidden = true;
				searchInput.focus();
				executeAttendeeLookup();
			});
		}

		if (filterButtons.length) {
			filterButtons.forEach(function (chip) {
				chip.addEventListener('click', function () {
					activeLookupFilter = chip.getAttribute('data-lookup-filter') || 'all';
					filterButtons.forEach(function (c) {
						c.classList.toggle('is-active', c === chip);
					});
					executeAttendeeLookup();
				});
			});
		}

		// Automatically load attendees on startup
		executeAttendeeLookup();
	}

	// =========================================================================
	// 5. ADMIN PANEL DOUBLE-CLICK / DOUBLE-SUBMIT PROTECTION
	// =========================================================================
	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;
		var action = form.getAttribute('action') || '';
		if (action.indexOf('admin-post.php') !== -1) {
			if (form.getAttribute('data-submitting') === '1') {
				e.preventDefault();
				return false;
			}
			form.setAttribute('data-submitting', '1');
			var submitter = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
			if (submitter && submitter.name && submitter.value) {
				var hiddenInput = document.createElement('input');
				hiddenInput.type = 'hidden';
				hiddenInput.name = submitter.name;
				hiddenInput.value = submitter.value;
				form.appendChild(hiddenInput);
			}
			setTimeout(function () {
				var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
				for (var i = 0; i < btns.length; i++) {
					btns[i].disabled = true;
				}
			}, 10);
		}
	});
})(window, document);


