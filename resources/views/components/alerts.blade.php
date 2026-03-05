@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="mdi mdi-check-circle-outline me-2 fs-5"></i>
        <strong>{{ session('success') }}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="mdi mdi-alert-circle-outline me-2 fs-5"></i>
        <strong>{{ session('error') }}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="mdi mdi-information-outline me-2 fs-5"></i>
        <strong>{{ session('info') }}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="mdi mdi-alert-outline me-2 fs-5"></i>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li><strong>{{ $error }}</strong></li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
