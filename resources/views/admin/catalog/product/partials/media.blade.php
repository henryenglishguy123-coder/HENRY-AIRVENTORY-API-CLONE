<div class="card mb-4">
    <div class="card-header pb-0">
        <h6>{{ __('Media Gallery') }}</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="dropzone" id="image_gallery">
                <div class="dz-message">
                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                    <p class="text-sm text-muted mb-0">{{ __('Drop images here or click to upload') }}</p>
                </div>
            </div>
            <div class="progress mt-2 d-none" id="global-upload-progress" style="height: 4px;">
                <div class="progress-bar bg-info" id="global-upload-bar" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div id="sortable-container" class="row g-2 sortable"></div>
        <input type="hidden" name="gallery" id="gallery_input" value="{{ old('gallery') }}">
    </div>
</div>