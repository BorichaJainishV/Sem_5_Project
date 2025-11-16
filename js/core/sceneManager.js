// SceneManager: encapsulates Babylon.js setup and interactions
// Why better: isolates engine/scene lifecycle, eases testing, and avoids global state.

export class SceneManager {
  constructor({ canvas }) {
    this.canvas = canvas;
    
    // Create engine with optimized settings
    this.engine = new BABYLON.Engine(canvas, true, {
      preserveDrawingBuffer: true,
      stencil: true,
      depth: true,
      antialias: true,
      powerPreference: 'high-performance',
      failIfMajorPerformanceCaveat: false,
    });

  this.scene = new BABYLON.Scene(this.engine);
    this.scene.useRightHandedSystem = false;
    this.scene.cleanupPreviouslyActiveMeshes = true;
    this.scene.autoClear = true;
    this.scene.autoClearDepthAndStencil = true;
    this.scene.skipPointerMovePicking = true;

  this.shadowGenerator = null;

    try {
      // Key directional light to mimic a studio setup
      const keyLight = new BABYLON.DirectionalLight('keyLight', new BABYLON.Vector3(-0.5, -0.8, -0.5), this.scene);
      keyLight.position = new BABYLON.Vector3(5, 10, 5);
      keyLight.intensity = 3.5;
      keyLight.shadowEnabled = true;
      keyLight.shadowMinZ = 1;
      keyLight.shadowMaxZ = 30;

      const shadowGenerator = new BABYLON.ShadowGenerator(2048, keyLight);
      shadowGenerator.useBlurExponentialShadowMap = true;
      shadowGenerator.blurKernel = 32;
      shadowGenerator.frustumEdgeFalloff = 0.4;
      shadowGenerator.useKernelBlur = true;
      this.shadowGenerator = shadowGenerator;

      // Fill light to prevent harsh shadows
      const fillLight = new BABYLON.DirectionalLight('fillLight', new BABYLON.Vector3(0.5, -0.5, -0.5), this.scene);
      fillLight.position = new BABYLON.Vector3(-5, 3, 5);
      fillLight.intensity = 0.6;

      // Rim light to highlight the silhouette
      const rimLight = new BABYLON.DirectionalLight('rimLight', new BABYLON.Vector3(0.5, -0.5, 0.5), this.scene);
      rimLight.position = new BABYLON.Vector3(-10, 5, -10);
      rimLight.intensity = 1.2;
      rimLight.diffuse = new BABYLON.Color3(1, 0.8, 0.6);
      rimLight.specular = new BABYLON.Color3(1, 0.9, 0.7);

      // Fill light from opposite side
      const fillLight2 = new BABYLON.DirectionalLight('fillLight2', new BABYLON.Vector3(0.5, -0.5, 0.5), this.scene);
      fillLight2.position = new BABYLON.Vector3(-5, 3, -5);
      fillLight2.intensity = 0.5;

      // Ambient light for overall brightness
      const ambientLight = new BABYLON.HemisphericLight('ambientLight', new BABYLON.Vector3(0, 1, 0), this.scene);
      ambientLight.intensity = 0.4;
      ambientLight.groundColor = new BABYLON.Color3(0.2, 0.2, 0.2);

      // Optional: Add IBL for reflections
      const hdr = BABYLON.CubeTexture.CreateFromPrefilteredData(
        'https://assets.babylonjs.com/environments/environmentSpecular.env',
        this.scene
      );
      this.scene.environmentTexture = hdr;
      this.scene.environmentIntensity = 0.5;
    } catch (e) {
      console.error('Failed to setup enhanced lighting:', e);
      const fallback = new BABYLON.HemisphericLight('light', new BABYLON.Vector3(0, 1, 0), this.scene);
      fallback.intensity = 1.2;
      this.shadowGenerator = null;
    }

    // Camera setup with proper frame handling
    this.camera = new BABYLON.ArcRotateCamera(
      'camera',
      -Math.PI / 2,
      Math.PI / 2.5,
      7,
      new BABYLON.Vector3(0, 0, 0),
      this.scene
    );

    // Camera control settings
    this.camera.attachControl(canvas, true);
    this.camera.wheelPrecision = 50;
    this.camera.lowerRadiusLimit = 3;
    this.camera.upperRadiusLimit = 15;

    // Enable proper camera frame handling
    this.camera.minZ = 0.1;
    this.camera.maxZ = 100;
    this.camera.mode = BABYLON.Camera.PERSPECTIVE_CAMERA;

    // Set viewport and ensure proper clearing
    this.camera.viewport = new BABYLON.Viewport(0, 0, 1.0, 1.0);
    this.camera.layerMask = 0x0fffffff;

    this.engine.runRenderLoop(() => {
      if (this.scene && !this.scene.isDisposed) {
        this.scene.render();
      }
    });

    this._resizeHandler = () => {
      if (this.engine) {
        this.engine.resize();
      }
    };
    window.addEventListener('resize', this._resizeHandler);

    this.scene.onDisposeObservable.add(() => {
      if (this._resizeHandler) {
        window.removeEventListener('resize', this._resizeHandler);
        this._resizeHandler = null;
      }
    });

    // Create a shared PBR material suitable for garments
    this.sharedMaterial = new BABYLON.PBRMaterial('sharedMaterial', this.scene);
    this.sharedMaterial.albedoColor = new BABYLON.Color3(1, 1, 1);
    this.sharedMaterial.metallic = 0;
    this.sharedMaterial.roughness = 1;
    this.sharedMaterial.backFaceCulling = false;
    this.sharedMaterial.twoSidedLighting = true;
    this.sharedMaterial.useRadianceOverAlpha = true;
    this.sharedMaterial.transparencyMode = BABYLON.PBRMaterial.PBRMATERIAL_OPAQUE;

    // Shared paint layer used for all apparel (matches legacy dynamic texture workflow)
    const paintSize = { width: 2048, height: 2048 };
    this.paintTexture = new BABYLON.DynamicTexture('paintTexture', paintSize, this.scene, false, BABYLON.Texture.TRILINEAR_SAMPLINGMODE);
    this.paintTexture.hasAlpha = true;
    this.paintTexture.wrapU = BABYLON.Texture.CLAMP_ADDRESSMODE;
    this.paintTexture.wrapV = BABYLON.Texture.CLAMP_ADDRESSMODE;
    this.paintTexture.anisotropicFilteringLevel = 4;

    const baseCtx = this.paintTexture.getContext();
    const paintCanvas = baseCtx?.canvas || this.paintTexture._canvas || null;
    if (paintCanvas && typeof paintCanvas.getContext === 'function') {
      this.paintContext = paintCanvas.getContext('2d', { willReadFrequently: true });
      if (this.paintContext) {
        this.paintTexture._context = this.paintContext;
      }
    }
    if (!this.paintContext) {
      this.paintContext = baseCtx;
    }
    this.clearPaintCanvas('#cccccc');

    this.sharedMaterial.albedoTexture = this.paintTexture;

    this.currentRoot = null;
    this.beforeRenderObserver = null;
    this.garmentMeshes = [];
  }

  setAlbedoTexture(texture) {
    if (!texture) {
      return;
    }

    // If a separate texture is provided, copy its pixels into the shared paint canvas
    const ctx = typeof texture.getContext === 'function' ? texture.getContext() : null;
    const sourceCanvas = ctx?.canvas || texture._canvas || null;

    if (sourceCanvas && this.paintContext && this.paintTexture) {
      try {
        const size = this.paintTexture.getSize();
        this.paintContext.clearRect(0, 0, size.width, size.height);
        this.paintContext.drawImage(sourceCanvas, 0, 0, size.width, size.height);
        this.updatePaintTexture();
      } catch (err) {
        console.warn('SceneManager.setAlbedoTexture copy failed:', err);
        this.sharedMaterial.albedoTexture = texture;
      }
    } else {
      this.sharedMaterial.albedoTexture = texture;
    }

    // Ensure current meshes reference the shared paint texture
    this.sharedMaterial.albedoTexture = this.paintTexture;
    if (this.garmentMeshes && this.garmentMeshes.length) {
      for (const mesh of this.garmentMeshes) {
        if (mesh && mesh.material) {
          mesh.material.albedoTexture = this.paintTexture;
        }
      }
    }
  }

  async loadGLB(modelFile) {
    try {
      // Dispose previous model and clean up
      if (this.currentRoot || (this.garmentMeshes && this.garmentMeshes.length)) {
        if (this.garmentMeshes) {
          for (const mesh of this.garmentMeshes) {
            if (mesh) {
              mesh.dispose(true, true);
            }
          }
          this.garmentMeshes = [];
        }

        if (this.currentRoot) {
          this.currentRoot.dispose(true, true);
          this.currentRoot = null;
        }
      }

      const sanitizedFile = modelFile.startsWith('/') ? modelFile.slice(1) : modelFile;
      console.log('Attempting to load model from:', sanitizedFile);

      BABYLON.SceneLoader.OnPluginActivatedObservable.addOnce((loader) => {
        if (loader.name === 'gltf') {
          const gltfLoader = loader;
          gltfLoader.animationStartMode = BABYLON.GLTFLoaderAnimationStartMode.NONE;
          gltfLoader.compileMaterials = false;
          gltfLoader.compileShadowGenerators = false;
          gltfLoader.useClipPlane = false;

          gltfLoader.onErrorObservable.add((error) => {
            console.error('GLTFLoader error:', error);
          });
        }
      });

      const rootUrl = window.location.origin + window.location.pathname.replace(/[^/]+$/, '');
      const modelDirectory = rootUrl + 'models/';
      console.log('Loading model from:', modelDirectory + sanitizedFile);

      const result = await BABYLON.SceneLoader.ImportMeshAsync(
        '',
        modelDirectory,
        sanitizedFile,
        this.scene,
        (event) => {
          if (event.lengthComputable) {
            const progress = ((event.loaded * 100) / event.total).toFixed();
            console.log(`Loading progress: ${progress}%`);
          }
        }
      );

      if (!result || !result.meshes || result.meshes.length === 0) {
        throw new Error('Model loaded but no meshes found');
      }

      console.log('GLB loaded, meshes:', result.meshes.map((m) => ({
        name: m.name,
        id: m.id,
        hasParent: !!m.parent,
        isVisible: m.isVisible,
        isEnabled: m.isEnabled(),
      })));

      let root = null;
      if (result.transformNodes && result.transformNodes.length > 0) {
        root =
          result.transformNodes.find((n) => n.name === '__root__') ||
          result.transformNodes.find((n) => !n.parent) ||
          result.transformNodes[0];
      }

      if (!root && result.meshes && result.meshes.length > 0) {
        root = result.meshes.find((m) => !m.parent) || result.meshes[0];
      }

      if (!root) {
        throw new Error('Could not determine root node of loaded model');
      }

      root.position = new BABYLON.Vector3(0, 0, 0);
      root.setEnabled(true);
      this.currentRoot = root;

      this.garmentMeshes = [];
      for (const mesh of result.meshes) {
        if (!(mesh instanceof BABYLON.Mesh)) {
          continue;
        }

        console.log('Setting up mesh:', mesh.name, {
          position: mesh.position.asArray(),
          visibility: mesh.visibility,
          isEnabled: mesh.isEnabled(),
          material: mesh.material ? mesh.material.name : 'none',
        });

        if (mesh.name.includes('helper') || mesh.name.includes('debug')) {
          continue;
        }

        mesh.isVisible = true;
        mesh.setEnabled(true);
        mesh.visibility = 1;
        mesh.receiveShadows = true;
        mesh.useVertexColors = false;
        mesh.isPickable = true;

        const mat = this.sharedMaterial.clone(`mat_${mesh.name}`);
        mat.albedoTexture = this.paintTexture;
        mesh.material = mat;

        if (mesh.subMeshes && mesh.subMeshes[0] && !mat.isReadyForSubMesh(mesh, mesh.subMeshes[0])) {
          await new Promise((resolve) => setTimeout(resolve, 50));
        }

        if (this.shadowGenerator) {
          this.shadowGenerator.addShadowCaster(mesh, true);
        }

        this.garmentMeshes.push(mesh);

        console.log('Mesh setup complete:', {
          name: mesh.name,
          enabled: mesh.isEnabled(),
          visible: mesh.isVisible,
          pickable: mesh.isPickable,
          material: mesh.material ? mesh.material.name : 'none',
        });
      }

      console.log('Model loaded successfully with meshes:', this.garmentMeshes.map((m) => m.name));

      this.fitToView(root, 0.8);
      this.scene.render();

      return root;
    } catch (error) {
      console.error('Error loading model:', error);
      throw error;
    }
  }

  fitToView(node, zoomFactor = 1.0) {
    if (!node) return;
    const meshes = this.scene.meshes.filter(m => m && m.isEnabled() && (m === node || (m.isDescendantOf && m.isDescendantOf(node))));
    if (!meshes.length) return;
    let min = new BABYLON.Vector3(Number.POSITIVE_INFINITY, Number.POSITIVE_INFINITY, Number.POSITIVE_INFINITY);
    let max = new BABYLON.Vector3(Number.NEGATIVE_INFINITY, Number.NEGATIVE_INFINITY, Number.NEGATIVE_INFINITY);
    meshes.forEach(mesh => {
      const bi = mesh.getBoundingInfo();
      min = BABYLON.Vector3.Minimize(min, bi.boundingBox.minimumWorld);
      max = BABYLON.Vector3.Maximize(max, bi.boundingBox.maximumWorld);
    });
    const center = min.add(max).scale(0.5);
    const size = max.subtract(min);
    const boundingRadius = size.length() * 0.5;
    this.camera.setTarget(center);
    const fov = this.camera.fov || (Math.PI / 4);
    let radius = boundingRadius / Math.sin(fov / 2);
    radius = Math.max(radius * zoomFactor, 0.1);
    this.camera.radius = radius;
  }

  attachFloatingAnimation(offsetY = 0, amplitude = 0.1) {
    if (this.beforeRenderObserver) {
      this.scene.onBeforeRenderObservable.remove(this.beforeRenderObserver);
      this.beforeRenderObserver = null;
    }
    if (!this.currentRoot) return;
    const start = Date.now();
    this.beforeRenderObserver = this.scene.onBeforeRenderObservable.add(() => {
      if (this.currentRoot) {
        const t = (Date.now() - start) * 0.001;
        this.currentRoot.position.y = offsetY + Math.sin(t) * amplitude;
      }
    });
  }

  setBackgroundColor(r, g, b) {
    // Values should be between 0 and 1
    r = Math.min(Math.max(r, 0), 1);
    g = Math.min(Math.max(g, 0), 1);
    b = Math.min(Math.max(b, 0), 1);
    this.scene.clearColor = new BABYLON.Color4(r, g, b, 1);
    this.scene.render();
  }

  dispose() {
    if (this.beforeRenderObserver) {
      this.scene.onBeforeRenderObservable.remove(this.beforeRenderObserver);
      this.beforeRenderObserver = null;
    }
    this.scene.dispose();
    this.engine.dispose();
  }

  getPaintTexture() {
    return this.paintTexture;
  }

  getPaintContext() {
    return this.paintContext;
  }

  updatePaintTexture() {
    if (this.paintTexture) {
      this.paintTexture.update(false);
    }
  }

  clearPaintCanvas(color = '#cccccc') {
    if (!this.paintContext || !this.paintTexture) {
      return;
    }
    try {
      const size = this.paintTexture.getSize();
      this.paintContext.fillStyle = color;
      this.paintContext.fillRect(0, 0, size.width, size.height);
      this.updatePaintTexture();
    } catch (err) {
      console.warn('SceneManager.clearPaintCanvas failed:', err);
    }
  }

  getGarmentBounds() {
    const mesh = this.garmentMeshes?.find((m) => m && !m.metadata?.isDecal);
    if (!mesh || !mesh.getBoundingInfo) {
      return null;
    }
    const bounds = mesh.getBoundingInfo().boundingBox;
    return {
      width: bounds.maximumWorld.x - bounds.minimumWorld.x,
      height: bounds.maximumWorld.y - bounds.minimumWorld.y,
      depth: bounds.maximumWorld.z - bounds.minimumWorld.z,
    };
  }
}

