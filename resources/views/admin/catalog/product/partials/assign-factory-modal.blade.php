<!-- Assign Factory Modal -->
<div class="modal fade" id="assignFactoryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
        <div class="modal-content shadow h-100 d-flex flex-column">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    {{ __('Assign SKU') }} : <span id="assignSku" class="text-primary"></span> {{ __('to Factories') }}
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body flex-grow-1 overflow-auto">
                <form id="assignFactoryForm">
                    <!-- Factory Select -->
                    <div class="mb-3">
                        <label class="form-label required fw-semibold">
                            {{ __('Select Factory') }}
                        </label>
                        <select id="factory_ids" name="factory_ids[]" class="form-select" multiple>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Factory collapse controls">
                            <button type="button" class="btn btn-outline-secondary" id="factoryExpandAll">
                                {{ __('Expand All') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="factoryCollapseAll">
                                {{ __('Collapse All') }}
                            </button>
                        </div>
                    </div>

                    <!-- Factory Cards -->
                    <div id="factory-assignment-preview" class="mt-4"></div>

                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="assignFactorySubmit">
                    <span class="btn-text">
                        <i class="fas fa-check me-1"></i> {{ __('Assign') }}
                    </span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #assignFactoryModal .modal-body {
        scroll-behavior: smooth;
    }

    #assignFactoryModal .factory-card-body {
        overflow: hidden;
        transition: max-height 0.25s ease, opacity 0.25s ease, padding-top 0.25s ease, padding-bottom 0.25s ease;
        max-height: 2000px;
        opacity: 1;
    }

    #assignFactoryModal .factory-card-body.factory-body-collapsed {
        max-height: 0;
        opacity: 0;
        padding-top: 0;
        padding-bottom: 0;
    }
</style>