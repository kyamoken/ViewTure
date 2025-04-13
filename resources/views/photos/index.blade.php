<!-- resources/views/photos/index.blade.php -->

<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4 text-white">投稿一覧</h1>

        @if (session('success'))
            <div class="mb-4 text-green-500">
                {{ session('success') }}
            </div>
        @endif

        @if ($photos->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                @foreach ($photos as $photo)
                    <div class="border p-2 rounded">
                        <img src="{{ asset('storage/' . $photo->image_path) }}" alt="{{ $photo->title }}" class="w-full h-auto mb-2">
                        <h2 class="text-lg font-semibold text-white">{{ $photo->title }}</h2>
                        <p class="text-sm text-white">投稿者: {{ $photo->user->name ?? '不明' }}</p>
                        @if ($photo->tags)
                            <p class="text-sm text-white">タグ: {{ $photo->tags }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            {{ $photos->links() }}
        @else
            <p class="text-white">まだ投稿はありません。</p>
        @endif
    </div>
</x-app-layout>
