document.addEventListener("DOMContentLoaded", () => {
    const state = {
        apiUrl: window?.productVariationUrl ?? null,
        uploadUrl: window?.uploadLayerImageUrl ?? null,
        csrf: window?.csrfToken,
        loader: document.querySelector('#layer-loading'),
        wrapper: document.querySelector('#layers-wrapper'),
        existing: {},
        baseUrl: window?.baseImageUrl ?? "",
    };
    init();
    async function init() {
        if (!state.apiUrl || !state.uploadUrl) {
            displayError("Configuration error: missing API URLs for product variations.");
            return;
        }
        collectExistingData();
        try {
            const colors = await fetchColors();
            renderUI(colors);
            toggleLoader(true);
            bindGlobalEvents();
        } catch (error) {
            console.error(error);
            displayError("Unable to load product configuration. Please refresh and try again.");
        }
    }
    function collectExistingData() {
        document.querySelectorAll('.layer-block').forEach(block => {
            const id = block.dataset.layerId;
            let parsed = [];
            try {
                parsed = JSON.parse(block.dataset.existing || "[]");
            } catch { parsed = []; }
            state.existing[id] = Array.isArray(parsed) ? parsed : Object.values(parsed);
        });
    }

    async function fetchColors() {
        const res = await fetch(state.apiUrl, {
            headers: {
                Accept: "application/json",
            },
        });
        if (!res.ok) throw new Error("Failed to load variations");
        const json = await res.json();
        const list = json?.data?.colors ?? [];
        if (!Array.isArray(list) || list.length === 0) {
            return [];
        }
        return list;
    }
    function renderUI(colors) {
        if (!colors || colors.length === 0) {
            displayError("No color variations found for this product.");
            return;
        }
        document.querySelectorAll('.layer-options').forEach(container => {
            const layerId = container.dataset.layerId;
            const existingImages = state.existing[layerId] || [];
            colors.forEach(color => {
                const match = existingImages.find(img => String(img.catalog_attribute_option_id) === String(color.id));
                container.insertAdjacentHTML("beforeend", createDropzoneHTML(layerId, color, match));
            });
        });
    }
    function createDropzoneHTML(layerId, color, existing) {
        const hasImage = !!existing?.image_path;
        const imgUrl = hasImage ? `${state.baseUrl}${existing.image_path}` : '';
        return `
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <div class="upload-zone-wrapper">
                    <div class="upload-zone ${hasImage ? 'has-image' : ''}">
                        <span class="zone-label text-truncate" title="${color.label}">${color.label}</span>
                        <div class="preview-area">
                            ${hasImage
                ? `<img src="${imgUrl}" alt="${color.label}">`
                : `<div class="d-flex flex-column align-items-center">
                                     <i class="fas fa-cloud-upload-alt placeholder-icon"></i>
                                     <span class="placeholder-text">Click to Upload</span>
                                   </div>`
            }
                        </div>
                        <div class="status-indicator text-success">
                            <i class="fas fa-check-circle"></i> Saved
                        </div>
                        <input type="file" 
                            class="layer-file-input" 
                            data-layer="${layerId}" 
                            data-option="${color.id}" 
                            accept="image/*">
                    </div>
                </div>
            </div>
        `;
    }
    function bindGlobalEvents() {
        document.addEventListener("change", e => {
            if (e.target.matches('.layer-file-input')) {
                handleFileSelect(e.target, e.target.files[0]);
            }
        });
        document.querySelectorAll('.upload-zone').forEach(zone => {
            zone.addEventListener('dragover', e => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });
            zone.addEventListener('dragleave', e => {
                e.preventDefault();
                zone.classList.remove('drag-over');
            });
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                const input = zone.querySelector('.layer-file-input');
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files; // Assign to input
                    handleFileSelect(input, e.dataTransfer.files[0]);
                }
            });
        });
    }
    async function handleFileSelect(input, file) {
        if (!file) return;
        const zone = input.closest('.upload-zone');
        const previewArea = zone.querySelector('.preview-area');
        const badge = zone.querySelector('.status-indicator');
        const validCheck = await validateImage(file);
        if (!validCheck.valid) {
            toastr.error(validCheck.message);
            input.value = "";
            return;
        }
        previewArea.style.opacity = '0.5';
        badge.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        badge.className = 'status-indicator text-primary d-block';
        const resp = await uploadFile(input.dataset.layer, input.dataset.option, file);
        if (resp?.success) {
            const newSrc = `${state.baseUrl}${resp.path}`;
            previewArea.innerHTML = `<img src="${newSrc}" />`;
            previewArea.style.opacity = '1';
            zone.classList.add('has-image');
            const successMsg = resp.message || "Layer updated successfully";
            badge.innerHTML = '<i class="fas fa-check"></i> Saved';
            badge.className = 'status-indicator text-success d-block';
            toastr.success(successMsg);
            setTimeout(() => { badge.style.display = 'none'; }, 2500);
        } else {
            previewArea.style.opacity = '1';
            badge.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
            badge.className = 'status-indicator text-danger d-block';
            const errorMsg = resp?.message || "Upload failed";
            toastr.error(errorMsg);
            input.value = "";
        }
    }
    function validateImage(file) {
        return new Promise(resolve => {
            if (!file.type.startsWith('image/')) {
                resolve({ valid: false, message: "File is not an image" });
                return;
            }
            const img = new Image();
            img.onload = () => {
                if (img.width !== img.height) {
                    resolve({ valid: false, message: "Image must be a 1:1 square" });
                } else {
                    resolve({ valid: true });
                }
            };
            img.src = URL.createObjectURL(file);
        });
    }
    async function uploadFile(layerId, optionId, file) {
        const fd = new FormData();
        fd.append("layer_id", layerId);
        fd.append("option_id", optionId);
        fd.append("image", file);
        try {
            const res = await fetch(state.uploadUrl, {
                method: "POST",
                headers: { "X-CSRF-TOKEN": state.csrf },
                body: fd
            });
            return await res.json();
        } catch (e) {
            console.error(e);
            return { success: false };
        }
    }
    function toggleLoader(showContent) {
        state.loader.classList.toggle('d-none', showContent);
        state.wrapper.classList.toggle('d-none', !showContent);
    }
    function displayError(msg) {
        state.loader.innerHTML = `<div class="text-danger fw-bold">${msg}</div>`;
    }
});
