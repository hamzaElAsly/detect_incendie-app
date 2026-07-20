@props(['variant' => 'primary', 'type' => 'button'])
<button type="{{ $type }}" {{ $attributes->class(['btn-primary' => $variant === 'primary','btn-secondary' => $variant === 'secondary','btn-danger' => $variant === 'danger','btn-ghost' => $variant === 'ghost']) }}>{{ $slot }}</button>
