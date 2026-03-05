@if($row->layers && $row->layers->count())
    @foreach ($row->layers as $layer)
        <span
            class="badge bg-secondary text-white mx-1 px-2 rounded-pill shadow-sm"
            style="font-size: 14px; letter-spacing: 0.5px;"
        >
            {{ $layer->layer_name }}
        </span>
    @endforeach
@else
    <span class="text-muted">—</span>
@endif
