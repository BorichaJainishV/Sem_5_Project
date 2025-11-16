// UIController: wires DOM controls to SceneManager actions and Babylon gizmos/highlights

import { LegacyPainter } from './legacyPainter.js?v=20251011';

export class UIController {
  constructor({ sceneManager, logoManager, textManager }) {
    if (!sceneManager) throw new Error('sceneManager is required');
    if (!logoManager) throw new Error('logoManager is required');
    if (!textManager) throw new Error('textManager is required');
    
    console.log('UIController: Starting initialization...');
    
    this.sceneManager = sceneManager;
    this.scene = sceneManager.scene;
    this.camera = sceneManager.camera;
    this.logoManager = logoManager;
    this.textManager = textManager;
    this.legacyPainter = new LegacyPainter(this.sceneManager);
    this.useLegacyStamping = true;
    this.currentStampDataUrl = null;

    // Initialize enhanced UI bindings
    import('./initUI.js').then(module => {
      module.initializeUI(this);
    }).catch(err => {
      console.error('Failed to initialize UI:', err);
    });
    
    // Debug: log mesh loading/visibility
    this.scene.onNewMeshAddedObservable.add((mesh) => {
      console.log('New mesh added:', mesh.name, 'enabled:', mesh.isEnabled(), 'visible:', mesh.isVisible);
    });

  // Initialize core components after scene is ready
  this.highlightLayer = null; // deprecated in favor of outlines
  this.gizmos = null;
    
    // Create a promise for initialization
    this.initPromise = new Promise((resolve) => {
        const initializeComponents = () => {
            try {
        // Prefer outlines instead of highlight layer to avoid internal add() errors
        this.highlightLayer = null;

                // Create gizmo manager
                this.gizmos = new BABYLON.GizmoManager(this.scene, 2.0);
                if (this.gizmos.gizmos) {
                    this.gizmos.positionGizmoEnabled = false;
                    this.gizmos.rotationGizmoEnabled = false;
                    this.gizmos.scaleGizmoEnabled = false;
                    this.gizmos.usePointerToAttachGizmos = false;
                }

                resolve();
            } catch (err) {
                console.warn('Failed to initialize UI components:', err);
                // Create dummy objects if initialization fails
                this.highlightLayer = { addMesh: () => {}, removeMesh: () => {} };
                this.gizmos = { 
                    attachToMesh: () => {},
                    positionGizmoEnabled: false,
                    rotationGizmoEnabled: false,
                    scaleGizmoEnabled: false
                };
                resolve();
            }
        };

        if (this.scene.isReady()) {
            initializeComponents();
        } else {
            this.scene.executeWhenReady(initializeComponents);
        }
    });

    // Gizmo manager is now initialized in initPromise

    this.selectedMesh = null;
    this.mode = 'place'; // 'place' | 'move'
    this.createdElements = [];
    this.currentStampTexture = null; // BABYLON.Texture used for decals
    this.currentStampKind = null; // 'logo' | 'text'
    this.currentStampMeta = null; // additional meta (e.g., text, font, color)
    this.canvasEl = document.getElementById('renderCanvas');
  this.placeHintEl = document.getElementById('placeHint');
    // Decal parameter controls
    this.decalWidthEl = document.getElementById('decalWidth');
    this.decalHeightEl = document.getElementById('decalHeight');
    this.decalRotationEl = document.getElementById('decalRotation');
  this.forceOutwardEl = document.getElementById('forceOutward');
  this.showNormalArrowEl = document.getElementById('showNormalArrow');
  this.decalDepthWriteOffEl = document.getElementById('decalDepthWriteOff');
  this.decalZOffsetEl = document.getElementById('decalZOffset');
  const initialWidth = this._num(this.decalWidthEl?.value, 0.8);
  const initialHeight = this._num(this.decalHeightEl?.value, 0.8);
  this.decalSize = { w: initialWidth, h: initialHeight };
    this.decalAngleDeg = this._num(this.decalRotationEl?.value, 0);
  this.decalZOffset = this._num(this.decalZOffsetEl?.value, 2);
  this.forceOutward = this.forceOutwardEl ? !!this.forceOutwardEl.checked : true;
  this.depthWriteOff = this.decalDepthWriteOffEl ? !!this.decalDepthWriteOffEl.checked : true;

  // Create a small cursor indicator (sphere) to preview placement point
  this.pickCursor = BABYLON.MeshBuilder.CreateSphere('pickCursor', { diameter: 0.05 }, this.scene);
  const cursorMat = new BABYLON.StandardMaterial('pickCursorMat', this.scene);
  cursorMat.emissiveColor = new BABYLON.Color3(1.0, 0.83, 0.3);
  cursorMat.disableLighting = true;
  this.pickCursor.material = cursorMat;
  this.pickCursor.isPickable = false;
  this.pickCursor.renderingGroupId = 2; // draw on top of most geometry

  // Debug: Add a visible sphere at camera target for reference
  this.debugSphere = BABYLON.MeshBuilder.CreateSphere('debugTarget', { diameter: 0.1 }, this.scene);
  const debugMat = new BABYLON.StandardMaterial('debugTargetMat', this.scene);
  debugMat.emissiveColor = new BABYLON.Color3(1, 0, 0);
  debugMat.disableLighting = true;
  this.debugSphere.material = debugMat;
  this.debugSphere.isPickable = false;
  this.debugSphere.renderingGroupId = 2;
  this.pickCursor.setEnabled(false);

  // Normal arrow for debugging orientation
  this.normalArrow = BABYLON.MeshBuilder.CreateCylinder('normalArrow', { diameter: 0.01, height: 0.3 }, this.scene);
  const arrowMat = new BABYLON.StandardMaterial('normalArrowMat', this.scene);
  arrowMat.emissiveColor = new BABYLON.Color3(0.2, 0.8, 1);
  arrowMat.disableLighting = true;
  this.normalArrow.material = arrowMat;
  this.normalArrow.isPickable = false;
  this.normalArrow.renderingGroupId = 2;
  this.normalArrow.setEnabled(false);

    this._bindPicking();
    this._bindControls();
    this._bindDesignTools();
  }

  _num(v, fallback) {
    const n = parseFloat(v);
    return isFinite(n) ? n : fallback;
  }

  _getPrimaryGarmentMesh() {
    return this.sceneManager.garmentMeshes?.find((mesh) => mesh && !mesh.metadata?.isDecal) || this.sceneManager.currentRoot;
  }

  _clampToInput(value, inputEl) {
    if (!inputEl) {
      return value;
    }
    const min = inputEl.min !== undefined && inputEl.min !== '' ? parseFloat(inputEl.min) : -Infinity;
    const max = inputEl.max !== undefined && inputEl.max !== '' ? parseFloat(inputEl.max) : Infinity;
    return Math.min(isFinite(max) ? max : value, Math.max(isFinite(min) ? min : value, value));
  }

  _computeDefaultDecalSize(aspect = 1) {
    const garment = this._getPrimaryGarmentMesh();
    if (!garment || !garment.getBoundingInfo) {
      return { width: this.decalSize.w, height: this.decalSize.h };
    }

    const bounds = garment.getBoundingInfo().boundingBox;
    const widthWorld = bounds.maximumWorld.x - bounds.minimumWorld.x;
    const heightWorld = bounds.maximumWorld.y - bounds.minimumWorld.y;

    const maxWidth = widthWorld * 0.55; // cover most of chest while leaving margins
    const maxHeight = heightWorld * 0.65; // keep some collar and hem visible

    if (!isFinite(maxWidth) || !isFinite(maxHeight) || maxWidth <= 0 || maxHeight <= 0) {
      return { width: this.decalSize.w, height: this.decalSize.h };
    }

    let width = maxWidth;
    let height = width / aspect;

    if (height > maxHeight) {
      height = maxHeight;
      width = height * aspect;
    }

    const minSize = 0.2;
    width = Math.max(minSize, Math.min(width, widthWorld));
    height = Math.max(minSize, Math.min(height, heightWorld));

    return { width, height };
  }

  _applyDecalSize(width, height) {
    if (!isFinite(width) || !isFinite(height)) {
      return;
    }

    const clampedWidth = this._clampToInput(width, this.decalWidthEl);
    const clampedHeight = this._clampToInput(height, this.decalHeightEl);

    this.decalSize.w = clampedWidth;
    this.decalSize.h = clampedHeight;

    if (this.decalWidthEl) {
      this.decalWidthEl.value = clampedWidth.toFixed(2);
    }

    if (this.decalHeightEl) {
      this.decalHeightEl.value = clampedHeight.toFixed(2);
    }
  }

  _updateDecalSizeFromTexture(texture) {
    if (!texture || typeof texture.getSize !== 'function') {
      return;
    }

    const size = texture.getSize();
    const aspect = size?.width && size?.height ? size.width / size.height : 1;
    const { width, height } = this._computeDefaultDecalSize(aspect || 1);
    this._applyDecalSize(width, height);

    if (this.currentStampMeta) {
      this.currentStampMeta.aspect = aspect || 1;
      this.currentStampMeta.textureWidth = size?.width || 0;
      this.currentStampMeta.textureHeight = size?.height || 0;
    }
  }

  _switchToPlaceMode() {
    this.mode = 'place';
    const placeBtn = document.getElementById('placeModeBtn');
    const moveBtn = document.getElementById('moveModeBtn');
    if (placeBtn) placeBtn.classList.add('active');
    if (moveBtn) moveBtn.classList.remove('active');
    this._updatePlaceHint();
  }

  _updatePlaceHint() {
    if (this.placeHintEl) {
      this.placeHintEl.style.display = this.mode === 'place' && this.currentStampTexture ? 'block' : 'none';
    }
  }

  _bindPicking() {
    this.scene.onPointerObservable.add(async (pointerInfo) => {
      // Update live cursor on move when in Place mode and stamp is armed
      if (pointerInfo.type === BABYLON.PointerEventTypes.POINTERMOVE) {
        const armed = !!this.currentStampTexture && this.mode === 'place';
        const pick = pointerInfo.pickInfo;
        if (armed && pick?.hit && pick.pickedMesh && pick.pickedPoint) {
          let n = null;
          try { n = pick.getNormal && pick.getNormal(true); } catch(e) { n = null; }
          if (!n) n = new BABYLON.Vector3(0,0,1);
          const rayDir = pick.ray?.direction || this.camera.getForwardRay().direction;
          // Optionally force outward
          if (!this.forceOutward || BABYLON.Vector3.Dot(n, rayDir) > 0) { n = n.negate(); }
          const previewPos = pick.pickedPoint.add(n.scale(0.02));
          this.pickCursor.position.copyFrom(previewPos);
          this.pickCursor.setEnabled(true);
          // Normal arrow visualize
          if (this.showNormalArrowEl && this.showNormalArrowEl.checked) {
            this.normalArrow.setEnabled(true);
            this.normalArrow.position.copyFrom(previewPos.add(n.scale(0.15)));
            this.normalArrow.setDirection(n);
          } else {
            this.normalArrow.setEnabled(false);
          }
        } else {
          this.pickCursor.setEnabled(false);
          this.normalArrow.setEnabled(false);
        }
      }
      if (pointerInfo.type === BABYLON.PointerEventTypes.POINTERPICK) {
        const pick = pointerInfo.pickInfo;
        if (!pick?.hit || !pick.pickedMesh) return;

        // Ignore skybox/environment
        if (pick.pickedMesh.name && pick.pickedMesh.name.includes('hdrSkyBox')) return;

        if (this.mode === 'place') {
          // Create a decal if we have a stamp texture
          if (!this.currentStampTexture) {
            // No stamp selected; fall back to selection
            await this.selectMesh(pick.pickedMesh);
            return;
          }
          const outcome = await this._applyStampAtPick(pick);
          if (outcome?.mesh) {
            this.createdElements.push(outcome.mesh);
            await this.selectMesh(outcome.mesh);
          } else if (outcome?.painted) {
            await this.selectMesh(null);
          }
          // Hide cursor after placement
          this.pickCursor.setEnabled(false);
        } else {
          // Move/Select mode: if a decal is selected, reposition it to the new pick point
          const selected = this.selectedMesh;
          const isSelectedDecal = selected && selected.metadata && selected.metadata.isDecal;
          const targetIsGarment = pick.pickedMesh && (!pick.pickedMesh.metadata || !pick.pickedMesh.metadata.isDecal);
          if (isSelectedDecal && targetIsGarment && pick.pickedPoint) {
            const old = selected;
            const oldMeta = old.metadata || {};
            const oldMat = old.material;
            const oldTex = oldMat && oldMat.diffuseTexture ? oldMat.diffuseTexture : this.currentStampTexture;
            const newPick = pick;
            const newDecal = this._createDecalAtPick(newPick, oldTex);
            if (newDecal) {
              // carry over metadata
              newDecal.metadata = { ...oldMeta };
              // match scale if user changed it
              newDecal.scaling.copyFrom(old.scaling);
              // replace in createdElements
              const idx = this.createdElements.indexOf(old);
              if (idx >= 0) this.createdElements[idx] = newDecal;
              this.selectMesh(newDecal);
              old.dispose();
            }
          } else {
            // otherwise, just select clicked mesh (decal or garment)
            await this.selectMesh(pick.pickedMesh);
          }
        }
      }
    });
  }

  async selectMesh(mesh) {
    if (this.selectedMesh === mesh) return;

    try {
        // Wait for UI components to be initialized
        await this.initPromise;

    // Remove outline/highlight from previously selected mesh
    if (this.selectedMesh) {
      try {
        this.selectedMesh.renderOutline = false;
      } catch {}
    }

        // Update selected mesh
        this.selectedMesh = mesh;

        if (mesh) {
      // Add outline to newly selected mesh
      try {
        mesh.outlineColor = BABYLON.Color3.FromHexString('#00BCD4');
        mesh.outlineWidth = 0.02;
        mesh.renderOutline = true;
      } catch {}

            // Safely update gizmo attachment
      if (this.gizmos && mesh && mesh instanceof BABYLON.Mesh) {
                try {
                    // First detach from current mesh if any
                    this.gizmos.attachToMesh(null);
                    
                    // Then attach to new mesh
                    this.gizmos.attachToMesh(mesh);
                    
                    // Update gizmo behaviors after attachment
                    if (this.gizmos.gizmos) {
                        ['position', 'rotation', 'scale'].forEach(type => {
                            if (this.gizmos.gizmos[type]) {
                                this.gizmos.gizmos[type].updateGizmoRotationToMatchAttachedMesh = false;
                            }
                        });
                    }
                } catch (err) {
                    console.warn('Failed to update gizmo attachment:', err);
                }
            }
    } else {
            // If no mesh provided, just clear gizmo attachment
            if (this.gizmos) {
                try {
                    this.gizmos.attachToMesh(null);
                } catch (err) {
                    console.warn('Failed to clear gizmo attachment:', err);
                }
            }
        }
    } catch (err) {
        console.error('Error in selectMesh:', err);
    }
  }

  _bindControls() {
    // Gizmo mode buttons
  const translateBtn = document.getElementById('gizmoTranslateBtn');
  const rotateBtn = document.getElementById('gizmoRotateBtn');
  const scaleBtn = document.getElementById('gizmoScaleBtn');

    if (translateBtn) translateBtn.addEventListener('click', () => {
      this.gizmos.positionGizmoEnabled = true;
      this.gizmos.rotationGizmoEnabled = false;
      this.gizmos.scaleGizmoEnabled = false;
    });
    if (rotateBtn) rotateBtn.addEventListener('click', () => {
      this.gizmos.positionGizmoEnabled = false;
      this.gizmos.rotationGizmoEnabled = true;
      this.gizmos.scaleGizmoEnabled = false;
    });
    if (scaleBtn) scaleBtn.addEventListener('click', () => {
      this.gizmos.positionGizmoEnabled = false;
      this.gizmos.rotationGizmoEnabled = false;
      this.gizmos.scaleGizmoEnabled = true;
    });

    // Element scale range
    const scaleInput = document.getElementById('elementScale');
    if (scaleInput) {
      scaleInput.addEventListener('input', () => {
        if (this.selectedMesh) {
          const s = parseFloat(scaleInput.value || '1');
          this.selectedMesh.scaling.setAll(s);
        }
      });
    }

    // Decal size and rotation controls with logging
    if (this.decalWidthEl) {
      console.log('Binding decal width control');
      this.decalWidthEl.addEventListener('input', (e) => {
        console.log('Decal width changed:', e.target.value);
        const newWidth = this._num(e.target.value, this.decalSize.w);
        const aspect = this.currentStampMeta?.aspect || (this.decalSize.w / Math.max(this.decalSize.h, 0.0001));
        this.decalSize.w = this._clampToInput(newWidth, this.decalWidthEl);
        if (aspect && isFinite(aspect) && this.decalHeightEl) {
          const derivedHeight = Math.max(0.1, this.decalSize.w / aspect);
          const clampedHeight = this._clampToInput(derivedHeight, this.decalHeightEl);
          this.decalSize.h = clampedHeight;
          this.decalHeightEl.value = clampedHeight.toFixed(2);
        }
      });
    } else {
      console.warn('Decal width control not found');
    }

    if (this.decalHeightEl) {
      console.log('Binding decal height control');
      this.decalHeightEl.addEventListener('input', (e) => {
        console.log('Decal height changed:', e.target.value);
        const newHeight = this._num(e.target.value, this.decalSize.h);
        const aspect = this.currentStampMeta?.aspect || (Math.max(this.decalSize.w, 0.0001) / this.decalSize.h);
        this.decalSize.h = this._clampToInput(newHeight, this.decalHeightEl);
        if (aspect && isFinite(aspect) && this.decalWidthEl) {
          const derivedWidth = Math.max(0.1, this.decalSize.h * aspect);
          const clampedWidth = this._clampToInput(derivedWidth, this.decalWidthEl);
          this.decalSize.w = clampedWidth;
          this.decalWidthEl.value = clampedWidth.toFixed(2);
        }
      });
    } else {
      console.warn('Decal height control not found');
    }

    if (this.decalRotationEl) {
      console.log('Binding decal rotation control');
      this.decalRotationEl.addEventListener('input', (e) => {
        console.log('Decal rotation changed:', e.target.value);
        this.decalAngleDeg = this._num(e.target.value, this.decalAngleDeg);
      });
    } else {
      console.warn('Decal rotation control not found');
    }

    if (this.decalZOffsetEl) {
      console.log('Binding decal Z-offset control');
      this.decalZOffsetEl.addEventListener('input', (e) => {
        console.log('Decal Z-offset changed:', e.target.value);
        this.decalZOffset = this._num(e.target.value, this.decalZOffset);
      });
    } else {
      console.warn('Decal Z-offset control not found');
    }

    if (this.forceOutwardEl) {
      console.log('Binding force outward control');
      this.forceOutwardEl.addEventListener('change', (e) => {
        console.log('Force outward changed:', e.target.checked);
        this.forceOutward = e.target.checked;
      });
    } else {
      console.warn('Force outward control not found');
    }

    if (this.decalDepthWriteOffEl) {
      console.log('Binding depth write control');
      this.decalDepthWriteOffEl.addEventListener('change', (e) => {
        console.log('Depth write changed:', e.target.checked);
        this.depthWriteOff = e.target.checked;
      });
    } else {
      console.warn('Depth write control not found');
    }

    // Export 4K
    const exportBtn = document.getElementById('export4KBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', async () => {
        await this.exportScreenshot(3840, 2160);
      });
    }

    // Place vs Move mode
    const placeBtn = document.getElementById('placeModeBtn');
    const moveBtn = document.getElementById('moveModeBtn');
    const setActive = (btnActive, btnInactive) => {
      if (btnActive) btnActive.classList.add('active');
      if (btnInactive) btnInactive.classList.remove('active');
    };
    if (placeBtn) placeBtn.addEventListener('click', () => {
      this.mode = 'place';
      this.gizmos.attachToMesh(null);
      setActive(placeBtn, moveBtn);
      if (this.canvasEl) this.canvasEl.classList.remove('move-mode-enabled');
      this._updatePlaceHint();
    });
    if (moveBtn) moveBtn.addEventListener('click', () => {
      this.mode = 'move';
      setActive(moveBtn, placeBtn);
      if (this.canvasEl) this.canvasEl.classList.add('move-mode-enabled');
      this._updatePlaceHint();
    });

    // Undo & Reset
    const undoBtn = document.getElementById('undoBtn');
    if (undoBtn) undoBtn.addEventListener('click', () => this.undo());
    const resetBtn = document.getElementById('resetBtn');
    if (resetBtn) resetBtn.addEventListener('click', () => this.resetDesign());
  }

  async _loadApparel(type) {
    console.log('Loading apparel type:', type);
    
    // Map types to model files
    const map = {
      tshirt: 'tshirt.glb',
      shirt: 'shirt.glb',
      hoodie: 'hoodie.glb'
    };
    
    try {
      // Validate type and get file name
      if (!map[type]) {
        console.warn(`Invalid apparel type "${type}", falling back to tshirt`);
        type = 'tshirt';
      }
      const file = map[type];
      
      console.log('Loading model file:', file);
      
      // Clear existing state
      this.resetDesign();
      this.currentStampTexture = null;
      this.selectMesh(null);
      
      // Load the model
      const root = await this.sceneManager.loadGLB(file);
      if (!root) {
        throw new Error(`Failed to load model: ${file}`);
      }
      
      console.log('Model loaded successfully, setting up view...');
      
      // Set up view and animation
      this.sceneManager.fitToView(root, 0.9);
      this.sceneManager.attachFloatingAnimation(0, 0.05);
      
      // Save preference
      try { 
        localStorage.setItem('apparel-type', type);
      } catch (e) {
        console.warn('Failed to save apparel preference:', e);
      }
      
      // Update UI buttons
      document.querySelectorAll('.apparel-btn-group button').forEach(btn => {
        btn.classList.toggle('active', btn.id.toLowerCase().includes(type));
      });
      
      return root;
    } catch (error) {
      console.error('Error loading apparel:', error);
      throw error;
    }
  }

  async exportScreenshot(width = 1920, height = 1080) {
    BABYLON.Tools.CreateScreenshotUsingRenderTarget(this.sceneManager.engine, this.sceneManager.camera, { width, height });
  }

  async _updateBaseColors() {
    console.log('Updating base colors...');
    const c1 = document.getElementById('baseColor1')?.value || '#ffffff';
    const c2 = document.getElementById('baseColor2')?.value || '#cccccc';
    this.applySplitBaseColors(c1, c2);
  }

  applySplitBaseColors(leftColor, rightColor, options = {}) {
    if (!this.legacyPainter) {
      return;
    }
    this.legacyPainter.applySplitColor(leftColor, rightColor, options);
  }

  _bindDesignTools() {
    console.log('Initializing design tools...');
    
    // Base color controls
    const baseColor1 = document.getElementById('baseColor1');
    const baseColor2 = document.getElementById('baseColor2');
    
    if (baseColor1) {
      console.log('Binding baseColor1');
      baseColor1.addEventListener('input', () => this._updateBaseColors());
    }
    
    if (baseColor2) {
      console.log('Binding baseColor2');
      baseColor2.addEventListener('input', () => this._updateBaseColors());
    }

    // Logo Upload and Processing
    const logoInput = document.getElementById('logoUpload');
    const logoUrlInput = document.getElementById('logoUrlInput');
    const logoUrlBtn = document.getElementById('logoUrlBtn');
    const logoAutoCrop = document.getElementById('logoAutoCrop');
    const logoRemoveWhite = document.getElementById('logoRemoveWhite');
    const logoThreshold = document.getElementById('logoThreshold');
    const logoProcessBtn = document.getElementById('logoProcessBtn');
    if (logoInput) {
      logoInput.addEventListener('change', async () => {
        const file = logoInput.files && logoInput.files[0];
        if (!file) return;
        if (!/^image\/(png|jpeg|jpg)$/i.test(file.type)) {
          alert('Please upload a PNG or JPG image.');
          return;
        }
        const reader = new FileReader();
        reader.onload = async () => {
          const dataURL = String(reader.result || '');
          try {
            // Create and arm the texture
            this.currentStampTexture = await this.logoManager.createLogoTexture(dataURL, {
              name: file.name || 'upload.png'
            });
            this.currentStampKind = 'logo';
            this.currentStampMeta = { fileName: file.name };
            this.currentStampDataUrl = dataURL;
            this._updateDecalSizeFromTexture(this.currentStampTexture);
            
            // Switch to place mode
            this._switchToPlaceMode();
            if (!this.useLegacyStamping) {
              this._placeAtCameraCenter();
            }
          } catch (error) {
            console.error('Failed to create logo texture:', error);
            alert('Failed to process the image. Please try another.');
          }
          const img = new Image();
          img.onload = () => {
            // Fit into canvas up to 1024 px to cap memory
            const maxDim = 1024;
            const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
            const w = Math.max(1, Math.round(img.width * scale));
            const h = Math.max(1, Math.round(img.height * scale));
            logoPreview.width = w;
            logoPreview.height = h;
            const ctx = logoPreview.getContext('2d', { willReadFrequently: true });
            ctx.clearRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            // Arm as-is from preview so user can place without processing
            try {
              const asIsURL = logoPreview.toDataURL('image/png');
              this._armLogoTexture(asIsURL, file.name || 'upload.png');
            } catch (e) { /* ignore taint errors if any */ }
          };
          img.src = dataURL;
        };
        reader.readAsDataURL(file);
      });
    }

    // Logo via URL (relative or absolute)
    if (logoUrlBtn && logoUrlInput) {
      logoUrlBtn.addEventListener('click', () => {
        const url = (logoUrlInput.value || '').trim();
        if (!url) { alert('Enter an image URL'); return; }
        try {
          // Load into preview canvas first for processing
          const img = new Image();
          img.crossOrigin = 'anonymous';
          img.onload = () => {
            const maxDim = 1024;
            const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
            const w = Math.max(1, Math.round(img.width * scale));
            const h = Math.max(1, Math.round(img.height * scale));
            if (!logoPreview) {
              // If no preview canvas, arm directly
              const tmp = document.createElement('canvas');
              tmp.width = w; tmp.height = h;
              const tctx = tmp.getContext('2d', { willReadFrequently: true });
              tctx.drawImage(img, 0, 0, w, h);
              this._armLogoTexture(tmp.toDataURL('image/png'), url);
              return;
            }
            logoPreview.width = w; logoPreview.height = h;
            const ctx = logoPreview.getContext('2d', { willReadFrequently: true });
            ctx.clearRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            // Arm as-is from preview
            try {
              const asIsURL = logoPreview.toDataURL('image/png');
              this._armLogoTexture(asIsURL, url);
            } catch (e) { /* ignore taint errors if any */ }
          };
          img.onerror = () => { alert('Could not load image from URL'); };
          img.src = url;
        } catch (e) {
          console.error('Texture creation error', e);
          alert('Could not load image from URL');
        }
      });
    }

    // Process & Use: auto-crop and remove white background
    if (logoProcessBtn && logoPreview) {
      logoProcessBtn.addEventListener('click', () => {
  const ctx = logoPreview.getContext('2d', { willReadFrequently: true });
        const w = logoPreview.width;
        const h = logoPreview.height;
        if (!w || !h) { alert('Load an image first'); return; }
        let imageData = ctx.getImageData(0, 0, w, h);
        const data = imageData.data;
        const threshold = parseInt(logoThreshold?.value || '30', 10);
        const doRemoveWhite = !!(logoRemoveWhite && logoRemoveWhite.checked);
        // 1) Optional: remove near-white background by alpha keying
        if (doRemoveWhite) {
          for (let i = 0; i < data.length; i += 4) {
            const r = data[i], g = data[i+1], b = data[i+2];
            const maxc = Math.max(r, g, b);
            const minc = Math.min(r, g, b);
            const isNearWhite = maxc > 255 - threshold && (maxc - minc) < threshold;
            if (isNearWhite) data[i+3] = 0; // make transparent
          }
        }
        // Write processed pixels back before cropping so we crop the updated alpha
        ctx.putImageData(imageData, 0, 0);
        // 2) Optional: auto-crop to non-transparent bounds
        const doCrop = !!(logoAutoCrop && logoAutoCrop.checked);
        let xMin = w, xMax = 0, yMin = h, yMax = 0;
        if (doCrop) {
          for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
              const idx = (y * w + x) * 4 + 3;
              if (data[idx] > 5) {
                if (x < xMin) xMin = x;
                if (x > xMax) xMax = x;
                if (y < yMin) yMin = y;
                if (y > yMax) yMax = y;
              }
            }
          }
          if (xMax >= xMin && yMax >= yMin) {
            const cw = xMax - xMin + 1;
            const ch = yMax - yMin + 1;
            const cropped = document.createElement('canvas');
            cropped.width = cw; cropped.height = ch;
            const cctx = cropped.getContext('2d', { willReadFrequently: true });
            cctx.putImageData(ctx.getImageData(xMin, yMin, cw, ch), 0, 0);
            // Update preview
            logoPreview.width = cw; logoPreview.height = ch;
            const pctx = logoPreview.getContext('2d', { willReadFrequently: true });
            pctx.clearRect(0, 0, cw, ch);
            pctx.drawImage(cropped, 0, 0);
          } else {
            // No opaque pixels found—keep as-is
          }
        }
        // 3) Arm as decal texture from processed preview
        const processedURL = logoPreview.toDataURL('image/png');
        this._armLogoTexture(processedURL, 'processed.png');
      });
    }

    // Text to decal binding
    const addTextBtn = document.getElementById('addTextBtn');
    if (addTextBtn) {
      addTextBtn.addEventListener('click', () => {
        const input = document.getElementById('customText');
        const colorInput = document.getElementById('textColor');
        const fontSelect = document.getElementById('fontFamily');
        const text = (input?.value || '').trim();
        if (!text) {
          alert('Enter some text first.');
          return;
        }
        const color = colorInput?.value || '#000000';
        const font = fontSelect?.value || 'Arial';
        
        // Create text texture using TextManager
        this.currentStampTexture = this.textManager.createTextTexture(text, {
          font,
          color,
          size: 512
        });
        this.currentStampDataUrl = this._textureToDataUrl(this.currentStampTexture);
        this.currentStampKind = 'text';
        this.currentStampMeta = { text, font, color };
        // Switch to Place mode automatically
        this.mode = 'place';
        const placeBtn = document.getElementById('placeModeBtn');
        const moveBtn = document.getElementById('moveModeBtn');
        if (placeBtn && moveBtn) {
          placeBtn.classList.add('active');
          moveBtn.classList.remove('active');
        }
        this._updatePlaceHint();
        // Auto-place a preview at camera center so user can see it immediately
        if (!this.useLegacyStamping) {
          this._placeAtCameraCenter();
        }
      });
    }

    // Split color pattern
    const applyPatternBtn = document.getElementById('applyColorPattern');
    if (applyPatternBtn) {
      applyPatternBtn.addEventListener('click', () => {
        const c1 = document.getElementById('baseColor1')?.value || '#cccccc';
        const c2 = document.getElementById('baseColor2')?.value || '#cccccc';
        this.applySplitBaseColors(c1, c2);
      });
    }
  }

  _armLogoTexture(dataURL, name) {
    try {
      console.log('Creating texture from:', name);
      this.currentStampDataUrl = dataURL;
      const tex = new BABYLON.Texture(
        dataURL,
        this.scene,
        true,
        true,  // invertY = true for proper orientation
        BABYLON.Texture.TRILINEAR_SAMPLINGMODE,
        null,
        null,
        null,
        true  // noMipmap = true for sharp decals
      );
      if (tex) {
        // Enhanced texture settings and loading
        tex.onLoadObservable.add(() => {
          console.log('Logo texture loaded successfully');
          tex.hasAlpha = true;
          tex.wrapU = BABYLON.Texture.CLAMP_ADDRESSMODE;
          tex.wrapV = BABYLON.Texture.CLAMP_ADDRESSMODE;
          tex.anisotropicFilteringLevel = 16;
          this.currentStampTexture = tex;
          this.currentStampKind = 'logo';
          this.currentStampMeta = { fileName: name };
          this._updateDecalSizeFromTexture(tex);
          this.mode = 'place';
          const placeBtn = document.getElementById('placeModeBtn');
          const moveBtn = document.getElementById('moveModeBtn');
          if (placeBtn && moveBtn) { placeBtn.classList.add('active'); moveBtn.classList.remove('active'); }
          this._updatePlaceHint();
          // Wait a frame for scene to update
          if (!this.useLegacyStamping) {
            requestAnimationFrame(() => {
              // Immediately place one at camera center for instant preview
              this._placeAtCameraCenter();
            });
          }
        });
      }
    } catch (e) {
      console.error('Texture creation failed', e);
      alert('Could not arm image as texture.');
    }
  }

  async _applyStampAtPick(pickInfo) {
    if (!pickInfo) {
      return null;
    }
    if (this.useLegacyStamping && this.legacyPainter) {
      const uv = this._getTextureCoordinates(pickInfo);
      if (!uv) {
        console.warn('No UV coordinates available for legacy stamping');
        return null;
      }
      const src = this.currentStampDataUrl || this._textureToDataUrl(this.currentStampTexture);
      if (!src) {
        console.warn('No source image available for legacy stamping');
        return null;
      }
      const success = await this.legacyPainter.stampDataUrl(src, uv, {
        widthWorld: this.decalSize.w,
        heightWorld: this.decalSize.h,
        rotationDeg: this.decalAngleDeg
      });
      return success ? { painted: true } : null;
    }

    const decal = this._createDecalAtPick(pickInfo, this.currentStampTexture);
    return decal ? { mesh: decal } : null;
  }

  _getTextureCoordinates(pickInfo) {
    if (!pickInfo?.hit || typeof pickInfo.getTextureCoordinates !== 'function') {
      return null;
    }
    try {
      const uv = pickInfo.getTextureCoordinates();
      if (!uv) {
        return null;
      }
      return {
        u: Math.min(1, Math.max(0, uv.x)),
        v: Math.min(1, Math.max(0, 1 - uv.y))
      };
    } catch (err) {
      console.warn('Failed to fetch UV coordinates:', err);
      return null;
    }
  }

  _textureToDataUrl(texture) {
    if (!texture || typeof texture.getContext !== 'function') {
      return null;
    }
    try {
      const ctx = texture.getContext();
      const canvas = ctx?.canvas;
      if (canvas && typeof canvas.toDataURL === 'function') {
        return canvas.toDataURL('image/png');
      }
    } catch (err) {
      console.warn('Failed to convert texture to data URL:', err);
    }
    return null;
  }

  _createTextTexture(text, fontFamily, color, size = 512) {
    const dt = new BABYLON.DynamicTexture('textStamp', size, this.scene, true);
    const ctx = dt.getContext();
    ctx.clearRect(0, 0, size, size);
    ctx.fillStyle = 'rgba(0,0,0,0)';
    ctx.fillRect(0, 0, size, size);
    const fontSize = Math.floor(size * 0.18);
    ctx.font = `${fontSize}px ${fontFamily}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = color;
    ctx.fillText(text, size / 2, size / 2);
    dt.hasAlpha = true;
    dt.update();
    return dt;
  }

  _calculateSurfaceNormal(pickInfo) {
    // Get normal vector with proper error handling
    let normal;
    try { 
      normal = pickInfo.getNormal && pickInfo.getNormal(true);
    } catch(e) { 
      normal = null;
    }
    
    // Default to up vector if no normal available
    normal = normal || new BABYLON.Vector3(0,0,1);
    
    // Get ray direction for orientation
    const rayDir = pickInfo.ray?.direction || this.camera.getForwardRay().direction;
    
    // Adjust normal direction based on forceOutward setting
    return (!this.forceOutward || BABYLON.Vector3.Dot(normal, rayDir) > 0) 
      ? normal.negate() 
      : normal.clone();
  }

  _createDecalAtPick(pickInfo, texture) {
    console.log('Creating decal at pick point');
    const sourceMesh = pickInfo.pickedMesh;
    if (!sourceMesh || !pickInfo.pickedPoint) {
      console.error('Invalid pick info - missing mesh or point');
      return null;
    }

    // Ensure mesh is ready for decals  
    sourceMesh.isVisible = true;
    sourceMesh.visibility = 1.0;
    sourceMesh.receiveShadows = true;

    // Calculate surface normal
    const normal = this._calculateSurfaceNormal(pickInfo);

    // Create decal based on type
    if (this.currentStampKind === 'text' && this.currentStampMeta) {
      return this.textManager.createTextDecal(
        sourceMesh,
        this.currentStampMeta.text,
        pickInfo.pickedPoint,
        normal,
        {
          font: this.currentStampMeta.font,
          color: this.currentStampMeta.color,
          width: this.decalSize.w,
          height: this.decalSize.h,
          angle: this.decalAngleDeg,
          zOffset: this.decalZOffset,
          depthWrite: !this.decalDepthWriteOff
        }
      );
    } else {
      return this.logoManager.createLogoDecal(
        sourceMesh,
        pickInfo.pickedPoint,
        normal,
        {
          width: this.decalSize.w,
          height: this.decalSize.h,
          angle: this.decalAngleDeg,
          zOffset: this.decalZOffset,
          depthWrite: !this.decalDepthWriteOff
        }
      );
    }

    // Move pick point slightly in front of surface for better visibility
    const adjustedPoint = pickInfo.pickedPoint.add(normal.scale(0.01));
    // Calculate final position and create the decal
    const w = this.decalSize?.w ?? 0.8;
    const h = this.decalSize?.h ?? 0.8;
    // Keep decal depth small but controlled
    const size = new BABYLON.Vector3(w, h, 0.05);
    
    const decal = BABYLON.MeshBuilder.CreateDecal('decal', sourceMesh, {
      position: adjustedPoint,
      normal: normal,
      size: size,
      angle: (this.decalAngleDeg || 0) * Math.PI / 180,
      cullBackFaces: false,
      localMode: true // Important for consistent projection
    });

    console.log('Created decal:', decal.name, 'at position:', adjustedPoint.asArray());
    
    // Use PBRMaterial for enhanced visibility
    const mat = new BABYLON.PBRMaterial('decalMat', this.scene);
    mat.metallic = 0;
    mat.roughness = 1;
    mat.alphaMode = BABYLON.Engine.ALPHA_COMBINE;
    mat.diffuseTexture = texture;
    mat.diffuseTexture.hasAlpha = true;
    mat.useAlphaFromDiffuseTexture = true;
    mat.opacityTexture = mat.diffuseTexture;
    mat.diffuseTexture.coordinatesMode = BABYLON.Texture.EXPLICIT_MODE;    // Ensure decal renders properly
    mat.zOffset = this.decalZOffset;
    mat.depthWrite = !this.depthWriteOff;
    mat.disableLighting = true;
    mat.emissiveColor = BABYLON.Color3.White();
    mat.specularColor = BABYLON.Color3.Black();
    
    console.log('Decal material settings:', {
      zOffset: mat.zOffset,
      depthWrite: mat.depthWrite,
      hasAlpha: mat.diffuseTexture.hasAlpha
    });
    mat.disableLighting = true;
    mat.emissiveColor = new BABYLON.Color3(1,1,1);
    mat.specularColor = new BABYLON.Color3(0, 0, 0);
    decal.material = mat;
    decal.isPickable = true;
    decal.metadata = {
      isDecal: true,
      kind: this.currentStampKind || 'logo',
      source: this.currentStampMeta || {},
      pick: {
        position: pickInfo.pickedPoint ? pickInfo.pickedPoint.asArray() : null,
        normal: (pickInfo.getNormal && pickInfo.getNormal(true)) ? pickInfo.getNormal(true).asArray() : null,
        angle: this.decalAngleDeg || 0,
        size: [w, h, Math.max(w, h)]
      }
    };
    // Parent to root so it follows apparel
    if (this.sceneManager.currentRoot) {
      decal.parent = this.sceneManager.currentRoot;
    }
    return decal;
  }

  // Helper: place current stamp at the camera center (ray to camera target)
  _placeAtCameraCenter() {
    if (this.useLegacyStamping) {
      return;
    }
    if (!this.currentStampTexture) return;
    console.log('Attempting auto-placement of texture:', this.currentStampKind);

    // First check if we have any meshes to place on and debug their state
    console.log('Available meshes:', this.scene.meshes.map(m => ({
      name: m.name,
      enabled: m.isEnabled(),
      visible: m.isVisible,
      visibility: m.visibility,
      hasParent: !!m.parent
    })));

    const garmentMesh = this.scene.meshes.find(mesh => {
      if (!mesh || !mesh.isEnabled()) return false;
      if (mesh === this.pickCursor || mesh === this.normalArrow) return false;
      if (mesh.metadata && mesh.metadata.isDecal) return false;
      if (mesh.name && (mesh.name.includes('hdrSkyBox') || mesh.name.includes('helper'))) return false;
      return true;
    });

    if (!garmentMesh) {
      console.error('No garment mesh found to place decal on!');
      return;
    }
    
    // Force visibility
    if (!garmentMesh.isVisible || garmentMesh.visibility < 1) {
      console.log('Making mesh visible:', garmentMesh.name);
      garmentMesh.isVisible = true;
      garmentMesh.visibility = 1.0;
    }
    
    console.log('Found garment mesh:', garmentMesh.name, 'visible:', garmentMesh.isVisible);
    
    // Try picking at the center of the screen first
    // Use main garment mesh from SceneManager
    const targetMesh = this.sceneManager.garmentMeshes?.[0];
    if (!targetMesh) {
      console.error('No target mesh found for placement');
      return;
    }
    console.log('Target mesh for placement:', targetMesh.name);

    const scene = this.scene;
    const engine = scene.getEngine();
    const width = engine.getRenderWidth();
    const height = engine.getRenderHeight();
    
    // Force pick on our target mesh
    let pickInfo = scene.pick(width / 2, height / 2, (mesh) => {
      return mesh === targetMesh;
      // Ignore our helper objects and decals
      if (mesh === this.pickCursor || mesh === this.normalArrow) return false;
      if (mesh.metadata && mesh.metadata.isDecal) return false;
      if (mesh.name && (mesh.name.includes('hdrSkyBox') || mesh.name.includes('helper'))) return false;
      console.log('Considering mesh for pick:', mesh.name);
      return true;
    });

    if (!pickInfo || !pickInfo.hit) {
      console.log('Center screen pick failed, trying raycast to camera target...');
      // Fallback: try ray from camera to its target
      const target = this.camera.target || new BABYLON.Vector3(0, 0, 0);
      const origin = this.camera.globalPosition ? this.camera.globalPosition.clone() : this.camera.position.clone();
      const dir = target.subtract(origin).normalize();
      const ray = new BABYLON.Ray(origin, dir, 1000);
      pickInfo = scene.pickWithRay(ray, (mesh) => {
        if (!mesh || !mesh.isEnabled()) return false;
        if (mesh === this.pickCursor || mesh === this.normalArrow) return false;
        if (mesh.metadata && mesh.metadata.isDecal) return false;
        if (mesh.name && (mesh.name.includes('hdrSkyBox') || mesh.name.includes('helper'))) return false;
        console.log('Considering mesh for ray pick:', mesh.name);
        return true;
      });
    }

    if (pickInfo && pickInfo.hit && pickInfo.pickedMesh) {
      console.log('Got valid pick on mesh:', pickInfo.pickedMesh.name);
      const decal = this._createDecalAtPick(pickInfo, this.currentStampTexture);
      if (decal) {
        this.createdElements.push(decal);
        this.selectMesh(decal);
        this.pickCursor.setEnabled(false);
        console.log('Successfully placed decal');
      } else {
        console.error('Failed to create decal from pick info');
      }
    } else {
      console.warn('No valid pick for auto-placement - is the T-shirt visible in center screen?');
    }
  }

  _updatePlaceHint() {
    if (!this.placeHintEl) return;
    const shouldShow = this.mode === 'place' && !!this.currentStampTexture;
    this.placeHintEl.style.display = shouldShow ? 'block' : 'none';
    if (shouldShow) {
      this.placeHintEl.textContent = this.useLegacyStamping
        ? 'Click on the apparel to stamp'
        : 'Click on the garment to place decal';
    }
    
    // Update debug sphere at camera target
    if (this.camera && this.camera.target && this.debugSphere) {
      this.debugSphere.position.copyFromFloats(
        this.camera.target.x,
        this.camera.target.y,
        this.camera.target.z
      );
    }
  }

  exportDesignData() {
    const apparelType = (typeof localStorage !== 'undefined' && localStorage.getItem('apparel-type')) || 'tshirt';
    const elements = this.createdElements.map(mesh => {
      const rot = mesh.rotationQuaternion ? mesh.rotationQuaternion.toEulerAngles() : mesh.rotation;
      return {
        id: mesh.id,
        name: mesh.name,
        kind: mesh.metadata?.kind || 'logo',
        source: mesh.metadata?.source || {},
        transform: {
          position: mesh.position.asArray(),
          rotation: rot.asArray(),
          scaling: [mesh.scaling.x, mesh.scaling.y, mesh.scaling.z]
        }
      };
    });
    return {
      version: 1,
      apparelType,
      elements
    };
  }

  undo() {
    if (this.legacyPainter && this.legacyPainter.undo()) {
      return;
    }
    const last = this.createdElements.pop();
    if (last) {
      if (this.selectedMesh === last) this.selectMesh(null);
      try {
        if (last && typeof last.dispose === 'function') {
          last.dispose();
        }
      } catch (err) {
        console.warn('Failed to dispose element during undo:', err);
      }
    }
  }

  resetDesign() {
    this.createdElements.forEach((m) => {
      try {
        if (m && typeof m.dispose === 'function') {
          m.dispose();
        }
      } catch (err) {
        console.warn('Failed to dispose element during reset:', err);
      }
    });
    this.createdElements = [];
    this.selectMesh(null);
    if (this.legacyPainter) {
      this.legacyPainter.resetToBaseColors();
    }
  }
}
