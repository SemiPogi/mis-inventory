<div class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
    @if(session('success'))
        <x-toast type="success">{{ session('success') }}</x-toast>
    @endif
    @if(session('error'))
        <x-toast type="error">{{ session('error') }}</x-toast>
    @endif
    @if($errors->any())
        <x-toast type="error">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-toast>
    @endif
</div>
