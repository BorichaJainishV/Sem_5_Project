// designTools.js - Enhanced tools for design manipulation

export class DesignTools {
    constructor(scene) {
        this.scene = scene;
    }

    static createColorTexture(scene, color1, color2, size = 512) {
        const texture = new BABYLON.DynamicTexture('splitColor', size, scene, true);
        const ctx = texture.getContext();
        
        // Clear canvas
        ctx.clearRect(0, 0, size, size);
        
        // Draw split color design
        ctx.fillStyle = color1;
        ctx.fillRect(0, 0, size / 2, size);
        ctx.fillStyle = color2;
        ctx.fillRect(size / 2, 0, size / 2, size);
        
        // Update texture
        texture.update();
        return texture;
    }

    static createTextTexture(scene, text, options = {}) {
        const {
            font = 'Arial',
            color = '#000000',
            size = 512,
            fontSize = Math.floor(size * 0.18)
        } = options;

        const texture = new BABYLON.DynamicTexture('textStamp', size, scene, true);
        const ctx = texture.getContext();
        
        // Setup canvas
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
        
        // Configure texture
        texture.hasAlpha = true;
        texture.update();
        return texture;
    }
}