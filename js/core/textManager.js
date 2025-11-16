// TextManager: Handles text rendering, font management, and text decal creation
export class TextManager {
    constructor(scene) {
        this.scene = scene;
        this.fonts = [
            'Arial',
            'Inter',
            'Lora',
            'Impact',
            'Courier New'
        ];
        this.defaultFontSize = 72;
    }

    createTextTexture(text, options = {}) {
        const {
            font = 'Arial',
            color = '#000000',
            size = 512,
            fontSize = this.defaultFontSize
        } = options;

        console.log('Creating text texture:', { text, font, color, size });

        const dynamicTexture = new BABYLON.DynamicTexture(
            'textStamp',
            size,
            this.scene,
            true  // No generate mipmaps for sharper text
        );
        
        const ctx = dynamicTexture.getContext();
        
        // Clear with transparency
        ctx.clearRect(0, 0, size, size);
        ctx.fillStyle = 'rgba(0,0,0,0)';
        ctx.fillRect(0, 0, size, size);
        
        // Configure text rendering
        ctx.font = `${fontSize}px ${font}`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = color;
        
        // Draw text
        ctx.fillText(text, size / 2, size / 2);
        
        // Update texture
        dynamicTexture.update();
        dynamicTexture.hasAlpha = true;
        dynamicTexture.wrapU = BABYLON.Texture.CLAMP_ADDRESSMODE;
        dynamicTexture.wrapV = BABYLON.Texture.CLAMP_ADDRESSMODE;
        dynamicTexture.anisotropicFilteringLevel = 8;

        return dynamicTexture;
    }

    createTextMaterial(texture) {
        const mat = new BABYLON.PBRMaterial('textMat', this.scene);
        
        // Base material settings
        mat.metallic = 0;
        mat.roughness = 1;
        mat.unlit = true;
        
        // Texture setup
        mat.albedoTexture = texture;
        mat.albedoTexture.hasAlpha = true;
        mat.useAlphaFromAlbedoTexture = true;
        
        // Enhanced alpha handling
        mat.transparencyMode = BABYLON.PBRMaterial.PBRMATERIAL_ALPHABLEND;
        mat.separateCullingPass = true;
        mat.backFaceCulling = false;
        
        // Depth and render settings
        mat.forceDepthWrite = true;
        mat.alphaMode = BABYLON.Engine.ALPHA_COMBINE;

        return mat;
    }

    createTextDecal(mesh, text, position, normal, options = {}) {
        const {
            font = 'Arial',
            color = '#000000',
            width = 0.8,
            height = 0.8,
            angle = 0,
            zOffset = 2,
            depthWrite = true
        } = options;

        // Create texture with text
        const texture = this.createTextTexture(text, { font, color });
        
        // Use proportional depth for better visibility
        const depth = Math.min(width, height) * 0.2; // 20% of smaller dimension

        // Create decal mesh
        const decal = BABYLON.MeshBuilder.CreateDecal('textDecal', mesh, {
            position: position,
            normal: normal,
            size: new BABYLON.Vector3(width, height, depth),
            angle: angle * Math.PI / 180,
            cullBackFaces: false,
            localMode: true
        });

        // Apply material
        const material = this.createTextMaterial(texture);
        material.zOffset = zOffset;
        material.depthWrite = depthWrite;
        decal.material = material;

        return decal;
    }

    getFontList() {
        return this.fonts;
    }
}