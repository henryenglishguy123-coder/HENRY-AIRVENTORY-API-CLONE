document.addEventListener('DOMContentLoaded', () => {

    const CONFIG = window.DesignTemplateConfig;
    if (!CONFIG) {
        console.error('DesignTemplateConfig not found');
        return;
    }

    /* ===============================
     * UI REFERENCES
     * =============================== */
    const UI = {
        tabs: document.getElementById('layerTabs'),
        contents: document.getElementById('layerContents'),
        addBtn: document.getElementById('addLayerButton'),
        saveBtn: document.getElementById('saveProductButton'),
        spinner: document.getElementById('saveButtonSpinner'),
        saveText: document.getElementById('saveButtonText'),
        name: document.getElementById('template_name'),
        status: document.getElementById('template_status'),
    };

    const Builder = {
        layers: {},
        count: 0
    };

    /* ===============================
     * HELPERS
     * =============================== */
    function showFieldError(el, message) {
        if (!el) return;
        el.classList.add('is-invalid');
        el.parentElement.querySelector('.invalid-feedback').textContent = message;
    }

    function clearFieldErrors() {
        document.querySelectorAll('.is-invalid')
            .forEach(el => el.classList.remove('is-invalid'));
    }

    async function api(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'X-CSRF-TOKEN': CONFIG.csrf,
                'Accept': 'application/json',
                ...(options.headers || {})
            },
            ...options
        });
        const data = await res.json();
        return { ok: res.ok, status: res.status, data };
    }

    /* ===============================
     * ADD NEW LAYER
     * =============================== */
    function addLayer() {
        Builder.count++;
        const id = `layer${Builder.count}`;

        UI.tabs.insertAdjacentHTML('beforeend', `
            <li class="nav-item">
                <a class="btn ${Builder.count === 1 ? 'btn-primary' : 'btn-outline-secondary'}
                   d-flex align-items-center gap-2"
                   data-bs-toggle="tab"
                   href="#${id}"
                   data-layer-id="${id}">
                    <span class="layer-title">${CONFIG.messages.layer} ${Builder.count}</span>
                    <i class="mdi mdi-pencil edit-layer"></i>
                    <i class="mdi mdi-close remove-layer"></i>
                </a>
            </li>
        `);

        UI.contents.insertAdjacentHTML('beforeend', `
            <div class="tab-pane fade ${Builder.count === 1 ? 'show active' : ''}" id="${id}">
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">${CONFIG.messages.upload_svg}</label>
                        <div class="position-relative">
                            <input type="file" class="form-control image-upload" accept=".svg">
                            <div class="upload-loader d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                ${CONFIG.messages.uploading}
                            </div>
                        </div>
                    </div>
                <div class="col-md-6 d-flex align-items-end">
                <div class="form-check mt-4">
                    <input
                        class="form-check-input is-neck-layer"
                        type="checkbox"
                        id="${id}_is_neck_layer"
                    >
                    <label class="form-check-label fw-semibold" for="${id}_is_neck_layer">
                        Is Neck Layer
                    </label>
                </div>
            </div>
        </div>
                <div class="canvas-wrapper d-flex justify-content-center">
                    <canvas width="500" height="500"></canvas>
                </div>
            </div>
        `);

        initCanvas(id);
        activateTabs();
    }

    /* ===============================
     * INIT CANVAS
     * =============================== */
    function initCanvas(layerId) {
        const wrapper = document.getElementById(layerId);
        const canvasEl = wrapper.querySelector('canvas');
        const upload = wrapper.querySelector('.image-upload');
        const loader = wrapper.querySelector('.upload-loader');

        const canvas = new fabric.Canvas(canvasEl, { selection: false });

        const rect = new fabric.Rect({
            width: 180,
            height: 220,
            fill: 'transparent',
            stroke: '#0d6efd',
            strokeWidth: 1.5,
            centeredScaling: true,
            lockUniScaling: true,
        });

        canvas.add(rect);
        rect.center();
        canvas.setActiveObject(rect);
        rect.set({
            hasBorders: true,
            hasControls: true,
            hasRotatingPoint: true,
            lockRotation: false,
            centeredRotation: true,
            cornerStyle: 'circle',
            cornerSize: 8,
            transparentCorners: false,
        });
        rect.setControlsVisibility({
            mt: false,
            mb: false,
            ml: false,
            mr: false,
            tl: true,
            tr: true,
            bl: true,
            br: true,
            mtr: true,
        });
        const neckCheckbox = wrapper.querySelector('.is-neck-layer');

        function keepInside() {
            const b = rect.getBoundingRect(true, true);
            let left = rect.left;
            let top = rect.top;

            if (b.left < 0) left -= b.left;
            if (b.top < 0) top -= b.top;
            if (b.left + b.width > canvas.width) {
                left -= (b.left + b.width - canvas.width);
            }
            if (b.top + b.height > canvas.height) {
                top -= (b.top + b.height - canvas.height);
            }

            rect.set({ left, top });
            rect.setCoords();
        }

        rect.on('moving', () => { keepInside(); canvas.renderAll(); });
        rect.on('scaling', () => { keepInside(); canvas.renderAll(); });

        upload.addEventListener('change', async e => {
            const file = e.target.files[0];
            if (!file || file.type !== 'image/svg+xml') {
                toastr.error(CONFIG.messages.only_svg);
                upload.value = '';
                return;
            }

            upload.disabled = true;
            loader.classList.remove('d-none');

            const formData = new FormData();
            formData.append('file', file);

            const { ok, data } = await api(CONFIG.routes.upload, {
                method: 'POST',
                body: formData
            });

            upload.disabled = false;
            loader.classList.add('d-none');

            if (!ok || !data.success) {
                toastr.error(CONFIG.messages.upload_failed);
                return;
            }

            fabric.Image.fromURL(data.url, img => {
                const scale = Math.min(
                    canvas.width / img.width,
                    canvas.height / img.height
                );
                img.set({
                    scaleX: scale,
                    scaleY: scale,
                    selectable: false,
                    evented: false
                });
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            }, { crossOrigin: 'anonymous' });

            Builder.layers[layerId].backgroundUrl = data.url;
        });

        Builder.layers[layerId] = {
            canvas,
            editableArea: rect,
            backgroundUrl: null,
            isNeckLayer: false,
        };
        applyRectScaling(false);
        function unsetOtherNeckLayers(currentLayerId) {
            Object.entries(Builder.layers).forEach(([id, layer]) => {
                if (id !== currentLayerId && layer.isNeckLayer) {
                    layer.isNeckLayer = false;

                    const wrapper = document.getElementById(id);
                    const checkbox = wrapper?.querySelector('.is-neck-layer');
                    if (checkbox) checkbox.checked = false;

                    layer.editableArea.set({
                        lockUniScaling: true,
                        centeredScaling: true,
                    });

                    layer.canvas.renderAll();
                }
            });
        }

        neckCheckbox.addEventListener('change', () => {
            if (neckCheckbox.checked) {
                unsetOtherNeckLayers(layerId);
                Builder.layers[layerId].isNeckLayer = true;
                applyRectScaling(true);
            } else {
                Builder.layers[layerId].isNeckLayer = false;
                applyRectScaling(false);
            }
        });
        function applyRectScaling(isNeck) {
            if (isNeck) {
                rect.setControlsVisibility({
                    mt: true,
                    mb: true,
                    ml: true,
                    mr: true,
                    tl: true,
                    tr: true,
                    bl: true,
                    br: true,
                    mtr: false,
                });

            } else {
                rect.set({
                    scaleX: 1,
                    scaleY: 1,
                    width: 180,
                    height: 220,
                });
                rect.setControlsVisibility({
            mt: false,
            mb: false,
            ml: false,
            mr: false,
            tl: true,
            tr: true,
            bl: true,
            br: true,
            mtr: true,
        });
            }
            rect.setCoords();
            canvas.renderAll();
        }

    }

    /* ===============================
     * TAB STYLING
     * =============================== */
    function activateTabs() {
        UI.tabs.querySelectorAll('a').forEach(tab => {
            tab.onclick = () => {
                UI.tabs.querySelectorAll('a')
                    .forEach(t => t.classList.replace('btn-primary', 'btn-outline-secondary'));
                tab.classList.replace('btn-outline-secondary', 'btn-primary');
            };
        });
    }

    /* ===============================
     * EDIT / REMOVE LAYER
     * =============================== */
    UI.tabs.addEventListener('click', e => {

        const editBtn = e.target.closest('.edit-layer');
        if (editBtn) {
            e.preventDefault();
            e.stopPropagation();

            const tab = editBtn.closest('a');
            const titleEl = tab.querySelector('.layer-title');

            Swal.fire({
                title: CONFIG.messages.edit_layer_title,
                input: 'text',
                inputValue: titleEl.innerText,
                showCancelButton: true,
                confirmButtonText: CONFIG.messages.update,
                inputValidator: value => {
                    if (!value) return CONFIG.messages.layer_name_required;
                }
            }).then(res => {
                if (res.isConfirmed) titleEl.innerText = res.value;
            });
            return;
        }

        const removeBtn = e.target.closest('.remove-layer');
        if (!removeBtn) return;

        e.preventDefault();
        e.stopPropagation();

        if (Object.keys(Builder.layers).length === 1) {
            toastr.warning(CONFIG.messages.min_one_layer);
            return;
        }

        const tab = removeBtn.closest('a');
        const layerId = tab.dataset.layerId;

        Swal.fire({
            title: CONFIG.messages.remove_layer_title,
            text: CONFIG.messages.remove_layer_text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: CONFIG.messages.remove
        }).then(res => {
            if (res.isConfirmed) removeLayer(layerId);
        });
    });

    function removeLayer(layerId) {
        const layer = Builder.layers[layerId];
        if (!layer) return;

        layer.canvas.dispose();
        document.getElementById(layerId)?.remove();
        UI.tabs.querySelector(`[data-layer-id="${layerId}"]`)
            ?.closest('li')?.remove();

        delete Builder.layers[layerId];

        const firstTab = UI.tabs.querySelector('a');
        if (firstTab) firstTab.click();
    }

    /* ===============================
     * SAVE TEMPLATE
     * =============================== */
    UI.saveBtn.onclick = async () => {
        clearFieldErrors();
        let hasError = false;

        if (!UI.name.value.trim()) {
            showFieldError(UI.name, CONFIG.messages.name_required);
            hasError = true;
        }

        if (!Object.keys(Builder.layers).length) {
            toastr.error(CONFIG.messages.min_one_layer);
            hasError = true;
        }

        const layers = Object.entries(Builder.layers).map(([id, l]) => {
            if (!l.backgroundUrl) {
                toastr.error(CONFIG.messages.layer_missing_svg.replace(':layer', id));
                hasError = true;
            }
            const tab = UI.tabs.querySelector(`[data-layer-id="${id}"]`);
            const r = l.editableArea;
            return {
                layerId: id,
                layerName: tab.querySelector('.layer-title').innerText,
                is_neck_layer: !!l.isNeckLayer,
                image: l.backgroundUrl,
                coordinates: {
                    left: r.left,
                    top: r.top,
                    width: r.width,
                    height: r.height,
                    scaleX: r.scaleX,
                    scaleY: r.scaleY,
                    angle: r.angle,
                }
            };
        });

        if (hasError) return;

        UI.saveBtn.disabled = true;
        UI.spinner.style.display = 'inline-block';
        UI.saveText.textContent = CONFIG.messages.saving;

        const { ok, status, data } = await api(CONFIG.routes.store, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CONFIG.csrf, 'Accept': 'application/json' },
            body: JSON.stringify({
                templateName: UI.name.value,
                templateStatus: UI.status.value,
                layers
            })
        });

        UI.saveBtn.disabled = false;
        UI.spinner.style.display = 'none';
        UI.saveText.textContent = CONFIG.messages.save;

        if (!ok) {
            if (status === 422 && data.errors) {
                Object.values(data.errors).flat()
                    .forEach(msg => toastr.error(msg));
            } else {
                toastr.error(data.message || CONFIG.messages.save_failed);
            }
            return;
        }

        toastr.success(data.message);
        setTimeout(() => {
            window.location.href = CONFIG.routes.index;
        }, 800);
    };


    UI.addBtn.onclick = addLayer;
    addLayer();
});
