@php
    $id = $id ?? null;
    $active = $active ?? null;
@endphp
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="list-group list-group-flush rounded">
            <a href="{{ $id ? route('admin.factories.business-information', $id) : '#' }}" @if($active === 'business')
            aria-current="page" @endif
                class="list-group-item list-group-item-action d-flex align-items-center py-3 {{ $active === 'business' ? 'active bg-primary text-white' : 'text-dark' }}">
                <i class="mdi mdi-factory me-2 fs-5"></i>
                <span class="fw-semibold">{{ __('Business Information') }}</span>
            </a>
            <a href="{{ $id ? route('admin.factories.branding', $id) : '#' }}" @if($active === 'branding')
            aria-current="page" @endif
                class="list-group-item list-group-item-action d-flex align-items-center py-3 {{ $active === 'branding' ? 'active bg-primary text-white' : 'text-dark' }}">
                <i class="mdi mdi-tag-multiple me-2 fs-5"></i>
                <span class="fw-semibold">{{ __('Branding') }}</span>
            </a>
        </div>
    </div>
</div>