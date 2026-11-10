import React from 'react';

interface LocalQRCodeProps {
  token: string;
  size?: number;
}

const GF_EXP = new Uint8Array(512);
const GF_LOG = new Uint8Array(256);
(() => {
  let x = 1;
  for (let i = 0; i < 255; i++) {
    GF_EXP[i] = x;
    GF_LOG[x] = i;
    x <<= 1;
    if (x & 0x100) x ^= 0x11d;
  }
  for (let i = 255; i < 512; i++) {
    GF_EXP[i] = GF_EXP[i - 255];
  }
})();

function gfMul(a: number, b: number): number {
  if (a === 0 || b === 0) return 0;
  return GF_EXP[GF_LOG[a] + GF_LOG[b]];
}

function rsGeneratorPoly(degree: number): Uint8Array {
  let poly = new Uint8Array([1]);
  for (let i = 0; i < degree; i++) {
    const next = new Uint8Array(poly.length + 1);
    const root = GF_EXP[i];
    for (let j = 0; j < poly.length; j++) {
      next[j] ^= poly[j];
      next[j + 1] ^= gfMul(poly[j], root);
    }
    poly = next;
  }
  return poly;
}

function rsComputeRemainder(data: number[], ecLen: number): Uint8Array {
  const gen = rsGeneratorPoly(ecLen);
  const rem = new Uint8Array(ecLen);
  for (let i = 0; i < data.length; i++) {
    const factor = data[i] ^ rem[0];
    for (let k = 0; k < ecLen - 1; k++) {
      rem[k] = rem[k + 1];
    }
    rem[ecLen - 1] = 0;
    if (factor !== 0) {
      for (let j = 0; j < ecLen; j++) {
        rem[j] ^= gfMul(gen[j + 1], factor);
      }
    }
  }
  return rem;
}

const QR_VERSIONS = [
  null,
  { size: 21, dataBytes: 19, ecBytesPerBlock: 7, blocks: 1, align: [] as number[] },
  { size: 25, dataBytes: 34, ecBytesPerBlock: 10, blocks: 1, align: [6, 18] },
  { size: 29, dataBytes: 55, ecBytesPerBlock: 15, blocks: 1, align: [6, 22] },
  { size: 33, dataBytes: 80, ecBytesPerBlock: 20, blocks: 1, align: [6, 26] },
  { size: 37, dataBytes: 108, ecBytesPerBlock: 26, blocks: 1, align: [6, 30] },
  { size: 41, dataBytes: 136, ecBytesPerBlock: 18, blocks: 2, align: [6, 34] },
];

function buildIsoQrMatrix(text: string): { matrix: Uint8Array[]; size: number } {
  const utf8 = Array.from(new TextEncoder().encode(String(text || 'TARBWY')));
  let ver = 3;
  for (let v = 1; v <= 6; v++) {
    const info = QR_VERSIONS[v]!;
    if (utf8.length + 2 <= info.dataBytes) {
      ver = v;
      break;
    }
  }
  const vInfo = QR_VERSIONS[ver]!;
  const payload = utf8.slice(0, vInfo.dataBytes - 2);

  const bits: number[] = [];
  const pushBits = (val: number, len: number) => {
    for (let i = len - 1; i >= 0; i--) {
      bits.push((val >>> i) & 1);
    }
  };
  pushBits(0b0100, 4);
  pushBits(payload.length, 8);
  for (const b of payload) pushBits(b, 8);
  const maxDataBits = vInfo.dataBytes * 8;
  for (let t = 0; t < 4 && bits.length < maxDataBits; t++) bits.push(0);
  while (bits.length % 8 !== 0) bits.push(0);

  const codewords: number[] = [];
  for (let bIdx = 0; bIdx < bits.length; bIdx += 8) {
    let byteVal = 0;
    for (let k = 0; k < 8; k++) byteVal = (byteVal << 1) | bits[bIdx + k];
    codewords.push(byteVal);
  }
  const padBytes = [0xec, 0x11];
  let padIdx = 0;
  while (codewords.length < vInfo.dataBytes) {
    codewords.push(padBytes[padIdx % 2]);
    padIdx++;
  }

  let finalCodewords: number[] = [];
  if (vInfo.blocks === 1) {
    const ec = rsComputeRemainder(codewords, vInfo.ecBytesPerBlock);
    finalCodewords = codewords.concat(Array.from(ec));
  } else {
    const half = vInfo.dataBytes / 2;
    const b1 = codewords.slice(0, half);
    const b2 = codewords.slice(half);
    const ec1 = rsComputeRemainder(b1, vInfo.ecBytesPerBlock);
    const ec2 = rsComputeRemainder(b2, vInfo.ecBytesPerBlock);
    for (let i = 0; i < half; i++) {
      finalCodewords.push(b1[i], b2[i]);
    }
    for (let j = 0; j < vInfo.ecBytesPerBlock; j++) {
      finalCodewords.push(ec1[j], ec2[j]);
    }
  }

  const size = vInfo.size;
  const matrix = Array.from({ length: size }, () => new Uint8Array(size));
  const reserved = Array.from({ length: size }, () => new Uint8Array(size));

  const setModule = (r: number, c: number, dark: boolean | number, isReserved: boolean) => {
    if (r < 0 || r >= size || c < 0 || c >= size) return;
    matrix[r][c] = dark ? 1 : 0;
    if (isReserved) reserved[r][c] = 1;
  };

  const placeFinder = (row: number, col: number) => {
    for (let dr = -1; dr <= 7; dr++) {
      for (let dc = -1; dc <= 7; dc++) {
        const rr = row + dr;
        const cc = col + dc;
        if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue;
        const inOuter = dr >= 0 && dr <= 6 && dc >= 0 && dc <= 6;
        const isBorder = dr === 0 || dr === 6 || dc === 0 || dc === 6;
        const isInner = dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4;
        setModule(rr, cc, inOuter && (isBorder || isInner), true);
      }
    }
  };

  placeFinder(0, 0);
  placeFinder(0, size - 7);
  placeFinder(size - 7, 0);

  if (vInfo.align.length >= 2) {
    for (const ar of vInfo.align) {
      for (const ac of vInfo.align) {
        if ((ar < 9 && ac < 9) || (ar < 9 && ac > size - 10) || (ar > size - 10 && ac < 9)) continue;
        for (let dr = -2; dr <= 2; dr++) {
          for (let dc = -2; dc <= 2; dc++) {
            const dark = Math.max(Math.abs(dr), Math.abs(dc)) !== 1;
            setModule(ar + dr, ac + dc, dark, true);
          }
        }
      }
    }
  }

  for (let tIdx = 8; tIdx < size - 8; tIdx++) {
    if (!reserved[6][tIdx]) setModule(6, tIdx, tIdx % 2 === 0, true);
    if (!reserved[tIdx][6]) setModule(tIdx, 6, tIdx % 2 === 0, true);
  }

  setModule(size - 8, 8, true, true);
  for (let f = 0; f < 9; f++) {
    if (!reserved[8][f]) reserved[8][f] = 1;
    if (!reserved[f][8]) reserved[f][8] = 1;
  }
  for (let f2 = 0; f2 < 8; f2++) {
    if (!reserved[8][size - 1 - f2]) reserved[8][size - 1 - f2] = 1;
    if (!reserved[size - 1 - f2][8]) reserved[size - 1 - f2][8] = 1;
  }

  const allBits: number[] = [];
  for (const cw of finalCodewords) {
    for (let bit = 7; bit >= 0; bit--) allBits.push((cw >>> bit) & 1);
  }

  let bitPtr = 0;
  let upward = true;
  for (let rightCol = size - 1; rightCol >= 1; rightCol -= 2) {
    if (rightCol === 6) rightCol = 5;
    for (let vert = 0; vert < size; vert++) {
      const r = upward ? size - 1 - vert : vert;
      for (let lr = 0; lr < 2; lr++) {
        const c = rightCol - lr;
        if (!reserved[r][c]) {
          const dataBit = bitPtr < allBits.length ? allBits[bitPtr++] : 0;
          const masked = dataBit ^ ((r + c) % 2 === 0 ? 1 : 0);
          matrix[r][c] = masked ? 1 : 0;
        }
      }
    }
    upward = !upward;
  }

  const formatBits = 0x77c4;
  for (let i = 0; i <= 5; i++) setModule(i, 8, (formatBits >>> i) & 1, true);
  setModule(7, 8, (formatBits >>> 6) & 1, true);
  setModule(8, 8, (formatBits >>> 7) & 1, true);
  setModule(8, 7, (formatBits >>> 8) & 1, true);
  for (let i2 = 9; i2 < 15; i2++) setModule(8, 14 - i2, (formatBits >>> i2) & 1, true);

  for (let j = 0; j < 8; j++) setModule(8, size - 1 - j, (formatBits >>> j) & 1, true);
  for (let j2 = 8; j2 < 15; j2++) setModule(size - 15 + j2, 8, (formatBits >>> j2) & 1, true);

  return { matrix, size };
}

/**
 * ISO/IEC 18004 Scannable QR Code SVG Renderer (Zero External CDN / Zero PII)
 * Encodes the opaque cryptographic token with Galois Field GF(256) Reed-Solomon ECC so
 * real Android and iOS cameras can scan it directly.
 */
export const LocalQRCode: React.FC<LocalQRCodeProps> = ({ token, size = 168 }) => {
  const { matrix, size: modCount } = buildIsoQrMatrix(token || 'TARBWY_TOKEN');
  const quietZone = 3;
  const totalModules = modCount + quietZone * 2;
  const cell = size / totalModules;

  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox={`0 0 ${size} ${size}`}
      width={size}
      height={size}
      shapeRendering="crispEdges"
      className="mx-auto border border-stone-200 bg-white"
      role="img"
      aria-label="رمز الاستجابة السريعة QR"
    >
      <rect width="100%" height="100%" fill="#FFFFFF" />
      {matrix.map((row, rIdx) =>
        Array.from(row).map((val, cIdx) =>
          val === 1 ? (
            <rect
              key={`${rIdx}-${cIdx}`}
              x={((cIdx + quietZone) * cell).toFixed(2)}
              y={((rIdx + quietZone) * cell).toFixed(2)}
              width={cell.toFixed(2)}
              height={cell.toFixed(2)}
              fill="#0F172A"
            />
          ) : null
        )
      )}
    </svg>
  );
};
