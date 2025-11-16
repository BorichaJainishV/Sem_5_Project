// LogoManager: Handles logo processing, texture creation, and decal application
export class LogoManager {
    constructor(scene) {
        this.scene = scene;
        this.currentStampTexture = null;
        this.logoPreviewCanvas = document.getElementById('logoPreview');
    }

    async createLogoTexture(dataURL, options = {}) {
        console.log('Creating logo texture with options:', options);
        
        try {
            // Create a dynamic texture
            const tempCanvas = document.createElement('canvas');
            const tempCtx = tempCanvas.getContext('2d');
            
            // Load image first
            const img = await new Promise((resolve, reject) => {
                const image = new Image();
                image.onload = () => resolve(image);
                image.onerror = () => reject(new Error('Failed to load image'));
                image.src = dataURL;
            });

            // Set canvas size and draw image
            tempCanvas.width = img.width;
            tempCanvas.height = img.height;
            tempCtx.drawImage(img, 0, 0);

            // Create dynamic texture
            const tex = new BABYLON.DynamicTexture(
                'logoTexture',
                { width: img.width, height: img.height },
                this.scene,
                false,
                BABYLON.Texture.TRILINEAR_SAMPLINGMODE
            );

            // Copy canvas content to texture
            const context = tex.getContext();
            context.drawImage(img, 0, 0);
            tex.update(false);

            // Configure texture properties
            tex.hasAlpha = true;
            tex.wrapU = BABYLON.Texture.CLAMP_ADDRESSMODE;
            tex.wrapV = BABYLON.Texture.CLAMP_ADDRESSMODE;
            tex.anisotropicFilteringLevel = 16;

            // Store the texture for later use
            this.currentStampTexture = tex;
            
            return tex;
        } catch (error) {
            console.error('Error creating logo texture:', error);
            throw error;
        }
    }

    createLogoMaterial(texture) {
        const mat = new BABYLON.PBRMaterial('logoMat', this.scene);
        
        // Base material settings
        mat.metallic = 0;
        mat.roughness = 1;
        mat.unlit = true;
        mat.emissiveColor = BABYLON.Color3.White();
        mat.albedoColor = BABYLON.Color3.White();
        
        // Texture setup
        mat.albedoTexture = texture;
        mat.albedoTexture.hasAlpha = true;
        mat.useAlphaFromAlbedoTexture = true;
        mat.albedoTexture.coordinatesMode = BABYLON.Texture.TRILINEAR_SAMPLINGMODE;
        
        // Enhanced alpha handling
        mat.transparencyMode = BABYLON.PBRMaterial.PBRMATERIAL_ALPHABLEND;
        mat.separateCullingPass = true;
        mat.backFaceCulling = false;
        mat.twoSidedLighting = true;
        
        // Depth and render settings
        mat.forceDepthWrite = true;
        mat.useAlphaFromAlbedoTexture = true;
        mat.needDepthPrePass = true;
        mat.alphaMode = BABYLON.Engine.ALPHA_COMBINE;
        mat.disableDepthWrite = false;
        mat.transparencyMode = BABYLON.PBRMaterial.MATERIAL_ALPHABLEND;

        return mat;
    }

    processLogoImage(imageElement, options = {}) {
        const {
            autoCrop = true,
            removeWhite = false,
            threshold = 30
        } = options;

        if (!this.logoPreviewCanvas) {
            console.warn('No preview canvas available for processing');
            return null;
        }

    const ctx = this.logoPreviewCanvas.getContext('2d', { willReadFrequently: true });
        const w = imageElement.width;
        const h = imageElement.height;

        // Resize if needed
        const maxDim = 1024;
        const scale = Math.min(1, maxDim / Math.max(w, h));
        this.logoPreviewCanvas.width = Math.max(1, Math.round(w * scale));
        this.logoPreviewCanvas.height = Math.max(1, Math.round(h * scale));

        // Draw and process
        ctx.clearRect(0, 0, this.logoPreviewCanvas.width, this.logoPreviewCanvas.height);
        ctx.drawImage(imageElement, 0, 0, this.logoPreviewCanvas.width, this.logoPreviewCanvas.height);

        if (removeWhite) {
            const imageData = ctx.getImageData(0, 0, this.logoPreviewCanvas.width, this.logoPreviewCanvas.height);
            const data = imageData.data;
            
            for (let i = 0; i < data.length; i += 4) {
                const r = data[i], g = data[i + 1], b = data[i + 2];
                const maxc = Math.max(r, g, b);
                const minc = Math.min(r, g, b);
                const isNearWhite = maxc > (255 - threshold) && (maxc - minc) < threshold;
                if (isNearWhite) {
                    data[i + 3] = 0; // Make transparent
                }
            }
            
            ctx.putImageData(imageData, 0, 0);
        }

        if (autoCrop) {
            const imageData = ctx.getImageData(0, 0, this.logoPreviewCanvas.width, this.logoPreviewCanvas.height);
            const data = imageData.data;
            let xMin = this.logoPreviewCanvas.width, xMax = 0, yMin = this.logoPreviewCanvas.height, yMax = 0;

            // Find bounds of non-transparent pixels
            for (let y = 0; y < this.logoPreviewCanvas.height; y++) {
                for (let x = 0; x < this.logoPreviewCanvas.width; x++) {
                    const idx = (y * this.logoPreviewCanvas.width + x) * 4 + 3;
                    if (data[idx] > 5) {
                        xMin = Math.min(xMin, x);
                        xMax = Math.max(xMax, x);
                        yMin = Math.min(yMin, y);
                        yMax = Math.max(yMax, y);
                    }
                }
            }

            // Crop if we found content
            if (xMax >= xMin && yMax >= yMin) {
                const cropped = ctx.getImageData(xMin, yMin, xMax - xMin + 1, yMax - yMin + 1);
                this.logoPreviewCanvas.width = xMax - xMin + 1;
                this.logoPreviewCanvas.height = yMax - yMin + 1;
                ctx.putImageData(cropped, 0, 0);
            }
        }

        return this.logoPreviewCanvas.toDataURL('image/png');
    }

    async createLogoDecal(mesh, position, normal, options = {}) {
        if (!this.currentStampTexture) {
            console.warn('No logo texture armed for decal creation');
            throw new Error('No logo texture available');
        }

        // Wait for texture to be ready
        await new Promise((resolve, reject) => {
            if (this.currentStampTexture.isReady()) {
                resolve();
            } else {
                const checkInterval = setInterval(() => {
                    if (this.currentStampTexture.isReady()) {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
                
                // Timeout after 5 seconds
                setTimeout(() => {
                    clearInterval(checkInterval);
                    reject(new Error('Texture loading timeout'));
                }, 5000);
            }
        });

        try {
            const {
                width = 0.8,
                height = 0.8,
                angle = 0,
                zOffset = 2,
                depthWrite = true
            } = options;

            // Use proportional depth for better visibility
            const depth = Math.min(width, height) * 0.2; // 20% of smaller dimension

            // Create the decal mesh
            const decal = BABYLON.MeshBuilder.CreateDecal('logoDecal', mesh, {
                position: position,
                normal: normal,
                size: new BABYLON.Vector3(width, height, depth),
                angle: angle * Math.PI / 180,
                cullBackFaces: false,
                localMode: true
            });

            if (!decal) {
                throw new Error('Failed to create decal mesh');
            }

            // Create and apply material
            const material = this.createLogoMaterial(this.currentStampTexture);
            material.zOffset = zOffset;
            material.depthWrite = depthWrite;
            decal.material = material;

            return decal;
        } catch (error) {
            console.error('Error creating logo decal:', error);
            throw error;
        }
    }
}