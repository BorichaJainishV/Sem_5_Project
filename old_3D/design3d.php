<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'header.php';

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = "You need to log in to access the 3D Designer.";
    header('Location: login.php');
    exit();
}
?>

<style>
    .design3d-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        padding: 2rem 0;
    }
    #renderCanvas {
        width: 100%;
        height: 70vh;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        outline: none;
        touch-action: none;
        cursor: grab;
    }
    #renderCanvas.drawing-enabled {
        cursor: crosshair;
    }
    .sidebar-controls {
        position: sticky;
        top: 100px;
        height: fit-content;
    }
    .control-section {
        background-color: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow);
    }
    .control-section-header {
        padding: 1rem 1.5rem;
        font-weight: var(--font-semibold);
        color: var(--color-dark);
        border-bottom: 1px solid var(--color-border);
    }
    .control-section-body {
        padding: 1.5rem;
    }
    #drawToggleBtn.btn-success {
        background-color: var(--color-success);
        color: white;
        border-color: var(--color-success);
    }
    @media (max-width: 992px) {
        .design3d-layout { grid-template-columns: 1fr; }
        .sidebar-controls { position: static; }
    }
</style>

<main class="container">
    <div class="page-header">
        <h1>3D T-Shirt Designer</h1>
        <p><b>How to use:</b> Click on the shirt to choose a location, then add your logo or text.</p>
    </div>

    <div class="design3d-layout">
        <div class="canvas-container">
            <canvas id="renderCanvas"></canvas>
        </div>

        <div class="sidebar-controls">
            <div class="control-section">
                <div class="control-section-header">T-Shirt Color</div>
                <div class="control-section-body">
                    <label for="baseColor" class="form-label">Select Base Color</label>
                    <input type="color" id="baseColor" value="#cccccc" class="form-control" style="height: 48px;">
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Add Logo</div>
                <div class="control-section-body">
                    <label for="logoUpload" class="form-label">Upload Image</label>
                    <input type="file" id="logoUpload" class="form-control" accept="image/png, image/jpeg">
                    <label for="logoSize" class="form-label mt-3">Logo Size: <span id="logoSizeValue">40</span></label>
                    <input type="range" id="logoSize" min="10" max="80" value="40" class="w-full">
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Add Custom Text</div>
                <div class="control-section-body">
                    <label for="customText" class="form-label">Enter Text</label>
                    <input type="text" id="customText" placeholder="Your Text Here" class="form-control mb-3">
                    <label for="fontStyle" class="form-label">Font Style</label>
                    <select id="fontStyle" class="form-control mb-3">
                        <option value="Arial">Arial</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Impact">Impact</option>
                    </select>
                    <label for="fontSize" class="form-label">Font Size: <span id="fontSizeValue">40</span></label>
                    <input type="range" id="fontSize" min="20" max="80" value="40" class="w-full mb-3">
                    <button id="addTextBtn" class="btn btn-secondary w-full">Apply Text</button>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Free-Hand Drawing</div>
                <div class="control-section-body">
                    <button id="drawToggleBtn" class="btn btn-outline w-full mb-4">Drawing is OFF</button>
                    <label for="brushColor" class="form-label">Brush Color</label>
                    <input type="color" id="brushColor" value="#ff0000" class="form-control mb-3" style="height: 48px;">
                    <label for="brushSize" class="form-label">Brush Size: <span id="brushSizeValue">5</span></label>
                    <input type="range" id="brushSize" min="1" max="20" value="5" class="w-full">
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Actions</div>
                <div class="control-section-body flex flex-col gap-4">
                    <button id="undoBtn" class="btn btn-outline w-full">Undo</button>
                    <button id="resetBtn" class="btn btn-danger w-full">Reset Design</button>
                    <button id="saveDesignBtn" class="btn btn-primary w-full">Save & Add to Cart</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.babylonjs.com/babylon.js"></script>
<script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>
<script>
    const canvas = document.getElementById('renderCanvas');
    const engine = new BABYLON.Engine(canvas, true);
    let scene, model, paintTexture, paintContext, modelLoaded = false;
    let history = [];
    let drawingModeEnabled = false;
    let lastPointerCoordinates = { x: 512, y: 412 };

    // Create Scene
    const createScene = async function () {
        scene = new BABYLON.Scene(engine);
        scene.clearColor = new BABYLON.Color4(0, 0, 0, 0);

        const camera = new BABYLON.ArcRotateCamera("camera", -Math.PI / 2, Math.PI / 2.5, 3, new BABYLON.Vector3(0, -0.5, 0), scene);
        camera.attachControl(canvas, true);
        camera.wheelPrecision = 50;
        camera.lowerRadiusLimit = 2;
        camera.upperRadiusLimit = 5;

        const light = new BABYLON.HemisphericLight("light", new BABYLON.Vector3(0, 1, 0), scene);
        light.intensity = 1.2;
        const light2 = new BABYLON.HemisphericLight("light2", new BABYLON.Vector3(0, 0, 0), scene);
        light2.intensity = 0.5;

        try {
            const result = await BABYLON.SceneLoader.ImportMeshAsync("", "models/", "tshirt.glb", scene);
            model = result.meshes[0];
            model.scaling.scaleInPlace(2.5);
            model.position.y = -4; // Adjusted for better centering

            const material = new BABYLON.PBRMaterial("pbr", scene);
            
            // FIX: More robust way to apply material to all visible parts
            result.meshes.forEach(mesh => {
                if (mesh instanceof BABYLON.Mesh) {
                    mesh.material = material;
                }
            });

            paintTexture = new BABYLON.DynamicTexture("paintTexture", { width: 1024, height: 1024 }, scene, false);
            paintContext = paintTexture.getContext({ willReadFrequently: true });
            material.albedoTexture = paintTexture;
            material.metallic = 0.1;
            material.roughness = 0.8;
            material.backFaceCulling = false;

            updateBaseColor("#cccccc");
            saveState();
            modelLoaded = true;
        } catch (e) {
            console.error("Error loading model:", e);
        }
        return scene;
    };

    function updateBaseColor(color) {
        if (!paintContext) return;
        saveState();
        paintContext.fillStyle = color;
        paintContext.fillRect(0, 0, 1024, 1024);
        paintTexture.update();
    }

    function saveState() {
        if (!paintContext) return;
        const imageData = paintContext.getImageData(0, 0, 1024, 1024);
        history.push(imageData);
        if (history.length > 20) history.shift();
    }

    function undo() {
        if (!paintContext || history.length <= 1) return;
        history.pop();
        const lastState = history[history.length - 1];
        paintContext.putImageData(lastState, 0, 0);
        paintTexture.update();
    }

    function getPointerCoordinates(pickInfo) {
        if (pickInfo.hit && pickInfo.getTextureCoordinates) {
            const texCoords = pickInfo.getTextureCoordinates();
            if (texCoords) {
                return { x: texCoords.x * 1024, y: (1 - texCoords.y) * 1024 };
            }
        }
        return null;
    }

    // --- NEW HELPER FUNCTION TO CAPTURE PREVIEWS ---
    async function capturePreview(camera, width, height, alpha, beta) {
        const originalAlpha = camera.alpha;
        const originalBeta = camera.beta;
        const originalRadius = camera.radius;

        camera.alpha = alpha;
        camera.beta = beta;
        camera.radius = 2.8; // Use a fixed zoom for consistent previews

        await scene.whenReadyAsync();
        scene.render();

        const previewData = await BABYLON.Tools.CreateScreenshotUsingRenderTargetAsync(engine, camera, { width, height });

        camera.alpha = originalAlpha;
        camera.beta = originalBeta;
        camera.radius = originalRadius;

        return previewData;
    }

    function setupEventListeners() {
        let isDrawing = false;

        scene.onPointerDown = (evt, pickInfo) => {
            const coords = getPointerCoordinates(pickInfo);
            if (coords) lastPointerCoordinates = coords;

            if (drawingModeEnabled) {
                isDrawing = true;
                saveState();
                if (coords) drawCircle(coords.x, coords.y);
            }
        };

        scene.onPointerMove = (evt, pickInfo) => {
            if (drawingModeEnabled && isDrawing) {
                const coords = getPointerCoordinates(pickInfo);
                if (coords) drawCircle(coords.x, coords.y);
            }
        };

        scene.onPointerUp = () => { isDrawing = false; };

        function drawCircle(x, y) {
            const brushSize = document.getElementById('brushSize').value;
            const brushColor = document.getElementById('brushColor').value;
            paintContext.fillStyle = brushColor;
            paintContext.beginPath();
            paintContext.arc(x, y, brushSize, 0, 2 * Math.PI);
            paintContext.fill();
            paintTexture.update();
        }

        document.getElementById('drawToggleBtn').addEventListener('click', () => {
            drawingModeEnabled = !drawingModeEnabled;
            const btn = document.getElementById('drawToggleBtn');
            btn.textContent = drawingModeEnabled ? 'Drawing is ON' : 'Drawing is OFF';
            btn.classList.toggle('btn-success');
            canvas.classList.toggle('drawing-enabled');
        });

        document.getElementById('logoUpload').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    saveState();
                    const logoSize = document.getElementById('logoSize').value * 4;
                    const x = lastPointerCoordinates.x - logoSize / 2;
                    const y = lastPointerCoordinates.y - logoSize / 2;
                    paintContext.drawImage(img, x, y, logoSize, logoSize);
                    paintTexture.update();
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
            e.target.value = ""; // Reset input
        });

        document.getElementById('addTextBtn').addEventListener('click', () => {
            const text = document.getElementById('customText').value.trim();
            if (!text) return;
            saveState();
            const fontSize = document.getElementById('fontSize').value;
            const fontStyle = document.getElementById('fontStyle').value;
            paintContext.font = `bold ${fontSize}px ${fontStyle}`;
            paintContext.fillStyle = "black";
            paintContext.textAlign = "center";
            paintContext.textBaseline = "middle";
            paintContext.fillText(text, lastPointerCoordinates.x, lastPointerCoordinates.y);
            paintTexture.update();
            document.getElementById('customText').value = '';
        });

        document.getElementById('baseColor').addEventListener('input', (e) => {
            if (modelLoaded) updateBaseColor(e.target.value);
        });

        document.getElementById('undoBtn').addEventListener('click', undo);

        document.getElementById('resetBtn').addEventListener('click', () => {
            history = [];
            updateBaseColor("#cccccc");
        });

        ['logoSize', 'fontSize', 'brushSize'].forEach(id => {
            const slider = document.getElementById(id);
            const display = document.getElementById(`${id}Value`);
            if (slider && display) {
                slider.addEventListener('input', () => display.textContent = slider.value);
            }
        });

        // --- NEW EVENT LISTENER FOR THE SAVE BUTTON ---
        document.getElementById('saveDesignBtn').addEventListener('click', async () => {
            if (!modelLoaded) {
                alert("Model not loaded yet.");
                return;
            }

            const btn = document.getElementById('saveDesignBtn');
            btn.disabled = true;
            btn.textContent = "Capturing Previews...";

            const camera = scene.activeCamera;
            const previewSize = { width: 512, height: 512 };

            try {
                // 1. Capture front and back previews
                const frontPreview = await capturePreview(camera, previewSize.width, previewSize.height, -Math.PI / 2, Math.PI / 2.5);
                const backPreview = await capturePreview(camera, previewSize.width, previewSize.height, Math.PI / 2, Math.PI / 2.5);

                // 2. Get the raw texture map
                const textureMap = paintContext.canvas.toDataURL("image/png");
                
                btn.textContent = "Saving to Database...";

                // 3. Prepare data payload for the server
                const designData = {
                    frontPreview: frontPreview,
                    backPreview: backPreview,
                    textureMap: textureMap
                };

                // 4. Send data to save_design.php
                const response = await fetch('save_design.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(designData)
                });
                
                const result = await response.json();

                if (result.success) {
		     window.location.href = 'cart.php';
                    alert(result.message);
                } else {
                    alert("Error: " + result.error);
                }

            } catch (error) {
                console.error("Failed to save design:", error);
                alert("An error occurred while saving. Please check the console.");
            } finally {
                btn.disabled = false;
                btn.textContent = "Save & Add to Cart";
            }
        });
    }

    createScene().then(() => {
        if (scene) {
            setupEventListeners();
            engine.runRenderLoop(() => { if (scene.isReady()) scene.render(); });
        }
    });

    window.addEventListener('resize', () => engine.resize());
</script>