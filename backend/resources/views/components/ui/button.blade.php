@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'href' => null])
@php
    $classes = [
        'btn-primary' => $variant === 'primary', 'btn-secondary' => $variant === 'secondary',
        'btn-success' => $variant === 'success', 'btn-warning' => $variant === 'warning',
        'btn-danger' => $variant === 'danger', 'btn-ghost' => $variant === 'ghost',
        'btn-outline' => $variant === 'outline', 'btn-link' => $variant === 'link',
        'btn-icon' => $variant === 'icon', 'btn-xs' => $size === 'xs',
        'btn-sm' => $size === 'sm', 'btn-lg' => $size === 'lg',
    ];
@endphp
@if($href)<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>@else<button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>@endif
