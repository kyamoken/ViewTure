<!-- resources/views/photos/create.blade.php -->

<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">画像投稿</h1>

        @if ($errors->any())
            <div class="mb-4">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li class="text-red-500">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('photos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label for="title" class="block text-lg">タイトル</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required
                       class="w-full p-2 border rounded">
            </div>

            <div class="mb-4">
                <label for="image" class="block text-lg">画像ファイル</label>
                <input type="file" name="image" id="image" accept="image/*" required
                       class="w-full p-2 border rounded">
            </div>

            <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded">投稿</button>
        </form>
    </div>
</x-app-layout>
