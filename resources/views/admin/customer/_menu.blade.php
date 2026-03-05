<ul class="list-group shadow-sm rounded overflow-hidden">

    <li class="list-group-item p-0 @if (request()->routeIs('admin.customer.show')) active bg-primary border-0 @endif">
        <a href="{{ route('admin.customer.show', $id) }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none @if (request()->routeIs('admin.customer.show')) text-white @else text-dark @endif">
            <i class="mdi mdi-account-outline fs-5"></i><span class="fw-semibold">{{ __('Basic Information') }}</span>
        </a>
    </li>
    <li class="list-group-item p-0 @if (request()->routeIs('admin.customer.wallet')) active bg-primary border-0 @endif">
        <a href="{{ route('admin.customer.wallet', $id) }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none @if (request()->routeIs('admin.customer.wallet')) text-white @else text-dark @endif">
            <i class="mdi mdi-wallet fs-5"></i><span class="fw-semibold">{{ __('Wallet') }}</span>
        </a>
    </li>
    <li class="list-group-item p-0 @if (request()->routeIs('admin.customer.stores')) active bg-primary border-0 @endif">
        <a href="{{ route('admin.customer.stores', $id) }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none @if (request()->routeIs('admin.customer.stores')) text-white @else text-dark @endif">
            <i class="mdi mdi-store fs-5"></i><span class="fw-semibold">{{ __('Stores') }}</span>
        </a>
    </li>
    <li class="list-group-item p-0 @if (request()->routeIs('admin.customer.templates')) active bg-primary border-0 @endif">
        <a href="{{ route('admin.customer.templates', $id) }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none @if (request()->routeIs('admin.customer.templates')) text-white @else text-dark @endif">
            <i class="mdi mdi-book-open-page-variant fs-5"></i><span class="fw-semibold">{{ __('Templates') }}</span>
        </a>
    </li>

</ul>
