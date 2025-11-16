// legacyPainter.js - reuses dynamic texture painting logic from the legacy designer

export class LegacyPainter {
  constructor(sceneManager, options = {}) {
    if (!sceneManager) {
      throw new Error('LegacyPainter requires a SceneManager instance');
    }
    this.sceneManager = sceneManager;
    this.maxHistory = options.maxHistory ?? 20;
    this.history = [];
    this.currentBase = { left: '#ffffff', right: '#cccccc' };
    this._syncHandles();
    this._ensureInitialState();
  }

  _syncHandles() {
    this.paintTexture = this.sceneManager.getPaintTexture();
    this.paintContext = this.sceneManager.getPaintContext();
  }

  _ensureInitialState() {
    if (!this.paintContext || !this.paintTexture) {
      return;
    }
    this.history = [];
    this.saveState();
  }

  saveState() {
    this._syncHandles();
    if (!this.paintContext || !this.paintTexture) {
      return;
    }
    try {
      const size = this.paintTexture.getSize();
      const snapshot = this.paintContext.getImageData(0, 0, size.width, size.height);
      this.history.push(snapshot);
      if (this.history.length > this.maxHistory) {
        this.history.shift();
      }
    } catch (err) {
      console.warn('LegacyPainter.saveState failed:', err);
    }
  }

  undo() {
    this._syncHandles();
    if (!this.paintContext || this.history.length <= 1) {
      return false;
    }
    try {
      // Remove latest snapshot and restore previous one
      this.history.pop();
      const previous = this.history[this.history.length - 1];
      if (!previous) {
        return false;
      }
      this.paintContext.putImageData(previous, 0, 0);
      this.sceneManager.updatePaintTexture();
      return true;
    } catch (err) {
      console.warn('LegacyPainter.undo failed:', err);
      return false;
    }
  }

  resetToBaseColors() {
    this.applySplitColor(this.currentBase.left, this.currentBase.right, { pushHistory: false });
    this.history = [];
    this.saveState();
  }

  applySplitColor(leftColor, rightColor, { pushHistory = true } = {}) {
    this._syncHandles();
    if (!this.paintContext || !this.paintTexture) {
      return;
    }
    const size = this.paintTexture.getSize();
    if (pushHistory) {
      this.saveState();
    }
    try {
      this.paintContext.clearRect(0, 0, size.width, size.height);
      const mid = size.width / 2;
      this.paintContext.fillStyle = leftColor;
      this.paintContext.fillRect(0, 0, mid, size.height);
      this.paintContext.fillStyle = rightColor;
      this.paintContext.fillRect(mid, 0, size.width - mid, size.height);
      this.sceneManager.updatePaintTexture();
      this.currentBase = { left: leftColor, right: rightColor };
    } catch (err) {
      console.warn('LegacyPainter.applySplitColor failed:', err);
    }
  }

  applySolidColor(color, opts = {}) {
    this.applySplitColor(color, color, opts);
  }

  async stampDataUrl(dataURL, uv, options = {}) {
    if (!dataURL || !uv) {
      return false;
    }
    this._syncHandles();
    if (!this.paintContext || !this.paintTexture) {
      return false;
    }

    try {
      const size = this.paintTexture.getSize();
      const bounds = this.sceneManager.getGarmentBounds();
      const widthWorld = options.widthWorld ?? 0.8;
      const heightWorld = options.heightWorld ?? 0.8;
      const rotationDeg = options.rotationDeg ?? 0;

      const spanX = Math.max(bounds?.width || 1, 0.0001);
      const spanY = Math.max(bounds?.height || 1, 0.0001);

      const pixelWidth = Math.max(8, size.width * (widthWorld / spanX));
      const pixelHeight = Math.max(8, size.height * (heightWorld / spanY));

      const centerX = this._clamp01(uv.u) * size.width;
      const centerY = this._clamp01(uv.v) * size.height;
      const drawX = centerX - pixelWidth / 2;
      const drawY = centerY - pixelHeight / 2;

      const image = await this._loadImage(dataURL);
      if (!image) {
        return false;
      }

      this.saveState();

      if (rotationDeg && rotationDeg % 360 !== 0) {
        const rad = rotationDeg * Math.PI / 180;
  const tempCanvas = document.createElement('canvas');
  tempCanvas.width = Math.ceil(pixelWidth);
  tempCanvas.height = Math.ceil(pixelHeight);
  const tempCtx = tempCanvas.getContext('2d', { willReadFrequently: true });
        if (tempCtx) {
          tempCtx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
          tempCtx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
          tempCtx.rotate(rad);
          tempCtx.drawImage(image, -pixelWidth / 2, -pixelHeight / 2, pixelWidth, pixelHeight);
          tempCtx.setTransform(1, 0, 0, 1, 0, 0);
          this.paintContext.drawImage(tempCanvas, drawX, drawY);
        }
      } else {
        this.paintContext.drawImage(image, drawX, drawY, pixelWidth, pixelHeight);
      }

      this.sceneManager.updatePaintTexture();
      return true;
    } catch (err) {
      console.warn('LegacyPainter.stampDataUrl failed:', err);
      return false;
    }
  }

  applyDynamicTexture(texture, { pushHistory = true } = {}) {
    if (!texture) {
      return;
    }
    this._syncHandles();
    if (!this.paintContext || !this.paintTexture) {
      return;
    }
    const ctx = texture.getContext ? texture.getContext() : null;
    const sourceCanvas = ctx?.canvas || texture._canvas || null;
    if (!sourceCanvas) {
      console.warn('LegacyPainter.applyDynamicTexture: no source canvas available');
      return;
    }
    const size = this.paintTexture.getSize();
    if (pushHistory) {
      this.saveState();
    }
    try {
      this.paintContext.clearRect(0, 0, size.width, size.height);
      this.paintContext.drawImage(sourceCanvas, 0, 0, size.width, size.height);
      this.sceneManager.updatePaintTexture();
    } catch (err) {
      console.warn('LegacyPainter.applyDynamicTexture failed:', err);
    }
  }

  _clamp01(value) {
    if (!Number.isFinite(value)) {
      return 0;
    }
    return Math.min(1, Math.max(0, value));
  }

  _loadImage(dataURL) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Failed to load image data'));
      img.src = dataURL;
    }).catch((err) => {
      console.warn('LegacyPainter._loadImage failed:', err);
      return null;
    });
  }
}
