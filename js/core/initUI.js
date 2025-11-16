// initUI.js: Enhanced UI initialization and event binding

export function initializeUI(controller) {
    console.log('Initializing UI components...');
    
    // Bind model loading buttons
    bindModelButtons(controller);
    
    // Bind mode buttons
    bindModeButtons(controller);
    
    // Bind decal controls
    bindDecalControls(controller);
    
    // Bind action buttons
    bindActionButtons(controller);
    
    // Bind color controls
    bindColorControls(controller);
    
    console.log('UI initialization complete');
}

function bindModeButtons(controller) {
    const placeBtn = document.getElementById('placeModeBtn');
    const moveBtn = document.getElementById('moveModeBtn');
    
    if (placeBtn) {
        placeBtn.onclick = () => {
            console.log('Switch to place mode');
            controller.mode = 'place';
            placeBtn.classList.add('active');
            moveBtn?.classList.remove('active');
            controller._updatePlaceHint();
        };
    }
    
    if (moveBtn) {
        moveBtn.onclick = () => {
            console.log('Switch to move mode');
            controller.mode = 'move';
            moveBtn.classList.add('active');
            placeBtn?.classList.remove('active');
            controller._updatePlaceHint();
        };
    }
}

function bindDecalControls(controller) {
    const controls = {
        width: document.getElementById('decalWidth'),
        height: document.getElementById('decalHeight'),
        rotation: document.getElementById('decalRotation'),
        zOffset: document.getElementById('decalZOffset'),
        forceOutward: document.getElementById('forceOutward'),
        depthWrite: document.getElementById('decalDepthWriteOff')
    };
    
    if (controls.width) {
        controls.width.oninput = (e) => {
            console.log('Decal width:', e.target.value);
            controller.decalSize.w = controller._num(e.target.value, controller.decalSize.w);
        };
    }
    
    if (controls.height) {
        controls.height.oninput = (e) => {
            console.log('Decal height:', e.target.value);
            controller.decalSize.h = controller._num(e.target.value, controller.decalSize.h);
        };
    }
    
    if (controls.rotation) {
        controls.rotation.oninput = (e) => {
            console.log('Decal rotation:', e.target.value);
            controller.decalAngleDeg = controller._num(e.target.value, controller.decalAngleDeg);
        };
    }
    
    if (controls.zOffset) {
        controls.zOffset.oninput = (e) => {
            console.log('Decal Z-offset:', e.target.value);
            controller.decalZOffset = controller._num(e.target.value, controller.decalZOffset);
        };
    }
    
    if (controls.forceOutward) {
        controls.forceOutward.onchange = (e) => {
            console.log('Force outward:', e.target.checked);
            controller.forceOutward = e.target.checked;
        };
    }
    
    if (controls.depthWrite) {
        controls.depthWrite.onchange = (e) => {
            console.log('Depth write off:', e.target.checked);
            controller.depthWriteOff = e.target.checked;
        };
    }
}

function bindActionButtons(controller) {
    const buttons = {
        undo: document.getElementById('undoBtn'),
        reset: document.getElementById('resetBtn'),
        save: document.getElementById('saveDesignBtn'),
        saveMulti: document.getElementById('saveDesignMultiBtn'),
        export: document.getElementById('export4KBtn')
    };
    
    if (buttons.undo) {
        buttons.undo.onclick = () => {
            console.log('Undo last action');
            const lastElement = controller.createdElements[controller.createdElements.length - 1];
            if (lastElement) {
                lastElement.dispose();
                controller.createdElements.pop();
                controller.selectMesh(null);
            }
        };
    }
    
    if (buttons.reset) {
        buttons.reset.onclick = () => {
            console.log('Reset design');
            controller.resetDesign();
        };
    }
    
    if (buttons.save) {
        buttons.save.onclick = async () => {
            console.log('Save design');
            try {
                buttons.save.disabled = true;
                buttons.save.textContent = 'Saving...';
                const designData = controller.exportDesignData();
                await controller.api.saveDesign({ width: 3840, height: 2160, designData });
            } catch (err) {
                console.error('Save failed:', err);
                alert('Failed to save: ' + err.message);
            } finally {
                buttons.save.disabled = false;
                buttons.save.textContent = 'Save & Add to Cart';
            }
        };
    }
    
    if (buttons.export) {
        buttons.export.onclick = async () => {
            console.log('Export 4K');
            try {
                buttons.export.disabled = true;
                buttons.export.textContent = 'Exporting...';
                await controller.exportScreenshot(3840, 2160);
            } catch (err) {
                console.error('Export failed:', err);
                alert('Failed to export: ' + err.message);
            } finally {
                buttons.export.disabled = false;
                buttons.export.textContent = 'Export 4K';
            }
        };
    }
}

function bindModelButtons(controller) {
    const modelButtons = {
        tshirt: document.getElementById('loadTshirtBtn'),
        hoodie: document.getElementById('loadHoodieBtn'),
        shirt: document.getElementById('loadShirtBtn')
    };

    const setActiveButton = (activeBtn) => {
        Object.values(modelButtons).forEach(btn => {
            if (btn) btn.classList.remove('active');
        });
        if (activeBtn) activeBtn.classList.add('active');
    };

    const loadWithController = async (type, button) => {
        console.log(`Loading ${type} model via controller`);
        setActiveButton(button);
        try {
            await controller._loadApparel(type);
        } catch (err) {
            console.error(`Failed to load ${type}:`, err);
            alert(`Failed to load ${type.charAt(0).toUpperCase() + type.slice(1)} model`);
        }
    };

    if (modelButtons.tshirt) {
        modelButtons.tshirt.onclick = () => loadWithController('tshirt', modelButtons.tshirt);
    }

    if (modelButtons.hoodie) {
        modelButtons.hoodie.onclick = () => loadWithController('hoodie', modelButtons.hoodie);
    }

    if (modelButtons.shirt) {
        modelButtons.shirt.onclick = () => loadWithController('shirt', modelButtons.shirt);
    }

}

function bindColorControls(controller) {
    const color1 = document.getElementById('baseColor1');
    const color2 = document.getElementById('baseColor2');
    
    async function updateColors() {
        const c1 = color1?.value || '#ffffff';
        const c2 = color2?.value || '#cccccc';
        console.log('Update colors:', c1, c2);
        controller.applySplitBaseColors(c1, c2);
    }
    
    if (color1) {
        color1.oninput = updateColors;
    }
    
    if (color2) {
        color2.oninput = updateColors;
    }
    
    // Set initial colors
    updateColors();
}