const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
const TARGET_MAX = 200 * 1024;

export async function compressWithWatermark(file, role = 'user') {
  if (!file) return null;
  if (file.size > MAX_UPLOAD_BYTES) {
    throw new Error('Select a file under 20MB');
  }

  const meta = normalizeMeta(role);
  const dataUrl = await readFileAsDataURL(file);
  const img = await loadImage(dataUrl);
  const canvas = document.createElement('canvas');
  let maxSide = 1280;

  for (let pass = 0; pass < 4; pass += 1) {
    const ratio = Math.min(1, maxSide / Math.max(img.width, img.height));
    canvas.width = Math.max(320, Math.round(img.width * ratio));
    canvas.height = Math.max(240, Math.round(img.height * ratio));

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    const stampTop = `Time: ${meta.time}`;
    const stampBottom = `Role: ${meta.role} | Email: ${meta.email}`;
    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    ctx.fillRect(0, canvas.height - 48, canvas.width, 48);
    ctx.fillStyle = '#fff';
    ctx.font = '14px sans-serif';
    ctx.textBaseline = 'top';
    ctx.fillText(stampTop, 12, canvas.height - 44);
    ctx.fillText(stampBottom, 12, canvas.height - 24);

    let quality = 0.82;
    for (let i = 0; i < 8; i += 1) {
      const output = canvas.toDataURL('image/jpeg', quality);
      if (output.length * 0.75 <= TARGET_MAX) {
        return output;
      }
      quality = Math.max(0.3, quality - 0.08);
    }

    maxSide = Math.round(maxSide * 0.8);
  }

  throw new Error('Could not compress image below 200KB. Please choose a smaller image.');
}

function normalizeMeta(input) {
  if (input && typeof input === 'object') {
    return {
      role: String(input.role || 'user'),
      email: String(input.email || 'unknown'),
      time: String(input.time || new Date().toISOString())
    };
  }
  return {
    role: String(input || 'user'),
    email: 'unknown',
    time: new Date().toISOString()
  };
}

function readFileAsDataURL(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

function loadImage(dataUrl) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = dataUrl;
  });
}
