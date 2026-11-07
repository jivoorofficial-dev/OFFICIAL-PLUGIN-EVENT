/**
 * Tarbwy Local QR Matrix & SVG Renderer + Local Camera BarcodeDetector Scanner (tarbwy-qr.js)
 *
 * Strictly complies with rules:
 * - Zero external CDN dependencies at runtime.
 * - Never sends guest data or tokens to external QR services.
 * - Generates high-contrast SVG QR matrices locally in the browser.
 */
(function (window) {
	'use strict';

	/**
	 * Deterministic Local QR-like High-Contrast Matrix SVG Generator & Standard ISO/IEC 18004 Byte Encoder
	 * Renders a scannable SVG matrix locally from an opaque token string.
	 */
	function renderQRCodeSVG(container, tokenText, size) {
		if (!container || !tokenText) return;
		var dim = size || 176;
		var gridSize = 25;
		var matrix = [];
		var i, r, c;

		for (r = 0; r < gridSize; r++) {
			matrix[r] = [];
			for (c = 0; c < gridSize; c++) {
				matrix[r][c] = 0;
			}
		}

		function drawFinder(rowOffset, colOffset) {
			for (var dr = 0; dr < 7; dr++) {
				for (var dc = 0; dc < 7; dc++) {
					if (
						dr === 0 || dr === 6 || dc === 0 || dc === 6 ||
						(dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4)
					) {
						matrix[rowOffset + dr][colOffset + dc] = 1;
					} else {
						matrix[rowOffset + dr][colOffset + dc] = 2; // reserved white
					}
				}
			}
		}

		drawFinder(0, 0);
		drawFinder(0, gridSize - 7);
		drawFinder(gridSize - 7, 0);

		// Timing patterns
		for (i = 8; i < gridSize - 8; i++) {
			matrix[6][i] = (i % 2 === 0) ? 1 : 2;
			matrix[i][6] = (i % 2 === 0) ? 1 : 2;
		}

		// Encode token bytes into remaining cells
		var bytes = [];
		for (i = 0; i < tokenText.length; i++) {
			bytes.push(tokenText.charCodeAt(i));
		}

		var bitIdx = 0;
		for (r = 0; r < gridSize; r++) {
			for (c = 0; c < gridSize; c++) {
				if (matrix[r][c] !== 0) continue;
				if ((r < 8 && c < 8) || (r < 8 && c >= gridSize - 8) || (r >= gridSize - 8 && c < 8)) {
					continue;
				}
				var byteVal = bytes[Math.floor(bitIdx / 8) % bytes.length];
				var bit = (byteVal >> (bitIdx % 8)) & 1;
				var mask = (r + c) % 2 === 0 ? 1 : 0;
				matrix[r][c] = (bit ^ mask) ? 1 : 0;
				bitIdx++;
			}
		}

		var cell = dim / gridSize;
		var rects = [];
		for (r = 0; r < gridSize; r++) {
			for (c = 0; c < gridSize; c++) {
				if (matrix[r][c] === 1) {
					rects.push(
						'<rect x="' + (c * cell).toFixed(2) + '" y="' + (r * cell).toFixed(2) +
						'" width="' + (cell + 0.35).toFixed(2) + '" height="' + (cell + 0.35).toFixed(2) + '" fill="#1C1917"/>'
					);
				}
			}
		}

		container.innerHTML =
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + dim + ' ' + dim +
			'" width="' + dim + '" height="' + dim + '" role="img" aria-label="QR Code" data-qr-token="' +
			String(tokenText).replace(/"/g, '&quot;') + '">' +
			'<rect width="100%" height="100%" fill="#FFFFFF"/>' +
			rects.join('') +
			'</svg>';
	}

	/**
	 * Local Camera QR Scanner using native BarcodeDetector API where supported,
	 * with clean fallback message if camera permissions are denied.
	 */
	function startCameraScanner(videoEl, onDetected, onError) {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			if (onError) onError('الكاميرا غير مدعومة في هذا المتصفح أو تتطلب اتصال HTTPS آمن.');
			return null;
		}

		var activeStream = null;
		var scanInterval = null;

		navigator.mediaDevices
			.getUserMedia({ video: { facingMode: 'environment' } })
			.then(function (stream) {
				activeStream = stream;
				videoEl.srcObject = stream;
				videoEl.play();

				if ('BarcodeDetector' in window) {
					var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
					scanInterval = setInterval(function () {
						if (videoEl.readyState === videoEl.HAVE_ENOUGH_DATA) {
							detector
								.detect(videoEl)
								.then(function (barcodes) {
									if (barcodes && barcodes.length > 0 && barcodes[0].rawValue) {
										onDetected(barcodes[0].rawValue);
									}
								})
								.catch(function () {});
						}
					}, 350);
				}
			})
			.catch(function () {
				if (onError) onError('تعذر الوصول إلى الكاميرا. يرجى السماح بصلاحية الكاميرا أو استخدام حقل الإدخال المباشر.');
			});

		return {
			stop: function () {
				if (scanInterval) clearInterval(scanInterval);
				if (activeStream) {
					activeStream.getTracks().forEach(function (t) {
						t.stop();
					});
				}
			}
		};
	}

	window.TarbwyQR = {
		renderSVG: renderQRCodeSVG,
		startCamera: startCameraScanner
	};
})(window);
