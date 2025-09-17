@if ($tasks->hasPages())
    <div class="mt-4 flex justify-center">
        {{ $tasks->appends(request()->query())->links() }}
    </div>
@endif
