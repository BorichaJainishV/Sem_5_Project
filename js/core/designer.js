// Designer: orchestrates all managers and boot sequence
import { SceneManager } from './sceneManager.js?v=20251011';
import { UIController } from './uiController.js?v=20251011';
import { ApiService } from './apiService.js?v=20251011';
import { LogoManager } from './logoManager.js?v=20251011';
import { TextManager } from './textManager.js?v=20251011';

export function applyDesignTexture({
  textureUrl,
  paintContext,
  paintTexture,
  saveState,
  markArtworkAdded,
  recordTimelineEntry,
  timelineMessage,
  bustCache = true,
} = {}) {
  return new Promise((resolve, reject) => {
    if (!textureUrl) {
      reject(new Error('No texture URL provided'));
      return;
    }
    if (!paintContext || !paintTexture || typeof saveState !== 'function') {
      reject(new Error('Painting context is not ready'));
      return;
    }

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        saveState();
        paintContext.clearRect(0, 0, 1024, 1024);
        paintContext.drawImage(img, 0, 0, 1024, 1024);
        paintTexture.update();
        if (typeof markArtworkAdded === 'function') {
          markArtworkAdded();
        }
        if (timelineMessage && typeof recordTimelineEntry === 'function') {
          recordTimelineEntry(timelineMessage);
        }
        resolve(true);
      } catch (error) {
        reject(error);
      }
    };
    img.onerror = () => reject(new Error('Unable to load texture image'));
    img.src = bustCache ? `${textureUrl}${textureUrl.includes('?') ? '&' : '?'}t=${Date.now()}` : textureUrl;
  });
}

export class Designer {
  constructor({ canvas }) {
    if (!canvas || !(canvas instanceof HTMLCanvasElement)) {
      console.error('Invalid canvas:', canvas);
      throw new Error('A valid canvas element is required for Designer initialization');
    }
    
    // Store canvas reference
    this.canvas = canvas;
    this.initialized = false;
    
    // Initialize properties
    this.sceneManager = null;
    this.logoManager = null;
    this.textManager = null;
    this.ui = null;
    this.api = null;
    
    // Bind methods
    this._initializeManagers = this._initializeManagers.bind(this);
    this._handleError = this._handleError.bind(this);
  }
  
  async _initializeManagers() {
    console.log('Starting manager initialization sequence...');
    
    try {
      // Initialize SceneManager with proper error handling
      console.log('Initializing SceneManager...');
      try {
        this.sceneManager = new SceneManager({ canvas: this.canvas });
        
        // Wait for scene to be ready
        await new Promise((resolve, reject) => {
          // Set timeout for initialization
          const timeout = setTimeout(() => {
            reject(new Error('Scene initialization timed out'));
          }, 10000);
          
          // Check if scene is already ready
          if (this.sceneManager.scene.isReady()) {
            clearTimeout(timeout);
            console.log('Scene was immediately ready');
            resolve();
            return;
          }
          
          // Otherwise wait for it to be ready
          this.sceneManager.scene.executeWhenReady(() => {
            clearTimeout(timeout);
            console.log('Scene is now ready');
            resolve();
          });
        });
        
        console.log('SceneManager initialized successfully');
      } catch (error) {
        console.error('Failed to initialize SceneManager:', error);
        throw error;
      }
      
      // Initialize other managers only after scene is ready
      console.log('Initializing LogoManager...');
      this.logoManager = new LogoManager(this.sceneManager.scene);
      
      console.log('Initializing TextManager...');
      this.textManager = new TextManager(this.sceneManager.scene);
      
      console.log('Initializing UIController...');
      this.ui = new UIController({ 
        sceneManager: this.sceneManager,
        logoManager: this.logoManager,
        textManager: this.textManager
      });
      
      console.log('Initializing ApiService...');
      this.api = new ApiService({ sceneManager: this.sceneManager });
      
      console.log('All managers initialized successfully');
      this.initialized = true;
    } catch (err) {
      this._handleError('Failed to initialize managers', err);
      throw err;
    }
  }
  
  _handleError(message, error) {
    console.error(`Designer Error - ${message}:`, error);
    // Clean up any partially initialized resources
    this._cleanup();
    throw new Error(`${message}: ${error.message}`);
  }
  
  _cleanup() {
    try {
      // Clean up managers in reverse order of creation
      if (this.ui) {
        console.log('Cleaning up UI...');
        // Remove event listeners
        const canvas = this.canvas;
        canvas.removeEventListener('pointerdown', this.ui._onPointerDown);
        canvas.removeEventListener('pointermove', this.ui._onPointerMove);
        canvas.removeEventListener('pointerup', this.ui._onPointerUp);
      }
      
      if (this.logoManager) {
        console.log('Cleaning up LogoManager...');
        this.logoManager = null;
      }
      
      if (this.textManager) {
        console.log('Cleaning up TextManager...');
        this.textManager = null;
      }
      
      if (this.sceneManager) {
        console.log('Disposing SceneManager...');
        // Stop render loop first
        if (this.sceneManager.engine) {
          this.sceneManager.engine.stopRenderLoop();
        }
        // Then dispose scene and engine
        this.sceneManager.dispose();
      }
      
      // Reset all managers
      this.sceneManager = null;
      this.logoManager = null;
      this.textManager = null;
      this.ui = null;
      this.api = null;
      this.initialized = false;
      
      console.log('Cleanup completed successfully');
    } catch (err) {
      console.error('Error during cleanup:', err);
    }
  }

  async init() {
    console.log('Designer: Starting initialization sequence...');
    
    try {
      // First initialize all managers
      await this._initializeManagers();
      
      if (!this.initialized || !this.ui) {
        throw new Error('Managers failed to initialize properly');
      }
      
      console.log('Binding UI controls...');
      // Initialize UI bindings
      await Promise.all([
        this.ui._bindControls(),
        this.ui._bindPicking(),
        this.ui._bindDesignTools()
      ]);
      
      // Load default apparel
      const last = localStorage.getItem('apparel-type') || 'tshirt';
      console.log('Loading default apparel:', last);
      
      // Wait for apparel to load
      await this.ui._loadApparel(last);
      console.log('Initial apparel loaded successfully');
      
      // Set initial base colors
  await this.ui._updateBaseColors();
      
      console.log('Designer initialized successfully');
    } catch (e) {
      console.error('Failed to initialize designer:', e);
      // Attempt to load tshirt as fallback
      try {
        console.log('Attempting to load fallback tshirt...');
        await this.ui._loadApparel('tshirt');
      } catch (fallbackError) {
        console.error('Critical: Failed to load fallback apparel:', fallbackError);
        throw new Error('Failed to initialize 3D designer: ' + e.message);
      }
    }

    // Wire Save & Add to Cart
    const saveBtn = document.getElementById('saveDesignBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
        try {
          // Save a 4K image to match Export 4K quality
          const designData = this.ui.exportDesignData();
          const result = await this.api.saveDesign({ width: 3840, height: 2160, designData });
          if (result?.success) {
            // Go to cart
            window.location.href = 'cart.php';
          } else {
            alert(result?.error || 'Failed to save design');
          }
        } catch (err) {
          console.error(err);
          alert('Failed to save design');
        } finally {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save & Add to Cart';
        }
      });
    }

    // Wire Save Multi-View & Add to Cart
    const saveMultiBtn = document.getElementById('saveDesignMultiBtn');
    if (saveMultiBtn) {
      saveMultiBtn.addEventListener('click', async () => {
        saveMultiBtn.disabled = true;
        saveMultiBtn.textContent = 'Saving Multi-View…';
        try {
          const designData = this.ui.exportDesignData();
          // Square 1920x1920 for consistent side/back previews
          const result = await this.api.saveDesignMulti({ width: 1920, height: 1920, designData });
          if (result?.success) {
            window.location.href = 'cart.php';
          } else {
            alert(result?.error || 'Failed to save multi-view design');
          }
        } catch (err) {
          console.error(err);
          alert('Failed to save multi-view design');
        } finally {
          saveMultiBtn.disabled = false;
          saveMultiBtn.textContent = 'Save Multi-View & Add to Cart';
        }
      });
    }
  }
}
