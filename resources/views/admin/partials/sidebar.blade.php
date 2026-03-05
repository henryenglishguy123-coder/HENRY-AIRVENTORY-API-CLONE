<aside class="left-sidebar" data-sidebarbg="skin5">
    <div class="scroll-sidebar">

        {{-- Profile Header --}}
        <header class="nav-item dropdown">
            <a class="nav-link dropdown-toggle pro-pic w-100 d-flex align-items-center justify-content-between gap-2"
                href="#" data-bs-toggle="dropdown">

                <span class="d-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/logo-mini.svg') }}" alt="Logo" class="light-logo invert-image"
                        width="25">
                    <p class="mb-0 text-white store_name m_hide_00">@storeconfig('store_name')</p>
                </span>

                <span class="aro m_hide_00"></span>
            </a>

            <ul class="dropdown-menu dropdown-menu-end user-dd animated">
                <li><a class="dropdown-item d-flex gap-1" href="#"><i class="mdi mdi-settings"></i>
                        {{ __('Edit Profile') }}</a></li>
                <li><a class="dropdown-item d-flex gap-1" href="javascript:void(0);" id="logoutLink"><i
                            class="mdi mdi-logout"></i> {{ __('Logout') }}</a></li>
            </ul>
        </header>

        {{-- Navigation --}}
        <nav class="sidebar-nav">
            <ul id="sidebarnav">

                @foreach ($menus as $menu)
                    @php $active = isMenuActive($menu); @endphp
                    @if ($menu->children->isNotEmpty())
                        <li class="sidebar-item {{ $active ? 'active' : '' }}">
                            <a class="sidebar-link has-arrow {{ $active ? 'active' : '' }}" href="javascript:void(0)">
                                <i class="mdi {{ $menu->icon }}"></i>
                                <span class="hide-menu">{{ __($menu->lang_title ?? $menu->title) }}</span>
                            </a>

                            <ul class="collapse first-level {{ $active ? 'in' : '' }}">
                                @foreach ($menu->children as $child)
                                    @php $childActive = isMenuActive($child); @endphp

                                    <li class="sidebar-item {{ $childActive ? 'active' : '' }}">

                                        @if ($child->children->isNotEmpty())
                                            <a class="sidebar-link has-arrow {{ $childActive ? 'active' : '' }}"
                                                href="javascript:void(0)">
                                                <i class="mdi {{ $child->icon }}"></i>
                                                <span
                                                    class="hide-menu">{{ __($child->lang_title ?? $child->title) }}</span>
                                            </a>

                                            <ul class="collapse second-level {{ $childActive ? 'in' : '' }}">
                                                @foreach ($child->children as $sub)
                                                    <li class="sidebar-item {{ isMenuActive($sub) ? 'active' : '' }}">
                                                        <a class="sidebar-link" href="{{ menuUrl($sub) }}">
                                                            <i class="mdi {{ $sub->icon }}"></i>
                                                            <span
                                                                class="hide-menu">{{ __($sub->lang_title ?? $sub->title) }}</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <a class="sidebar-link" href="{{ menuUrl($child) }}">
                                                <i class="mdi {{ $child->icon }}"></i>
                                                <span
                                                    class="hide-menu">{{ __($child->lang_title ?? $child->title) }}</span>
                                            </a>
                                        @endif

                                    </li>
                                @endforeach
                            </ul>

                        </li>
                    @else
                        {{-- Single Menu --}}
                        <li class="sidebar-item {{ $active ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ menuUrl($menu) }}">
                                <i class="mdi {{ $menu->icon }}"></i>
                                <span class="hide-menu">{{ __($menu->lang_title ?? $menu->title) }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach

                {{-- Collapse Sidebar Button --}}
                <li class="sidebar-item d-lg-block">
                    <a class="sidebar-link collapse_menu" href="javascript:void(0)" data-sidebartype="mini-sidebar">
                        <i class="mdi mdi-arrow-all"></i>
                        <span class="hide-menu">{{ __('Collapse Menu') }}</span>
                    </a>
                </li>

            </ul>
        </nav>

        {{-- Logo Bottom --}}
        <div class="logo text-center">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">
                <b class="logo-icon">
                    <img src="{{ asset('assets/images/logo-mini.svg') }}" width="25" class="invert-image">
                </b>
                <b class="logo-text ms-2">
                    <img src="{{ asset('assets/images/logo.svg') }}" height="20" class="invert-image">
                </b>
            </a>
        </div>

    </div>
</aside>
