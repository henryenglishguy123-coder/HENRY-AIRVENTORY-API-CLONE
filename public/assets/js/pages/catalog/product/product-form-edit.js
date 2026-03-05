class ProductEditManager extends ProductManager {
    showSuccessToast(message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: 'success',
            title: message
        });
    }

    initVariantSystem() {
        const tbody = document.getElementById('variants-table-body');
        if (!tbody) return;

        // Image Upload
        tbody.addEventListener('change', async (e) => {
            if (e.target.classList.contains('variant-file-input')) {
                const file = e.target.files[0];
                if (!file) return;

                const variantId = e.target.dataset.variantId;
                if (!variantId) return;

                if (!file.type.startsWith('image/')) {
                    Swal.fire('Invalid File', 'Only image files are allowed.', 'error');
                    e.target.value = '';
                    return;
                }

                // Get or Create Overlay
                const label = e.target.closest('label');
                let overlay = label.querySelector('.variant-upload-overlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.className = 'variant-upload-overlay';
                    overlay.innerHTML = `
                        <div class="variant-spinner"></div>
                        <i class="fas fa-check-circle variant-success-icon"></i>
                    `;
                    label.appendChild(overlay);
                }

                // Show Loading
                overlay.classList.remove('success');
                overlay.classList.add('active');

                // Optimistic UI - Show Image Immediately
                const reader = new FileReader();
                let originalSrc = null;
                const img = label.querySelector('img');

                if (img) originalSrc = img.src;

                reader.onload = (ev) => {
                    const img = label.querySelector('img');
                    if (img) {
                        img.src = ev.target.result;
                        img.classList.add('loaded'); // Ensure it's visible
                    }
                    const skel = label.querySelector('.variant-skeleton');
                    if (skel) skel.classList.add('hidden');
                };
                reader.readAsDataURL(file);

                const formData = new FormData();
                formData.append('variant_id', variantId);
                formData.append('image', file);
                formData.append('_token', this.config.csrf);

                try {
                    // Simulate delay for "better feel" (optional, but requested)
                    // await new Promise(r => setTimeout(r, 500)); 

                    const response = await fetch(this.config.urls.variantImage, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        try {
                            const errData = await response.json();
                            throw new Error(errData.message || 'Upload failed');
                        } catch (e) {
                            throw new Error('Upload failed');
                        }
                    }

                    const data = await response.json();

                    if (data.success) {
                        // Image already updated optimistically, just ensure correct URL from server
                        // const img = label.querySelector('img');
                        // img.src = data.image_url; 

                        // Show Success State
                        overlay.classList.add('success');

                        // Hide after delay
                        setTimeout(() => {
                            overlay.classList.remove('active', 'success');
                        }, 1500);

                        this.showSuccessToast(data.message || 'Image updated');
                    } else {
                        throw new Error(data.message || 'Upload failed');
                    }
                } catch (error) {
                    console.error('Variant image upload error:', error);
                    overlay.classList.remove('active'); // Hide immediately on error

                    // Revert image
                    const img = label.querySelector('img');
                    if (img && originalSrc) {
                        img.src = originalSrc;
                        // If it was hidden/skeleton before, might need to revert classes too.
                        // For now, just restoring src is key.
                        if (!img.complete || !img.naturalWidth) { // simplified check
                            img.classList.remove('loaded');
                            const skel = label.querySelector('.variant-skeleton');
                            if (skel) skel.classList.remove('hidden');
                        }
                    }

                    Swal.fire('Error', error.message, 'error');
                } finally {
                    e.target.value = '';
                }
            }
        });

        // Delete Variant
        tbody.addEventListener('click', async (e) => {
            const btn = e.target.closest('.remove-variant');
            if (!btn) return;

            const variantId = btn.dataset.variantId;
            if (!variantId) return;

            const result = await Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!'
            });

            if (result.isConfirmed) {
                try {
                    const url = this.config.urls.variantDelete.replace(':id', variantId);
                    const response = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': this.config.csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Delete failed');
                    }

                    const data = await response.json();

                    if (data.success) {
                        btn.closest('tr').remove();
                        this.showSuccessToast(data.message || 'Variant deleted successfully');
                    } else {
                        throw new Error(data.message || 'Delete failed');
                    }
                } catch (error) {
                    console.error('Variant delete error:', error);
                    Swal.fire('Error', error.message, 'error');
                }
            }
        });
    }
}
