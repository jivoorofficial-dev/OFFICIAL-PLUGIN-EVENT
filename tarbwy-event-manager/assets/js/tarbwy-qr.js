/**
 * Tarbwy Local ISO/IEC 18004 QR Code Generator & Multi-Engine Mobile Camera Scanner
 *
 * - Zero external CDN dependencies at runtime (100% local execution).
 * - Generates genuine ISO/IEC 18004 standard QR codes (Byte Mode, ECC Level L, Versions 1–6)
 *   so any Android or iOS camera can scan tickets reliably.
 * - Provides cross-browser camera access for Android Chrome & iOS Safari with:
 *   1. Progressive getUserMedia constraints (prevents OverconstrainedError on iOS/Android)
 *   2. iOS Safari playsinline + muted + autoplay compliance
 *   3. Native BarcodeDetector + Canvas Frame Analyzer + Camera Snapshot Image Scanner fallback
 *   4. Camera flip (rear/front) & Torch/Flashlight control
 *   5. Web Audio API + Haptic vibration feedback for reception staff
 */
(function (window) {
	'use strict';

	/* =========================================================================
	   1. ISO/IEC 18004 STANDARD QR CODE ENCODER (Versions 1 to 6, ECC Level L)
	   ========================================================================= */

	// GF(256) exponent & log tables with primitive polynomial 0x11D (285)
	var GF_EXP = new Uint8Array(512);
	var GF_LOG = new Uint8Array(256);
	(function initGaloisField() {
		var x = 1;
		for (var i = 0; i < 255; i++) {
			GF_EXP[i] = x;
			GF_LOG[x] = i;
			x <<= 1;
			if (x & 0x100) {
				x ^= 0x11d;
			}
		}
		for (var j = 255; j < 512; j++) {
			GF_EXP[j] = GF_EXP[j - 255];
		}
	})();

	function gfMul(a, b) {
		if (a === 0 || b === 0) return 0;
		return GF_EXP[GF_LOG[a] + GF_LOG[b]];
	}

	function rsGeneratorPoly(degree) {
		var poly = new Uint8Array([1]);
		for (var i = 0; i < degree; i++) {
			var next = new Uint8Array(poly.length + 1);
			var root = GF_EXP[i];
			for (var j = 0; j < poly.length; j++) {
				next[j] ^= poly[j];
				next[j + 1] ^= gfMul(poly[j], root);
			}
			poly = next;
		}
		return poly;
	}

	function rsEncode(dataBytes, ecLen) {
		var gen = rsGeneratorPoly(ecLen);
		var rem = new Uint8Array(ecLen);
		for (var i = 0; i < dataBytes.length; i++) {
			var factor = dataBytes[i] ^ rem[0];
			for (var k = 0; k < ecLen - 1; k++) {
				rem[k] = rem[k + 1];
			}
			rem[ecLen - 1] = 0;
			if (factor !== 0) {
				for (var j = 0; j < ecLen; j++) {
					rem[j] ^= gfMul(gen[j + 1], factor);
				}
			}
		}
		return rem;
	}

	// Version table for ECC Level L (Versions 1 to 6, single-block for V1-V5, 2-block for V6)
	var QR_VERSIONS = [
		null,
		{ version: 1, size: 21, dataCodewords: 19, ecCodewordsPerBlock: 7, blocks: 1, align: [] },
		{ version: 2, size: 25, dataCodewords: 34, ecCodewordsPerBlock: 10, blocks: 1, align: [6, 18] },
		{ version: 3, size: 29, dataCodewords: 55, ecCodewordsPerBlock: 15, blocks: 1, align: [6, 22] },
		{ version: 4, size: 33, dataCodewords: 80, ecCodewordsPerBlock: 20, blocks: 1, align: [6, 26] },
		{ version: 5, size: 37, dataCodewords: 108, ecCodewordsPerBlock: 26, blocks: 1, align: [6, 30] },
		{ version: 6, size: 41, dataCodewords: 136, ecCodewordsPerBlock: 18, blocks: 2, align: [6, 34] }
	];

	function utf8ToBytes(str) {
		var clean = String(str || '');
		if (window.TextEncoder) {
			return Array.from(new TextEncoder().encode(clean));
		}
		var out = [];
		for (var i = 0; i < clean.length; i++) {
			var c = clean.charCodeAt(i);
			if (c < 0x80) {
				out.push(c);
			} else if (c < 0x800) {
				out.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f));
			} else {
				out.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f));
			}
		}
		return out;
	}

	function selectQrVersion(byteLen) {
		for (var v = 1; v <= 6; v++) {
			var maxBytes = QR_VERSIONS[v].dataCodewords - 2; // 4-bit mode + 8-bit length + 4-bit terminator
			if (byteLen <= maxBytes) {
				return QR_VERSIONS[v];
			}
		}
		return QR_VERSIONS[6];
	}

	function buildQrMatrix(payload) {
		var utf8 = utf8ToBytes(payload);
		var verInfo = selectQrVersion(utf8.length);
		if (utf8.length > verInfo.dataCodewords - 2) {
			utf8 = utf8.slice(0, verInfo.dataCodewords - 2);
		}

		// Build bit stream: Byte Mode (0100) + 8-bit character count + payload bytes + terminator
		var bits = [];
		function pushBits(val, len) {
			for (var i = len - 1; i >= 0; i--) {
				bits.push((val >>> i) & 1);
			}
		}

		pushBits(0x4, 4); // Byte mode indicator (0100)
		pushBits(utf8.length, 8);
		for (var b = 0; b < utf8.length; b++) {
			pushBits(utf8[b], 8);
		}

		var totalDataBits = verInfo.dataCodewords * 8;
		var termLen = Math.min(4, totalDataBits - bits.length);
		pushBits(0, termLen);

		while (bits.length % 8 !== 0) {
			bits.push(0);
		}

		var dataBytes = new Uint8Array(verInfo.dataCodewords);
		for (var idx = 0; idx < bits.length / 8; idx++) {
			var byteVal = 0;
			for (var bit = 0; bit < 8; bit++) {
				byteVal = (byteVal << 1) | bits[idx * 8 + bit];
			}
			dataBytes[idx] = byteVal;
		}

		var padBytes = [0xec, 0x11];
		var padIdx = 0;
		for (var p = bits.length / 8; p < verInfo.dataCodewords; p++) {
			dataBytes[p] = padBytes[padIdx % 2];
			padIdx++;
		}

		// Error Correction & Interleaving
		var finalCodewords = [];
		if (verInfo.blocks === 1) {
			var ec = rsEncode(dataBytes, verInfo.ecCodewordsPerBlock);
			for (var d = 0; d < dataBytes.length; d++) finalCodewords.push(dataBytes[d]);
			for (var e = 0; e < ec.length; e++) finalCodewords.push(ec[e]);
		} else {
			var blockSize = verInfo.dataCodewords / verInfo.blocks;
			var dataBlocks = [];
			var ecBlocks = [];
			for (var blk = 0; blk < verInfo.blocks; blk++) {
				var slice = dataBytes.subarray(blk * blockSize, (blk + 1) * blockSize);
				dataBlocks.push(slice);
				ecBlocks.push(rsEncode(slice, verInfo.ecCodewordsPerBlock));
			}
			for (var col = 0; col < blockSize; col++) {
				for (var b1 = 0; b1 < verInfo.blocks; b1++) {
					finalCodewords.push(dataBlocks[b1][col]);
				}
			}
			for (var ecol = 0; ecol < verInfo.ecCodewordsPerBlock; ecol++) {
				for (var b2 = 0; b2 < verInfo.blocks; b2++) {
					finalCodewords.push(ecBlocks[b2][ecol]);
				}
			}
		}

		var N = verInfo.size;
		var matrix = [];
		var isFunction = [];
		for (var r = 0; r < N; r++) {
			matrix.push(new Uint8Array(N));
			isFunction.push(new Uint8Array(N));
		}

		function setFunctionModule(row, col, dark) {
			if (row >= 0 && row < N && col >= 0 && col < N) {
				matrix[row][col] = dark ? 1 : 0;
				isFunction[row][col] = 1;
			}
		}

		function drawFinderPattern(cRow, cCol) {
			for (var dy = -4; dy <= 4; dy++) {
				for (var dx = -4; dx <= 4; dx++) {
					var dist = Math.max(Math.abs(dx), Math.abs(dy));
					var rr = cRow + dy;
					var cc = cCol + dx;
					if (rr >= 0 && rr < N && cc >= 0 && cc < N) {
						setFunctionModule(rr, cc, dist !== 2 && dist !== 4);
					}
				}
			}
		}

		function drawAlignmentPattern(cRow, cCol) {
			for (var dy = -2; dy <= 2; dy++) {
				for (var dx = -2; dx <= 2; dx++) {
					setFunctionModule(cRow + dy, cCol + dx, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
				}
			}
		}

		// Finder patterns (3 corners)
		drawFinderPattern(3, 3);
		drawFinderPattern(3, N - 4);
		drawFinderPattern(N - 4, 3);

		// Timing patterns
		for (var t = 8; t < N - 8; t++) {
			var darkTiming = t % 2 === 0;
			setFunctionModule(6, t, darkTiming);
			setFunctionModule(t, 6, darkTiming);
		}

		// Alignment patterns
		var alignCoords = verInfo.align;
		for (var a1 = 0; a1 < alignCoords.length; a1++) {
			for (var a2 = 0; a2 < alignCoords.length; a2++) {
				var ar = alignCoords[a1];
				var ac = alignCoords[a2];
				if ((ar === 6 && ac === 6) || (ar === 6 && ac === N - 7) || (ar === N - 7 && ac === 6)) {
					continue;
				}
				drawAlignmentPattern(ar, ac);
			}
		}

		// Dark module & Reserve Format Info areas
		setFunctionModule(4 * verInfo.version + 9, 8, true);
		for (var f = 0; f <= 8; f++) {
			if (f !== 6) {
				if (!isFunction[8][f]) setFunctionModule(8, f, false);
				if (!isFunction[f][8]) setFunctionModule(f, 8, false);
			}
		}
		for (var f2 = 0; f2 < 8; f2++) {
			if (!isFunction[8][N - 1 - f2]) setFunctionModule(8, N - 1 - f2, false);
			if (!isFunction[N - 1 - f2][8]) setFunctionModule(N - 1 - f2, 8, false);
		}

		// Place data + EC bits in zigzag upward/downward 2-column strips
		var allBits = [];
		for (var cw = 0; cw < finalCodewords.length; cw++) {
			for (var s = 7; s >= 0; s--) {
				allBits.push((finalCodewords[cw] >>> s) & 1);
			}
		}

		var bitIndex = 0;
		var upward = true;
		for (var rightCol = N - 1; rightCol >= 1; rightCol -= 2) {
			if (rightCol === 6) rightCol = 5; // Skip vertical timing column
			for (var vert = 0; vert < N; vert++) {
				var rowIdx = upward ? N - 1 - vert : vert;
				for (var lr = 0; lr < 2; lr++) {
					var colIdx = rightCol - lr;
					if (!isFunction[rowIdx][colIdx]) {
						var bitVal = bitIndex < allBits.length ? allBits[bitIndex] : 0;
						bitIndex++;
						// Apply Mask Pattern 0: (row + col) % 2 === 0
						if ((rowIdx + colIdx) % 2 === 0) {
							bitVal ^= 1;
						}
						matrix[rowIdx][colIdx] = bitVal;
					}
				}
			}
			upward = !upward;
		}

		// Format Information for ECC Level L (01) + Mask Pattern 0 (000) -> 0x77C4 (30660)
		// Per ISO/IEC 18004 Figure 25: bit 14 (MSB) is placed at (8,0) and (N-1,8); bit 0 (LSB) at (0,8) and (8,N-1)
		var formatBits = 0x77c4;
		function getFormatBit(bitPos) {
			return ((formatBits >>> bitPos) & 1) === 1;
		}

		// Top-left format bits (bit 0 at (0,8) down to bit 14 at (8,0))
		for (var fb = 0; fb <= 5; fb++) setFunctionModule(fb, 8, getFormatBit(fb));
		setFunctionModule(7, 8, getFormatBit(6));
		setFunctionModule(8, 8, getFormatBit(7));
		setFunctionModule(8, 7, getFormatBit(8));
		for (var fb2 = 9; fb2 < 15; fb2++) setFunctionModule(8, 14 - fb2, getFormatBit(fb2));

		// Top-right (bits 0..7) and Bottom-left (bits 8..14) format bits
		for (var fb3 = 0; fb3 < 8; fb3++) setFunctionModule(8, N - 1 - fb3, getFormatBit(fb3));
		for (var fb4 = 8; fb4 < 15; fb4++) setFunctionModule(N - 15 + fb4, 8, getFormatBit(fb4));
		setFunctionModule(N - 8, 8, true); // Fixed dark module

		return { matrix: matrix, size: N };
	}

	/**
	 * Render a standards-compliant ISO/IEC 18004 SVG QR Code from an opaque token string
	 *
	 * @param {string} tokenString Opaque token or ticket URL
	 * @param {number} size SVG width/height in pixels
	 * @returns {string} SVG markup string
	 */
	function renderQRCodeSVG(tokenString, size) {
		size = size || 210;
		var payload = String(tokenString || 'TARBWY-INVALID').trim();
		var qr = buildQrMatrix(payload);
		var N = qr.size;
		var quietZone = 4;
		var totalModules = N + quietZone * 2;
		var pathParts = [];

		for (var r = 0; r < N; r++) {
			for (var c = 0; c < N; c++) {
				if (qr.matrix[r][c] === 1) {
					var x = c + quietZone;
					var y = r + quietZone;
					pathParts.push('M' + x + ',' + y + 'h1v1h-1z');
				}
			}
		}

		return (
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' +
			totalModules +
			' ' +
			totalModules +
			'" width="' +
			size +
			'" height="' +
			size +
			'" shape-rendering="crispEdges" role="img" aria-label="QR Code" data-qr-payload="' +
			payload.replace(/"/g, '&quot;') +
			'">' +
			'<rect width="' +
			totalModules +
			'" height="' +
			totalModules +
			'" fill="#FFFFFF"/>' +
			'<path d="' +
			pathParts.join('') +
			'" fill="#0F172A"/>' +
			'</svg>'
		);
	}

	/* =========================================================================
	   2. SMART QR PAYLOAD PARSER & AUDIO/HAPTIC FEEDBACK
	   ========================================================================= */

	function normalizeScannedPayload(rawString) {
		var str = String(rawString || '').trim();
		if (!str) return '';
		try {
			if (str.indexOf('http') !== -1 || str.indexOf('?') !== -1 || str.indexOf('t=') !== -1 || str.indexOf('token=') !== -1) {
				var qIndex = str.indexOf('?');
				if (qIndex !== -1) {
					var params = new URLSearchParams(str.slice(qIndex + 1));
					if (params.get('t')) return params.get('t').trim();
					if (params.get('token')) return params.get('token').trim();
					if (params.get('ref')) return params.get('ref').trim();
				}
			}
		} catch (e) {
			// Ignore URL parse error and return raw string
		}
		return str;
	}

	var audioMuted = false;
	function setAudioMuted(muted) {
		audioMuted = !!muted;
	}

	function playFeedbackSound(type) {
		// Haptic vibration on Android devices
		try {
			if (navigator.vibrate) {
				if (type === 'valid') {
					navigator.vibrate([80]);
				} else if (type === 'already_checked_in' || type === 'duplicate' || type === 'warning') {
					navigator.vibrate([100, 60, 100]);
				} else {
					navigator.vibrate([220]);
				}
			}
		} catch (e) {
			// Ignore vibration errors on iOS
		}

		if (audioMuted) return;

		try {
			var AudioCtx = window.AudioContext || window.webkitAudioContext;
			if (!AudioCtx) return;
			var ctx = new AudioCtx();
			var osc = ctx.createOscillator();
			var gain = ctx.createGain();
			osc.connect(gain);
			gain.connect(ctx.destination);

			var now = ctx.currentTime;
			if (type === 'valid') {
				osc.type = 'sine';
				osc.frequency.setValueAtTime(880, now);
				osc.frequency.setValueAtTime(1318.5, now + 0.09);
				gain.gain.setValueAtTime(0.18, now);
				gain.gain.exponentialRampToValueAtTime(0.001, now + 0.26);
				osc.start(now);
				osc.stop(now + 0.26);
			} else if (type === 'already_checked_in' || type === 'duplicate' || type === 'warning') {
				osc.type = 'triangle';
				osc.frequency.setValueAtTime(520, now);
				osc.frequency.setValueAtTime(440, now + 0.14);
				gain.gain.setValueAtTime(0.2, now);
				gain.gain.exponentialRampToValueAtTime(0.001, now + 0.34);
				osc.start(now);
				osc.stop(now + 0.34);
			} else {
				osc.type = 'sawtooth';
				osc.frequency.setValueAtTime(260, now);
				osc.frequency.setValueAtTime(195, now + 0.15);
				gain.gain.setValueAtTime(0.18, now);
				gain.gain.exponentialRampToValueAtTime(0.001, now + 0.36);
				osc.start(now);
				osc.stop(now + 0.36);
			}
		} catch (e) {
			// Ignore audio context errors if blocked
		}
	}

	/* =========================================================================
	   3. PURE-JS CANVAS QR MATRIX DECODER FALLBACK (For iOS Safari / No BarcodeDetector)
	   ========================================================================= */

	var GF_INV = new Uint8Array(256);
	for (var gi = 1; gi < 256; gi++) {
		GF_INV[gi] = GF_EXP[255 - GF_LOG[gi]];
	}

	function decodeQrAtThreshold(gray, w, h, threshold) {
		var centers = [];
		var stepY = Math.max(1, Math.floor(h / 140));
		for (var y = stepY; y < h - stepY; y += stepY) {
			var stateCount = [0, 0, 0, 0, 0];
			var currentState = 0;
			var rowOffset = y * w;
			for (var x = 0; x < w; x++) {
				var isDark = gray[rowOffset + x] < threshold;
				if (isDark) {
					if ((currentState & 1) === 1) currentState++;
					stateCount[currentState]++;
				} else {
					if ((currentState & 1) === 1) {
						stateCount[currentState]++;
					} else {
						if (currentState === 4) {
							var totalFinder = stateCount[0] + stateCount[1] + stateCount[2] + stateCount[3] + stateCount[4];
							if (totalFinder >= 7) {
								var moduleSize = totalFinder / 7.0;
								var maxVar = moduleSize * 0.7;
								if (
									Math.abs(moduleSize - stateCount[0]) < maxVar &&
									Math.abs(moduleSize - stateCount[1]) < maxVar &&
									Math.abs(3 * moduleSize - stateCount[2]) < 3 * maxVar &&
									Math.abs(moduleSize - stateCount[3]) < maxVar &&
									Math.abs(moduleSize - stateCount[4]) < maxVar
								) {
									var centerX = x - stateCount[4] - stateCount[3] - stateCount[2] / 2.0;
									var vy = y;
									var vCount = 0;
									var cxInt = Math.round(centerX);
									while (vy >= 0 && gray[vy * w + cxInt] < threshold) {
										vy--;
										vCount++;
									}
									var vyDown = y + 1;
									while (vyDown < h && gray[vyDown * w + cxInt] < threshold) {
										vyDown++;
										vCount++;
									}
									if (Math.abs(vCount - stateCount[2]) <= stateCount[2] * 0.65) {
										var centerY = (vy + vyDown) / 2.0;
										var merged = false;
										for (var cIdx = 0; cIdx < centers.length; cIdx++) {
											var exist = centers[cIdx];
											if (Math.hypot(exist.x - centerX, exist.y - centerY) < moduleSize * 2.5) {
												exist.x = (exist.x * exist.count + centerX) / (exist.count + 1);
												exist.y = (exist.y * exist.count + centerY) / (exist.count + 1);
												exist.size = (exist.size * exist.count + moduleSize) / (exist.count + 1);
												exist.count++;
												merged = true;
												break;
											}
										}
										if (!merged) {
											centers.push({ x: centerX, y: centerY, size: moduleSize, count: 1 });
										}
									}
								}
							}
							stateCount = [stateCount[2], stateCount[3], stateCount[4], 1, 0];
							currentState = 3;
						} else {
							currentState++;
							stateCount[currentState]++;
						}
					}
				}
			}
		}

		centers.sort(function (a, b) {
			return b.count - a.count;
		});
		if (centers.length < 3) return null;

		var maxCand = Math.min(centers.length, 6);
		var triplets = [];
		for (var c1 = 0; c1 < maxCand - 2; c1++) {
			for (var c2 = c1 + 1; c2 < maxCand - 1; c2++) {
				for (var c3 = c2 + 1; c3 < maxCand; c3++) {
					triplets.push([centers[c1], centers[c2], centers[c3]]);
				}
			}
		}

		function evalMask(mask, r, c) {
			switch (mask) {
				case 0: return (r + c) % 2 === 0;
				case 1: return r % 2 === 0;
				case 2: return c % 3 === 0;
				case 3: return (r + c) % 3 === 0;
				case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
				case 5: return ((r * c) % 2) + ((r * c) % 3) === 0;
				case 6: return (((r * c) % 2) + ((r * c) % 3)) % 2 === 0;
				case 7: return (((r + c) % 2) + ((r * c) % 3)) % 2 === 0;
				default: return (r + c) % 2 === 0;
			}
		}

		for (var tIdx = 0; tIdx < triplets.length; tIdx++) {
			var top3 = triplets[tIdx];
			var d01 = Math.hypot(top3[0].x - top3[1].x, top3[0].y - top3[1].y);
			var d12 = Math.hypot(top3[1].x - top3[2].x, top3[1].y - top3[2].y);
			var d02 = Math.hypot(top3[0].x - top3[2].x, top3[0].y - top3[2].y);

			var tl, pA, pB;
			if (d01 >= d12 && d01 >= d02) {
				tl = top3[2];
				pA = top3[0];
				pB = top3[1];
			} else if (d12 >= d01 && d12 >= d02) {
				tl = top3[0];
				pA = top3[1];
				pB = top3[2];
			} else {
				tl = top3[1];
				pA = top3[0];
				pB = top3[2];
			}

			var cross = (pA.x - tl.x) * (pB.y - tl.y) - (pA.y - tl.y) * (pB.x - tl.x);
			var tr = cross > 0 ? pA : pB;
			var bl = cross > 0 ? pB : pA;

			var distTR = Math.hypot(tr.x - tl.x, tr.y - tl.y);
			var distBL = Math.hypot(bl.x - tl.x, bl.y - tl.y);
			if (distTR < 14 || distBL < 14) continue;
			var sideRatio = distTR > distBL ? distTR / distBL : distBL / distTR;
			if (sideRatio > 1.45) continue;

			var avgModule = (tl.size + tr.size + bl.size) / 3.0;
			var dimension = Math.round((distTR + distBL) / (2.0 * avgModule)) + 7;
			var estV = Math.max(1, Math.min(6, Math.round((dimension - 17) / 4)));

			var versionCandidates = [estV];
			if (estV !== 1) versionCandidates.push(1);
			if (estV !== 2) versionCandidates.push(2);
			if (estV !== 3) versionCandidates.push(3);

			for (var vIdx = 0; vIdx < versionCandidates.length; vIdx++) {
				var vNum = versionCandidates[vIdx];
				var N = 17 + 4 * vNum;
				var verInfo = QR_VERSIONS[vNum];
				if (!verInfo) continue;

				var stepXVec = { x: (tr.x - tl.x) / (N - 7), y: (tr.y - tl.y) / (N - 7) };
				var stepYVec = { x: (bl.x - tl.x) / (N - 7), y: (bl.y - tl.y) / (N - 7) };
				var originX = tl.x - 3 * stepXVec.x - 3 * stepYVec.x;
				var originY = tl.y - 3 * stepXVec.y - 3 * stepYVec.y;

				var sampled = [];
				var isFunc = [];
				var useNeighborhood = avgModule >= 3.0;
				for (var r = 0; r < N; r++) {
					var sRow = new Uint8Array(N);
					var fRow = new Uint8Array(N);
					for (var c = 0; c < N; c++) {
						var sx = Math.round(originX + c * stepXVec.x + r * stepYVec.x);
						var sy = Math.round(originY + c * stepXVec.y + r * stepYVec.y);
						if (sx >= 0 && sx < w && sy >= 0 && sy < h) {
							if (useNeighborhood && sx > 0 && sx < w - 1 && sy > 0 && sy < h - 1) {
								var darkVotes = 0;
								for (var ny = -1; ny <= 1; ny++) {
									var rOff = (sy + ny) * w;
									for (var nx = -1; nx <= 1; nx++) {
										if (gray[rOff + sx + nx] < threshold) darkVotes++;
									}
								}
								sRow[c] = darkVotes >= 5 ? 1 : 0;
							} else {
								sRow[c] = gray[sy * w + sx] < threshold ? 1 : 0;
							}
						}
					}
					sampled.push(sRow);
					isFunc.push(fRow);
				}

				for (var dy = -4; dy <= 4; dy++) {
					for (var dx = -4; dx <= 4; dx++) {
						if (3 + dy >= 0 && 3 + dy < N && 3 + dx >= 0 && 3 + dx < N) isFunc[3 + dy][3 + dx] = 1;
						if (3 + dy >= 0 && 3 + dy < N && N - 4 + dx >= 0 && N - 4 + dx < N) isFunc[3 + dy][N - 4 + dx] = 1;
						if (N - 4 + dy >= 0 && N - 4 + dy < N && 3 + dx >= 0 && 3 + dx < N) isFunc[N - 4 + dy][3 + dx] = 1;
					}
				}
				for (var t = 8; t < N - 8; t++) {
					isFunc[6][t] = 1;
					isFunc[t][6] = 1;
				}
				for (var a1 = 0; a1 < verInfo.align.length; a1++) {
					for (var a2 = 0; a2 < verInfo.align.length; a2++) {
						var ar = verInfo.align[a1];
						var ac = verInfo.align[a2];
						if ((ar === 6 && ac === 6) || (ar === 6 && ac === N - 7) || (ar === N - 7 && ac === 6)) continue;
						for (var ady = -2; ady <= 2; ady++) {
							for (var adx = -2; adx <= 2; adx++) {
								if (ar + ady >= 0 && ar + ady < N && ac + adx >= 0 && ac + adx < N) {
									isFunc[ar + ady][ac + adx] = 1;
								}
							}
						}
					}
				}
				for (var f = 0; f <= 8; f++) {
					isFunc[8][f] = 1;
					isFunc[f][8] = 1;
				}
				for (var f2 = 0; f2 < 8; f2++) {
					isFunc[8][N - 1 - f2] = 1;
					isFunc[N - 1 - f2][8] = 1;
				}

				var fmtStd = 0;
				for (var fb = 0; fb <= 5; fb++) fmtStd |= sampled[fb][8] << fb;
				fmtStd |= sampled[7][8] << 6;
				fmtStd |= sampled[8][8] << 7;
				fmtStd |= sampled[8][7] << 8;
				for (var fb2 = 9; fb2 < 15; fb2++) fmtStd |= sampled[8][14 - fb2] << fb2;

				var decodedMask = ((fmtStd ^ 0x5412) >>> 10) & 0x7;
				var maskCandidates = [0];
				if (decodedMask !== 0) maskCandidates.push(decodedMask);

				for (var mIdx = 0; mIdx < maskCandidates.length; mIdx++) {
					var maskPattern = maskCandidates[mIdx];
					var rawBits = [];
					var upward = true;
					for (var rightCol = N - 1; rightCol >= 1; rightCol -= 2) {
						if (rightCol === 6) rightCol = 5;
						for (var vert = 0; vert < N; vert++) {
							var rowIdx = upward ? N - 1 - vert : vert;
							for (var lr = 0; lr < 2; lr++) {
								var colIdx = rightCol - lr;
								if (!isFunc[rowIdx][colIdx]) {
									var bit = sampled[rowIdx][colIdx];
									if (evalMask(maskPattern, rowIdx, colIdx)) bit ^= 1;
									rawBits.push(bit);
								}
							}
						}
						upward = !upward;
					}

					var totalCw = verInfo.dataCodewords + verInfo.ecCodewords;
					var codewords = new Uint8Array(totalCw);
					for (var cwIdx = 0; cwIdx < totalCw; cwIdx++) {
						var cwByte = 0;
						for (var bIdx = 0; bIdx < 8; bIdx++) {
							cwByte = (cwByte << 1) | (rawBits[cwIdx * 8 + bIdx] || 0);
						}
						codewords[cwIdx] = cwByte;
					}

					// Reed-Solomon single-byte error correction via syndromes S0, S1
					var s0 = 0;
					var s1 = 0;
					for (var k = 0; k < totalCw; k++) {
						s0 ^= codewords[k];
						s1 = gfMul(s1, 2) ^ codewords[k];
					}
					if (s0 !== 0 && s1 !== 0) {
						var ratio = gfMul(s1, GF_INV[s0]);
						var errPow = GF_LOG[ratio];
						var errIdx = totalCw - 1 - errPow;
						if (errIdx >= 0 && errIdx < totalCw) {
							codewords[errIdx] ^= s0;
							for (var rb = 0; rb < 8; rb++) {
								rawBits[errIdx * 8 + rb] = (codewords[errIdx] >>> (7 - rb)) & 1;
							}
						}
					}

					var mode = 0;
					for (var i = 0; i < 4; i++) mode = (mode << 1) | (rawBits[i] || 0);
					if (mode !== 4) continue;

					var charCount = 0;
					for (var j = 0; j < 8; j++) charCount = (charCount << 1) | (rawBits[4 + j] || 0);
					if (charCount < 6 || charCount > verInfo.dataCodewords - 2) continue;

					var outChars = '';
					var validAscii = true;
					for (var ch = 0; ch < charCount; ch++) {
						var code = 0;
						for (var b = 0; b < 8; b++) {
							code = (code << 1) | (rawBits[12 + ch * 8 + b] || 0);
						}
						if (code < 32 || code > 126) {
							validAscii = false;
							break;
						}
						outChars += String.fromCharCode(code);
					}
					if (validAscii && outChars.length >= 6) {
						return outChars;
					}
				}
			}
		}
		return null;
	}

	// Attempt to locate 3 finder patterns in a binarized canvas frame and decode Version 1–6 QR matrix
	function tryDecodeCanvasQR(canvas, ctx) {
		var w = canvas.width;
		var h = canvas.height;
		if (w < 60 || h < 60) return null;

		var imgData = ctx.getImageData(0, 0, w, h).data;
		var gray = new Uint8Array(w * h);
		var sum = 0;
		for (var i = 0, p = 0; i < imgData.length; i += 4, p++) {
			var lum = (imgData[i] * 77 + imgData[i + 1] * 150 + imgData[i + 2] * 29) >> 8;
			gray[p] = lum;
			sum += lum;
		}
		var avgLum = sum / (w * h);
		var thresholds = [
			avgLum * 0.95,
			avgLum * 0.80,
			avgLum * 1.12,
			128,
			160,
			96
		];
		for (var tIdx = 0; tIdx < thresholds.length; tIdx++) {
			var res = decodeQrAtThreshold(gray, w, h, thresholds[tIdx]);
			if (res) return res;
		}

		// Also try center-cropped ROI (where the green scanner box is aimed)
		var cropW = Math.floor(w * 0.65);
		var cropH = Math.floor(h * 0.65);
		if (cropW >= 80 && cropH >= 80) {
			var startX = Math.floor((w - cropW) / 2);
			var startY = Math.floor((h - cropH) / 2);
			var roiGray = new Uint8Array(cropW * cropH);
			var roiSum = 0;
			for (var ry = 0; ry < cropH; ry++) {
				var srcRow = (startY + ry) * w + startX;
				var dstRow = ry * cropW;
				for (var rx = 0; rx < cropW; rx++) {
					var val = gray[srcRow + rx];
					roiGray[dstRow + rx] = val;
					roiSum += val;
				}
			}
			var roiAvg = roiSum / (cropW * cropH);
			var roiThresholds = [roiAvg * 0.95, roiAvg * 0.80, 128];
			for (var rt = 0; rt < roiThresholds.length; rt++) {
				var roiRes = decodeQrAtThreshold(roiGray, cropW, cropH, roiThresholds[rt]);
				if (roiRes) return roiRes;
			}
		}

		return null;
	}

	/* =========================================================================
	   4. CAMERA SCANNER CONTROLLER (Android + iOS Safari + Snapshot Fallback)
	   ========================================================================= */

	/**
	 * Start live mobile/tablet camera QR scanner
	 *
	 * @param {HTMLVideoElement} videoElement
	 * @param {function(string)} onScanSuccess
	 * @param {function(string)} onError
	 * @param {object} [options] { facingMode: 'environment'|'user', onCameraReady: function, onStatusChange: function }
	 * @returns {object} Scanner controller { stop, switchCamera, toggleTorch, isActive }
	 */
	function startCameraScanner(videoElement, onScanSuccess, onError, options) {
		options = options || {};
		var active = true;
		var stream = null;
		var rafId = null;
		var scanTimerId = null;
		var initTimeoutId = null;
		var readinessPollId = null;
		var currentFacing = options.facingMode || 'environment';
		var videoDevices = [];
		var currentDeviceIndex = -1;
		var torchEnabled = false;
		var lastScannedValue = '';
		var lastScannedTime = 0;
		var notifiedReady = false;
		var initTimeoutMs = options.timeoutMs || 10000;

		function ensureViewportVisible() {
			if (!videoElement) return;
			videoElement.hidden = false;
			videoElement.removeAttribute('hidden');
			videoElement.style.display = 'block';
			videoElement.style.visibility = 'visible';
			videoElement.style.opacity = '1';
			if (videoElement.parentElement) {
				videoElement.parentElement.hidden = false;
				videoElement.parentElement.removeAttribute('hidden');
				videoElement.parentElement.style.display = 'block';
				videoElement.parentElement.classList.add('is-camera-active');
			}
		}

		// Ensure the video element and its container are visible in the DOM before requesting camera stream
		ensureViewportVisible();

		var offCanvas = document.createElement('canvas');
		var offCtx = offCanvas.getContext('2d', { willReadFrequently: true });

		var detector = null;
		if ('BarcodeDetector' in window) {
			try {
				detector = new window.BarcodeDetector({ formats: ['qr_code'] });
			} catch (e) {
				detector = null;
			}
		}

		function emitStatus(statusObj) {
			if (typeof options.onStatusChange === 'function') {
				try {
					options.onStatusChange(statusObj);
				} catch (e) {}
			}
		}

		function clearInitTimers() {
			if (initTimeoutId) {
				clearTimeout(initTimeoutId);
				initTimeoutId = null;
			}
			if (readinessPollId) {
				clearInterval(readinessPollId);
				readinessPollId = null;
			}
		}

		function stopStream() {
			clearInitTimers();
			if (rafId) {
				cancelAnimationFrame(rafId);
				rafId = null;
			}
			if (scanTimerId) {
				clearTimeout(scanTimerId);
				scanTimerId = null;
			}
			if (videoElement) {
				try {
					videoElement.onloadedmetadata = null;
					videoElement.onloadeddata = null;
					videoElement.onplaying = null;
					videoElement.pause();
					videoElement.srcObject = null;
					videoElement.removeAttribute('src');
				} catch (e) {}
			}
			if (stream) {
				stream.getTracks().forEach(function (track) {
					try {
						track.stop();
					} catch (e) {}
				});
				stream = null;
			}
		}

		function syncCheckinDomUi(activeState, errMsg) {
			try {
				var loaderEl = document.getElementById('tarbwy-camera-loading');
				if (loaderEl) {
					loaderEl.hidden = true;
					loaderEl.style.display = 'none';
				}
				var errBanner = document.getElementById('tarbwy-camera-error-banner');
				if (errBanner) {
					if (errMsg) {
						errBanner.textContent = errMsg;
						errBanner.hidden = false;
						errBanner.style.display = 'block';
					} else if (activeState) {
						errBanner.hidden = true;
						errBanner.style.display = 'none';
					}
				}
			} catch (e) {}
		}

		function stop() {
			active = false;
			stopStream();
		}

		function failWithError(message) {
			if (!active) return;
			active = false;
			stopStream();
			syncCheckinDomUi(false, message);
			if (typeof onError === 'function') {
				onError(message);
			}
		}

		function formatCameraError(err) {
			var name = (err && err.name) ? String(err.name) : '';
			var msg = (err && err.message) ? String(err.message) : '';
			if (name === 'TimeoutError' || msg === 'CAMERA_INIT_TIMEOUT') {
				return 'استغرق تشغيل الكاميرا وقتاً أطول من المعتاد (انتهت مهلة التهيئة). يرجى التأكد من الموافقة على إذن الكاميرا في المتصفح وعدم انشغالها بتطبيق آخر، ثم الضغط على «تشغيل كاميرا المسح المباشر» مجدداً.';
			}
			if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
				return 'تم رفض أو حظر صلاحية الكاميرا في المتصفح. يرجى الضغط على أيقونة القفل 🔒 بجانب الرابط في شريط العنوان والسماح بالكاميرا ثم المحاولة مرة أخرى.';
			}
			if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
				return 'لم يتم العثور على كاميرا متصلة بهذا الجهاز. يمكنك استخدام زر «التقاط صورة لرمز QR» أو البحث اليدوي بالاسم أو رقم التذكرة.';
			}
			if (name === 'NotReadableError' || name === 'TrackStartError') {
				return 'الكاميرا مشغولة حالياً بواسطة تطبيق آخر أو تعذر بدء البث من النظام. يرجى إغلاق التطبيقات الأخرى التي تستخدم الكاميرا ثم المحاولة مجدداً.';
			}
			if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
				return 'إعدادات دقة الكاميرا غير مدعومة على هذا الجهاز. يرجى المحاولة مرة أخرى أو استخدام زر «التقاط صورة لرمز QR».';
			}
			if (name === 'AbortError') {
				return 'تمت مقاطعة تشغيل الكاميرا قبل اكتمال البث. يرجى الضغط على «تشغيل كاميرا المسح المباشر» للمحاولة مجدداً.';
			}
			return 'تعذر فتح الكاميرا المباشرة (' + (name || 'خطأ غير متوقع') + '). يرجى التأكد من منح صلاحية الكاميرا للمتصفح أو استخدام زر «التقاط صورة لرمز QR» أدناه.';
		}

		function handleDetectedRaw(rawValue) {
			var clean = normalizeScannedPayload(rawValue);
			if (!clean || clean.length < 4) return;
			var now = Date.now();
			// Debounce identical scan within 2.5 seconds
			if (clean === lastScannedValue && now - lastScannedTime < 2500) {
				return;
			}
			lastScannedValue = clean;
			lastScannedTime = now;
			onScanSuccess(clean);
		}

		function runCanvasFallbackFrame() {
			if (!active || !videoElement || videoElement.videoWidth <= 0) return;
			try {
				var vw = videoElement.videoWidth;
				var vh = videoElement.videoHeight;
				var scale = Math.min(1, 520 / Math.max(vw, vh));
				offCanvas.width = Math.max(120, Math.floor(vw * scale));
				offCanvas.height = Math.max(120, Math.floor(vh * scale));
				offCtx.drawImage(videoElement, 0, 0, offCanvas.width, offCanvas.height);

				var decoded = tryDecodeCanvasQR(offCanvas, offCtx);
				if (decoded) {
					handleDetectedRaw(decoded);
				}
			} catch (err) {
				// Ignore single-frame decode errors
			}
		}

		function scheduleNextScan(delayMs) {
			if (!active) return;
			scanTimerId = setTimeout(function () {
				if (!active) return;
				rafId = requestAnimationFrame(scanLoop);
			}, delayMs || 140);
		}

		function scanLoop() {
			if (!active) return;

			if (videoElement && videoElement.readyState >= 2 && videoElement.videoWidth > 0 && !videoElement.paused) {
				// 1. Try native BarcodeDetector first if available, AND fallback to pure-JS Canvas decoder
				if (detector) {
					detector
						.detect(videoElement)
						.then(function (barcodes) {
							if (!active) return;
							if (barcodes && barcodes.length > 0 && barcodes[0].rawValue) {
								handleDetectedRaw(barcodes[0].rawValue);
							} else {
								runCanvasFallbackFrame();
							}
							scheduleNextScan(140);
						})
						.catch(function () {
							detector = null;
							runCanvasFallbackFrame();
							scheduleNextScan(140);
						});
					return;
				}

				// 2. Pure-JS Canvas QR Decoder (for iOS Safari / browsers without BarcodeDetector)
				runCanvasFallbackFrame();
			}

			scheduleNextScan(160);
		}

		function notifyCapabilities() {
			if (!stream) return;
			var hasTorch = false;
			try {
				var track = stream.getVideoTracks()[0];
				if (track && typeof track.getCapabilities === 'function') {
					var caps = track.getCapabilities();
					hasTorch = !!(caps && caps.torch);
				}
			} catch (e) {}

			var capPayload = {
				status: 'active',
				hasTorch: hasTorch,
				torchSupported: hasTorch,
				facingMode: currentFacing,
				videoWidth: videoElement ? videoElement.videoWidth : 0,
				videoHeight: videoElement ? videoElement.videoHeight : 0
			};

			syncCheckinDomUi(true, null);

			if (typeof options.onCameraReady === 'function') {
				try {
					options.onCameraReady(capPayload);
				} catch (e) {}
			}
			emitStatus(capPayload);
		}

		function attachAndPlayStream(mediaStream) {
			if (!active) {
				if (mediaStream && mediaStream.getTracks) {
					mediaStream.getTracks().forEach(function (t) {
						try {
							t.stop();
						} catch (e) {}
					});
				}
				return;
			}

			var videoTracks = mediaStream && mediaStream.getVideoTracks ? mediaStream.getVideoTracks() : [];
			if (!videoTracks || !videoTracks.length) {
				failWithError('تم الاتصال بالكاميرا ولكن لم يتم استلام بث فيديو صالح من الجهاز. يرجى المحاولة مرة أخرى.');
				return;
			}

			stream = mediaStream;
			notifiedReady = false;
			ensureViewportVisible();

			// Enumerate video devices now that permission is granted so front/rear camera switching works across all lenses
			if (navigator.mediaDevices && typeof navigator.mediaDevices.enumerateDevices === 'function') {
				navigator.mediaDevices
					.enumerateDevices()
					.then(function (devices) {
						videoDevices = (devices || []).filter(function (d) {
							return d && d.kind === 'videoinput';
						});
						var activeTrack = videoTracks[0];
						if (activeTrack && typeof activeTrack.getSettings === 'function') {
							var activeDeviceId = activeTrack.getSettings().deviceId;
							if (activeDeviceId) {
								for (var i = 0; i < videoDevices.length; i++) {
									if (videoDevices[i].deviceId === activeDeviceId) {
										currentDeviceIndex = i;
										break;
									}
								}
							}
						}
					})
					.catch(function () {});
			}

			// Finalize scanner readiness ONLY after video stream is playing and has non-zero dimensions
			function finalizeScannerStart() {
				if (!active || notifiedReady) return;
				if (!videoElement || videoElement.readyState < 2 || videoElement.videoWidth <= 0) {
					return;
				}
				notifiedReady = true;
				clearInitTimers();
				ensureViewportVisible();
				notifyCapabilities();
				rafId = requestAnimationFrame(scanLoop);
			}

			function startVideoPlayback() {
				if (!active || notifiedReady || !videoElement) return;
				videoElement.muted = true;
				videoElement.defaultMuted = true;
				videoElement.playsInline = true;

				try {
					var playPromise = videoElement.play();
					if (playPromise && typeof playPromise.then === 'function') {
						playPromise
							.then(function () {
								if (!active) return;
								finalizeScannerStart();
							})
							.catch(function (playErr) {
								if (!active) return;
								// Retry once with forced muted state (required by iOS Safari / Android autoplay policy)
								videoElement.muted = true;
								setTimeout(function () {
									if (!active || notifiedReady) return;
									try {
										var retryPromise = videoElement.play();
										if (retryPromise && typeof retryPromise.then === 'function') {
											retryPromise.then(finalizeScannerStart).catch(function (err2) {
												failWithError(formatCameraError(err2 || playErr));
											});
										}
									} catch (err3) {
										failWithError(formatCameraError(err3 || playErr));
									}
								}, 120);
							});
					} else {
						finalizeScannerStart();
					}
				} catch (syncErr) {
					failWithError(formatCameraError(syncErr));
				}
			}

			// Poll every 60ms so as soon as videoElement.videoWidth > 0 and readyState >= 2, scanner starts
			readinessPollId = setInterval(function () {
				if (!active) {
					clearInitTimers();
					return;
				}
				if (videoElement && videoElement.readyState >= 2 && videoElement.videoWidth > 0) {
					if (videoElement.paused) {
						try {
							videoElement.play().catch(function () {});
						} catch (e) {}
					}
					finalizeScannerStart();
				}
			}, 60);

			// Single clean event sequence: attach srcObject -> wait for loadedmetadata (or readyState >= 1) -> call play() once
			var playTriggered = false;
			function triggerPlayOnce() {
				if (!active || playTriggered) return;
				playTriggered = true;
				startVideoPlayback();
			}

			videoElement.onloadedmetadata = triggerPlayOnce;
			videoElement.onloadeddata = triggerPlayOnce;
			videoElement.onplaying = finalizeScannerStart;

			try {
				if ('srcObject' in videoElement) {
					videoElement.srcObject = mediaStream;
				} else if ('mozSrcObject' in videoElement) {
					videoElement.mozSrcObject = mediaStream;
				} else if (window.URL && window.URL.createObjectURL) {
					videoElement.src = window.URL.createObjectURL(mediaStream);
				}
			} catch (attachErr) {
				failWithError(formatCameraError(attachErr));
				return;
			}

			// If metadata is already available synchronously (or if loadedmetadata doesn't fire within 450ms on some WebViews), trigger play
			if (videoElement.readyState >= 1) {
				triggerPlayOnce();
			} else {
				setTimeout(triggerPlayOnce, 450);
			}
		}

		function callGetUserMediaWithTimeout(constraints, timeoutMs) {
			return new Promise(function (resolve, reject) {
				var settled = false;
				var timer = setTimeout(function () {
					if (settled) return;
					settled = true;
					var tErr = new Error('CAMERA_INIT_TIMEOUT');
					tErr.name = 'TimeoutError';
					reject(tErr);
				}, timeoutMs || 7500);

				var gumPromise = null;
				try {
					if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
						gumPromise = navigator.mediaDevices.getUserMedia(constraints);
					} else {
						var legacyGum =
							navigator.getUserMedia ||
							navigator.webkitGetUserMedia ||
							navigator.mozGetUserMedia ||
							navigator.msGetUserMedia;
						if (legacyGum) {
							gumPromise = new Promise(function (res, rej) {
								legacyGum.call(navigator, constraints, res, rej);
							});
						}
					}
				} catch (err) {
					clearTimeout(timer);
					reject(err);
					return;
				}

				if (!gumPromise || typeof gumPromise.then !== 'function') {
					clearTimeout(timer);
					var unsupportedErr = new Error('GETUSERMEDIA_UNSUPPORTED');
					unsupportedErr.name = 'NotSupportedError';
					reject(unsupportedErr);
					return;
				}

				gumPromise
					.then(function (mediaStream) {
						if (settled) {
							if (mediaStream && mediaStream.getTracks) {
								mediaStream.getTracks().forEach(function (t) {
									try {
										t.stop();
									} catch (e) {}
								});
							}
							return;
						}
						settled = true;
						clearTimeout(timer);
						resolve(mediaStream);
					})
					.catch(function (err) {
						if (settled) return;
						settled = true;
						clearTimeout(timer);
						reject(err);
					});
			});
		}

		function requestCameraStream(facing, preferredDeviceId) {
			if (!videoElement) {
				failWithError('عنصر معاينة الفيديو غير موجود في الصفحة. يرجى تحديث الصفحة والمحاولة مجدداً.');
				return;
			}

			if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
				failWithError('يتطلب تشغيل الكاميرا اتصالاً آمناً عبر بروتوكول HTTPS. يمكنك استخدام زر «التقاط صورة لرمز QR» أو البحث اليدوي أدناه.');
				return;
			}

			var hasMediaApi =
				(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') ||
				navigator.getUserMedia ||
				navigator.webkitGetUserMedia ||
				navigator.mozGetUserMedia;

			if (!hasMediaApi) {
				failWithError('متصفح الجهاز لا يدعم تشغيل البث المباشر للكاميرا. يرجى استخدام زر «التقاط صورة لرمز QR» أو البحث اليدوي.');
				return;
			}

			clearInitTimers();
			// Hard overall initialization timeout so the UI NEVER remains stuck on "جاري تشغيل الكاميرا..."
			initTimeoutId = setTimeout(function () {
				if (active && !notifiedReady) {
					var timeoutErr = new Error('CAMERA_INIT_TIMEOUT');
					timeoutErr.name = 'TimeoutError';
					failWithError(formatCameraError(timeoutErr));
				}
			}, initTimeoutMs);

			emitStatus({ status: 'requesting', message: 'جاري طلب إذن الكاميرا وفتح البث المباشر...' });

			// Ensure iOS Safari & Android Chrome attributes are set before attaching stream
			ensureViewportVisible();
			videoElement.setAttribute('playsinline', '');
			videoElement.setAttribute('webkit-playsinline', '');
			videoElement.setAttribute('autoplay', '');
			videoElement.setAttribute('muted', '');
			videoElement.playsInline = true;
			videoElement.muted = true;
			videoElement.defaultMuted = true;
			videoElement.autoplay = true;

			// Progressive constraint fallback for Android Chrome, Samsung Internet, and iOS Safari
			var constraintAttempts = [];
			if (preferredDeviceId) {
				constraintAttempts.push({
					audio: false,
					video: {
						deviceId: { exact: preferredDeviceId },
						width: { ideal: 1280 },
						height: { ideal: 720 }
					}
				});
				constraintAttempts.push({
					audio: false,
					video: {
						deviceId: { exact: preferredDeviceId }
					}
				});
			}
			constraintAttempts.push(
				{
					audio: false,
					video: {
						facingMode: { ideal: facing },
						width: { ideal: 1280 },
						height: { ideal: 720 }
					}
				},
				{
					audio: false,
					video: {
						facingMode: facing
					}
				},
				{
					audio: false,
					video: true
				}
			);

			var lastError = null;

			function tryConstraint(idx) {
				if (!active) return;
				if (idx >= constraintAttempts.length) {
					failWithError(formatCameraError(lastError));
					return;
				}
				callGetUserMediaWithTimeout(constraintAttempts[idx], 7500)
					.then(function (mediaStream) {
						if (!active) {
							if (mediaStream && mediaStream.getTracks) {
								mediaStream.getTracks().forEach(function (t) {
									try {
										t.stop();
									} catch (e) {}
								});
							}
							return;
						}
						attachAndPlayStream(mediaStream);
					})
					.catch(function (err) {
						if (!active) return;
						lastError = err;
						var errName = (err && err.name) ? String(err.name) : '';
						// Do not retry other constraints if user explicitly denied permission or timed out waiting for prompt
						if (
							errName === 'NotAllowedError' ||
							errName === 'PermissionDeniedError' ||
							errName === 'SecurityError' ||
							errName === 'TimeoutError'
						) {
							failWithError(formatCameraError(err));
							return;
						}
						tryConstraint(idx + 1);
					});
			}

			tryConstraint(0);
		}

		function switchCamera() {
			if (!active) return;
			currentFacing = currentFacing === 'environment' ? 'user' : 'environment';
			torchEnabled = false;
			notifiedReady = false;
			stopStream();

			if (videoDevices.length > 1) {
				currentDeviceIndex = (currentDeviceIndex + 1) % videoDevices.length;
				var nextDevice = videoDevices[currentDeviceIndex];
				requestCameraStream(currentFacing, nextDevice ? nextDevice.deviceId : null);
			} else {
				requestCameraStream(currentFacing, null);
			}
		}

		function toggleTorch() {
			if (!stream) return Promise.resolve(false);
			try {
				var track = stream.getVideoTracks()[0];
				if (track && typeof track.applyConstraints === 'function') {
					torchEnabled = !torchEnabled;
					return track
						.applyConstraints({ advanced: [{ torch: torchEnabled }] })
						.then(function () {
							return torchEnabled;
						})
						.catch(function () {
							torchEnabled = false;
							return false;
						});
				}
			} catch (e) {}
			return Promise.resolve(false);
		}

		requestCameraStream(currentFacing, null);

		return {
			stop: stop,
			switchCamera: switchCamera,
			toggleTorch: toggleTorch,
			isActive: function () {
				return active && notifiedReady;
			}
		};
	}

	/**
	 * Decode a QR code from a camera snapshot or uploaded image file (100% iOS/Android fallback)
	 *
	 * @param {File} imageFile
	 * @param {function(string)} onScanSuccess
	 * @param {function(string)} onError
	 */
	function scanImageFile(imageFile, onScanSuccess, onError) {
		if (!imageFile) return;

		var url = URL.createObjectURL(imageFile);
		var img = new Image();
		img.onload = function () {
			URL.revokeObjectURL(url);

			function fallbackCanvasDecode() {
				try {
					var canvas = document.createElement('canvas');
					var scale = Math.min(1, 720 / Math.max(img.width, img.height));
					canvas.width = Math.max(120, Math.floor(img.width * scale));
					canvas.height = Math.max(120, Math.floor(img.height * scale));
					var ctx = canvas.getContext('2d', { willReadFrequently: true });
					ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

					var decoded = tryDecodeCanvasQR(canvas, ctx);
					if (decoded) {
						onScanSuccess(normalizeScannedPayload(decoded));
					} else {
						onError('لم يتم العثور على رمز QR واضح في الصورة الملتقطة. يرجى تقريب الكاميرا من الرمز أو البحث بالاسم/رقم التذكرة.');
					}
				} catch (e) {
					onError('تعذر تحليل الصورة الملتقطة. يرجى استخدام البحث اليدوي.');
				}
			}

			if ('BarcodeDetector' in window) {
				try {
					var det = new window.BarcodeDetector({ formats: ['qr_code'] });
					det
						.detect(img)
						.then(function (barcodes) {
							if (barcodes && barcodes.length > 0 && barcodes[0].rawValue) {
								onScanSuccess(normalizeScannedPayload(barcodes[0].rawValue));
							} else {
								fallbackCanvasDecode();
							}
						})
						.catch(function () {
							fallbackCanvasDecode();
						});
					return;
				} catch (e) {
					// Fall through to canvas decode
				}
			}

			fallbackCanvasDecode();
		};
		img.onerror = function () {
			URL.revokeObjectURL(url);
			onError('تعذر قراءة ملف الصورة.');
		};
		img.src = url;
	}

	function renderSVG(container, tokenString, size) {
		if (!container) return '';
		var svgMarkup = renderQRCodeSVG(tokenString, size);
		container.innerHTML = svgMarkup;
		return svgMarkup;
	}

	window.TARBWY_QR_ENGINE_VERSION = '1.5.0';
	window.TarbwyQR = {
		version: '1.5.0',
		renderSVG: renderSVG,
		renderQRCodeSVG: renderQRCodeSVG,
		startCamera: startCameraScanner,
		startCameraScanner: startCameraScanner,
		scanImageFile: scanImageFile,
		normalizeScannedPayload: normalizeScannedPayload,
		playFeedbackSound: playFeedbackSound,
		playScanFeedback: playFeedbackSound,
		setAudioMuted: setAudioMuted
	};
})(window);
