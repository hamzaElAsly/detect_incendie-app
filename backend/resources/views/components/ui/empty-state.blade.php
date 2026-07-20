@props(['title', 'description' => null, 'icon' => '◇'])
<div {{ $attributes->class('grid min-h-40 place-items-center p-8 text-center') }}>
 <div>
  <span class="mx-auto grid size-12 place-items-center rounded-xl bg-slate-100 text-xl text-slate-500 dark:bg-white/5" aria-hidden="true">{{ $icon }}</span>
  <h3 class="mt-4 font-bold">{{ $title }}</h3>
  @if($description)<p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $description }}</p>@endif
  <div class="mt-4 empty:hidden">{{ $slot }}</div>
 </div>
</div>
