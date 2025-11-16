// ApiService: handles server communication for saving designs

export class ApiService {
  constructor({ sceneManager }) {
    this.sceneManager = sceneManager;
  }

  async saveDesign({ width = 1920, height = 1080, designData = null } = {}) {
    const engine = this.sceneManager.engine;
    const camera = this.sceneManager.camera;
    const scene = this.sceneManager.scene;

    // Pause floating animation if present
    const prevObserver = this.sceneManager.beforeRenderObserver;
    if (prevObserver) {
      scene.onBeforeRenderObservable.remove(prevObserver);
      this.sceneManager.beforeRenderObserver = null;
    }

    try {
      let dataURL;
      if (BABYLON.Tools.CreateScreenshotUsingRenderTargetAsync) {
        dataURL = await BABYLON.Tools.CreateScreenshotUsingRenderTargetAsync(engine, camera, { width, height });
      } else {
        dataURL = await new Promise((resolve, reject) => {
          try {
            BABYLON.Tools.CreateScreenshotUsingRenderTarget(
              engine,
              camera,
              { width, height },
              (data) => resolve(data)
            );
          } catch (e) {
            reject(e);
          }
        });
      }

      const body = { textureMap: dataURL };
      if (designData) body.design = designData;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch('save_design.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(body)
      });
      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        const txt = await res.text();
        throw new Error(`Unexpected response (${res.status}): ${txt.slice(0,200)}`);
      }
      const json = await res.json();
      return json;
    } finally {
      // Restore animation safely
      if (prevObserver) {
        this.sceneManager.attachFloatingAnimation(0, 0.05);
      }
    }
  }

  async saveDesignMulti({ width = 1920, height = 1080, designData = null, angles = null } = {}) {
    const engine = this.sceneManager.engine;
    const camera = this.sceneManager.camera;
    const scene = this.sceneManager.scene;

    // Default angles (alpha) for front/back/left/right based on ArcRotateCamera
    const views = angles || [
      { key: 'front', alpha: -Math.PI / 2 },
      { key: 'right', alpha: 0 },
      { key: 'back', alpha: Math.PI / 2 },
      { key: 'left', alpha: Math.PI }
    ];

    // Pause floating animation for crisp images
    const prevObserver = this.sceneManager.beforeRenderObserver;
    if (prevObserver) {
      scene.onBeforeRenderObservable.remove(prevObserver);
      this.sceneManager.beforeRenderObserver = null;
    }

    const originalAlpha = camera.alpha;
    const images = {};

    try {
      for (const v of views) {
        camera.alpha = v.alpha;
        await scene.whenReadyAsync();
        const dataURL = await (BABYLON.Tools.CreateScreenshotUsingRenderTargetAsync
          ? BABYLON.Tools.CreateScreenshotUsingRenderTargetAsync(engine, camera, { width, height })
          : new Promise((resolve, reject) => {
              try {
                BABYLON.Tools.CreateScreenshotUsingRenderTarget(engine, camera, { width, height }, (d) => resolve(d));
              } catch (e) { reject(e); }
            }));
        images[v.key] = dataURL;
      }

      const body = { images };
      if (designData) body.design = designData;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const res = await fetch('save_design.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(body)
      });
      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        const txt = await res.text();
        throw new Error(`Unexpected response (${res.status}): ${txt.slice(0,200)}`);
      }
      return await res.json();
    } finally {
      camera.alpha = originalAlpha;
      if (prevObserver) {
        this.sceneManager.attachFloatingAnimation(0, 0.05);
      }
    }
  }
}
