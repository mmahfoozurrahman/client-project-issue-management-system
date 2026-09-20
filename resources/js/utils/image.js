/**
 * Convert any Image Blob or File to a high-quality JPEG File.
 * Draws on an HTML5 canvas with a white background to prevent transparent PNGs from becoming black boxes.
 *
 * @param {Blob|File} blob
 * @param {string} preferredFilename
 * @param {number} quality
 * @returns {Promise<File>}
 */
export function convertBlobToJpegFile(blob, preferredFilename = 'screenshot.jpg', quality = 0.92) {
    return new Promise((resolve) => {
        const objectUrl = URL.createObjectURL(blob);
        const img = new Image();

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth || img.width || 800;
            canvas.height = img.naturalHeight || img.height || 600;
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
            }

            canvas.toBlob(
                (jpegBlob) => {
                    const finalBlob = jpegBlob || blob;
                    const base = preferredFilename.replace(/\.[^/.]+$/, '');
                    const finalName = `${base}.jpg`;
                    const jpegFile = new File([finalBlob], finalName, {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    });
                    resolve(jpegFile);
                },
                'image/jpeg',
                quality
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            const base = preferredFilename.replace(/\.[^/.]+$/, '');
            const fallbackFile = new File([blob], `${base}.jpg`, {
                type: 'image/jpeg',
                lastModified: Date.now(),
            });
            resolve(fallbackFile);
        };

        img.src = objectUrl;
    });
}

/**
 * Generate a timestamped screenshot filename.
 *
 * @param {number} counter
 * @param {number} total
 * @returns {string}
 */
export function getScreenshotFilename(counter = 0, total = 1) {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const timestamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
    const suffix = total > 1 ? `-${counter}` : '';
    return `screenshot-${timestamp}${suffix}.jpg`;
}

/**
 * Format bytes to human readable string (B, KB, MB).
 *
 * @param {number} value
 * @returns {string}
 */
export function formatFileSize(value) {
    if (!value && value !== 0) {
        return 'Unknown size';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}
