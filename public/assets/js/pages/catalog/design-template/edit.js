document.addEventListener('DOMContentLoaded', () => {
    const CONFIG = window.DesignTemplateConfig;
    if (!CONFIG) return console.error('DesignTemplateConfig missing');

    /* ===============================
     * STATE
     * =============================== */
    const Builder = {
        layers: {},
        count: 0,
    };

    /* ===============================
     * UI
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
        const layerDomId = tab.dataset.layerId;
        const layer = Builder.layers[layerDomId];
        Swal.fire({
            title: CONFIG.messages.remove_layer_title,
            text: CONFIG.messages.remove_layer_text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: CONFIG.messages.remove
        }).then(async res => {
            if (!res.isConfirmed) return;
            if (layer?.actualId) {
                const { ok, data } = await api(
                    CONFIG.routes.deleteLayer.replace(':id', layer.actualId),
                    { method: 'DELETE', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CONFIG.csrf } }
                );
                if (!ok || !data.success) {
                    toastr.error(data?.message || CONFIG.messages.remove_failed);
                    return;
                }
            }
            removeLayer(layerDomId);
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
     * HELPERS
     * =============================== */
    const api = async (url, options = {}) => {
        const res = await fetch(url, {
            headers: {
                'X-CSRF-TOKEN': CONFIG.csrf,
                'Accept': 'application/json',
                ...(options.headers || {}),
            },
            ...options,
        });

        return { ok: res.ok, status: res.status, data: await res.json() };
    };

    /* ===============================
     * NECK LAYER LOGIC
     * =============================== */
    const unsetOtherNeckLayers = currentId => {
        Object.entries(Builder.layers).forEach(([id, layer]) => {
            if (id !== currentId && layer.isNeckLayer) {
                layer.isNeckLayer = false;
                const cb = document.querySelector(`#${id} .is-neck-layer`);
                if (cb) cb.checked = false;
                applyNeckMode(layer.editableArea, layer.canvas, false);
            }
        });
    };

    const applyNeckMode = (rect, canvas, isNeck) => {
        rect.lockUniScaling = !isNeck;

        rect.setControlsVisibility(
            isNeck
                ? { mt: true, mb: true, ml: true, mr: true, tl: true, tr: true, bl: true, br: true, mtr: false }
                : { mt: false, mb: false, ml: false, mr: false, tl: true, tr: true, bl: true, br: true, mtr: true }
        );
        rect.setCoords();
        canvas.renderAll();
    };

    /* ===============================
     * CANVAS INIT (SINGLE SOURCE)
     * =============================== */
    const initLayerCanvas = (layerId, data = null) => {
        const wrapper = document.getElementById(layerId);
        const canvasEl = wrapper.querySelector('canvas');
        const upload = wrapper.querySelector('.image-upload');
        const loader = wrapper.querySelector('.upload-loader');
        const checkbox = wrapper.querySelector('.is-neck-layer');

        const canvas = new fabric.Canvas(canvasEl, { selection: false });

        const rect = new fabric.Rect({
            left: data?.coordinates?.left ?? 0,
            top: data?.coordinates?.top ?? 0,
            width: data?.coordinates?.width ?? 180,
            height: data?.coordinates?.height ?? 220,
            scaleX: data?.coordinates?.scaleX ?? 1,
            scaleY: data?.coordinates?.scaleY ?? 1,
            angle: data?.coordinates?.angle ?? 0,
            fill: 'transparent',
            stroke: '#0d6efd',
            strokeWidth: 1.5,
            centeredScaling: true,
            cornerStyle: 'circle',
            cornerSize: 8,
        });

        canvas.add(rect);
        canvas.setActiveObject(rect);

        const keepInside = () => {
            const b = rect.getBoundingRect(true);
            rect.set({
                left: Math.min(Math.max(rect.left, -b.left), canvas.width - b.width),
                top: Math.min(Math.max(rect.top, -b.top), canvas.height - b.height),
            });
            rect.setCoords();
        };

        rect.on('moving', keepInside);
        rect.on('scaling', keepInside);

        if (data?.image) {
            fabric.Image.fromURL(data.image, img => {
                const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
                img.set({ scaleX: scale, scaleY: scale, selectable: false, evented: false });
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            }, { crossOrigin: 'anonymous' });
        }

        checkbox.checked = !!data?.is_neck_layer;
        applyNeckMode(rect, canvas, checkbox.checked);

        checkbox.addEventListener('change', () => {
            if (checkbox.checked) unsetOtherNeckLayers(layerId);
            Builder.layers[layerId].isNeckLayer = checkbox.checked;
            applyNeckMode(rect, canvas, checkbox.checked);
        });

        upload?.addEventListener('change', async e => {
            const file = e.target.files[0];
            if (!file || !file.name.endsWith('.svg')) return toastr.error(CONFIG.messages.only_svg);
            loader.classList.remove('d-none');
            upload.disabled = true;

            const fd = new FormData();
            fd.append('file', file);

            const { ok, data } = await api(CONFIG.routes.upload, { method: 'POST', body: fd });

            loader.classList.add('d-none');
            upload.disabled = false;

            if (!ok || !data.success) return toastr.error(CONFIG.messages.upload_failed);

            fabric.Image.fromURL(data.url, img => {
                const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
                img.set({ scaleX: scale, scaleY: scale, selectable: false, evented: false });
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            }, { crossOrigin: 'anonymous' });

            Builder.layers[layerId].backgroundUrl = data.url;
        });

        Builder.layers[layerId] = {
            actualId: data?.id ?? null,
            canvas,
            editableArea: rect,
            backgroundUrl: data?.image,
            isNeckLayer: !!data?.is_neck_layer,
        };
    };

    /* ===============================
     * LAYER CREATION
     * =============================== */
    const addLayer = (data = null) => {
        Builder.count++;
        const actualId = data?.id ?? null;
        const domId = actualId
            ? `layer-${actualId}`
            : `layer-new-${Builder.count}`;


        UI.tabs.insertAdjacentHTML('beforeend', `
            <li class="nav-item">
                <a class="btn ${Builder.count === 1 ? 'btn-outline-primary active' : 'btn-outline-primary'}"
                   data-bs-toggle="tab" href="#${domId}" data-layer-id="${domId}">
                    <span class="layer-title">${data?.layer_name ?? `${CONFIG.messages.layer} ${Builder.count}`}</span>
                    <i class="mdi mdi-pencil edit-layer"></i>
                    <i class="mdi mdi-close remove-layer"></i>
                </a>
            </li>
        `);

        UI.contents.insertAdjacentHTML('beforeend', `
            <div class="tab-pane fade ${Builder.count === 1 ? 'show active' : ''}" id="${domId}">
                <div class="row mt-3">
                    <div class="col-md-6">
                        <input type="file" class="form-control image-upload" accept=".svg">
                        <div class="upload-loader d-none">
                            <span class="spinner-border spinner-border-sm"></span>
                            ${CONFIG.messages.uploading}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <input type="checkbox" class="form-check-input is-neck-layer">
                        <label class="form-check-label fw-semibold">Is Neck Layer</label>
                    </div>
                </div>
                <div class="canvas-wrapper d-flex justify-content-center mt-3">
                    <canvas width="500" height="500"></canvas>
                </div>
            </div>
        `);

        initLayerCanvas(domId, data);
    };

    /* ===============================
     * INIT
     * =============================== */
    UI.addBtn.onclick = () => addLayer();

    if (CONFIG.template?.length) {
        CONFIG.template.forEach(layer => addLayer(layer));
    } else {
        addLayer();
    }
    const setSavingState = isSaving => {
        UI.saveBtn.disabled = isSaving;
        UI.spinner.classList.toggle('d-none', !isSaving);
        UI.saveText.textContent = isSaving
            ? CONFIG.messages.saving
            : CONFIG.messages.save;
    };

    /* ===============================
 * SAVE TEMPLATE
 * =============================== */
    UI.saveBtn.addEventListener('click', async () => {
        let hasError = false;

        setSavingState(true);

        try {
            /* ===============================
             * VALIDATION
             * =============================== */
            if (!UI.name.value.trim()) {
                toastr.error(CONFIG.messages.name_required);
                hasError = true;
            }

            if (!Object.keys(Builder.layers).length) {
                toastr.error(CONFIG.messages.min_one_layer);
                hasError = true;
            }

            /* ===============================
             * BUILD PAYLOAD
             * =============================== */
            const layers = Object.entries(Builder.layers).map(([domId, layer]) => {
                if (!layer.backgroundUrl) {
                    toastr.error(
                        CONFIG.messages.layer_missing_svg.replace(':layer', domId)
                    );
                    hasError = true;
                }

                const rect = layer.editableArea;
                const tab = UI.tabs.querySelector(`[data-layer-id="${domId}"]`);

                return {
                    id: layer.actualId ?? null,
                    layerName: tab?.querySelector('.layer-title')?.innerText ?? domId,
                    is_neck_layer: layer.isNeckLayer,
                    image: layer.backgroundUrl.replace(CONFIG.baseUrl, ''),
                    coordinates: {
                        left: rect.left,
                        top: rect.top,
                        width: rect.width,
                        height: rect.height,
                        scaleX: rect.scaleX,
                        scaleY: rect.scaleY,
                        angle: rect.angle,
                    },
                };
            });

            if (hasError) return; // finally WILL run 🔥

            /* ===============================
             * API CALL
             * =============================== */
            const { ok, status, data } = await api(CONFIG.routes.store, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CONFIG.csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    templateName: UI.name.value,
                    templateStatus: UI.status.value,
                    layers,
                }),
            });

            if (!ok) {
                if (status === 422 && data?.errors) {
                    Object.values(data.errors).flat()
                        .forEach(msg => toastr.error(msg));
                } else {
                    toastr.error(data?.message || CONFIG.messages.save_failed);
                }
                return;
            }

            /* ===============================
             * SUCCESS
             * =============================== */
            toastr.success(data.message || CONFIG.messages.saved_successfully);

            setTimeout(() => {
                window.location.href = CONFIG.routes.index;
            }, 800);

        } catch (err) {
            console.error(err);
            toastr.error(CONFIG.messages.save_failed);
        } finally {
            setSavingState(false);
        }
    });


});
