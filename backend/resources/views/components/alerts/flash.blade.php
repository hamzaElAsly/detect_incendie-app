@if(session('success'))<div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800" role="status">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800" role="alert">{{ session('error') }}</div>@endif
