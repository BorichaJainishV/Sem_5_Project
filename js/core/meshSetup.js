// meshSetup.js - Enhanced mesh setup utilities

export class MeshSetup {
    static setupMesh(mesh, material, scene) {
        console.log(`Setting up mesh: ${mesh.name}`);
        
        // Force visibility
        mesh.setEnabled(true);
        mesh.isPickable = true;
        mesh.isVisible = true;
        mesh.visibility = 1.0;
        mesh.receiveShadows = true;

        // Create material
        const mat = material.clone(`mat_${mesh.name}`);
        this.configureMaterial(mat);
        
        // Apply material
        mesh.material = mat;
        console.log(`Mesh ${mesh.name} setup complete`);
        
        return mesh;
    }
    
    static configureMaterial(material) {
        // Basic settings
        material.backFaceCulling = false;
        material.twoSidedLighting = true;
    if ('needDepthPrePass' in material) material.needDepthPrePass = false;
    if ('forceDepthWrite' in material) material.forceDepthWrite = true;
    if ('separateCullingPass' in material) material.separateCullingPass = false;
    if ('transparencyMode' in material) material.transparencyMode = BABYLON.PBRMaterial.PBRMATERIAL_OPAQUE;
        
        // Color and lighting
        material.albedoColor = new BABYLON.Color3(1, 1, 1);
    if ('metallic' in material) material.metallic = 0;
    if ('roughness' in material) material.roughness = 0.5;
    if ('emissiveIntensity' in material) material.emissiveIntensity = 0.1;
        
        // Disable unused features
    if ('useAmbientOcclusionFromMetallicTextureRed' in material) material.useAmbientOcclusionFromMetallicTextureRed = false;
    if ('useRoughnessFromMetallicTextureGreen' in material) material.useRoughnessFromMetallicTextureGreen = false;
    if ('useMetallnessFromMetallicTextureBlue' in material) material.useMetallnessFromMetallicTextureBlue = false;
        
        return material;
    }
}