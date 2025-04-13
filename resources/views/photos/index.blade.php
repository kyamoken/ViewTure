<x-app-layout>
    <!-- Alpine.js を使用する場合のみ（必要なら） -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js" defer></script>

    <div x-data="{ modalOpen: false, modalImg: '' }">
        <!--
          p-4 を削除または変更すると、左右の余白を減らせます。
          container や mx-auto を外すと画面いっぱいに広がります。
          例: <div class="p-4"> のみでもOK
        -->
        <div class="container mx-auto p-4">
            <h1 class="text-2xl font-bold mb-4 text-white">投稿一覧</h1>

            @if (session('success'))
                <div class="mb-4 text-green-500">
                    {{ session('success') }}
                </div>
            @endif

            @if ($photos->count())
                <!--
                    grid-cols-1, sm:grid-cols-2, md:grid-cols-3 ...
                    画面幅によって段数を変化させる例
                -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach ($photos as $photo)
                        <!-- カード部分 -->
                        <div
                            class="border rounded overflow-hidden bg-gray-900 cursor-pointer"
                            @click.prevent="
                                modalImg='{{ asset('storage/' . $photo->image_path) }}';
                                modalOpen = true
                            "
                        >
                            <!-- 16:9 のアスペクト比 -->
                            <div class="aspect-video overflow-hidden">
                                <img
                                    src="{{ asset('storage/' . $photo->image_path) }}"
                                    alt="{{ $photo->title }}"
                                    class="w-full h-full object-cover"
                                >
                            </div>

                            <!-- カード下部テキスト -->
                            <div class="p-2">
                                <h2 class="text-lg font-semibold text-white">
                                    {{ $photo->title }}
                                </h2>
                                <p class="text-sm text-white">
                                    投稿者: {{ $photo->user->name ?? '不明' }}
                                </p>
                                @if ($photo->tags)
                                    <p class="text-sm text-white">
                                        タグ: {{ $photo->tags }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                {{ $photos->links() }}
            @else
                <p class="text-white">まだ投稿はありません。</p>
            @endif
        </div>

        <!-- モーダル（前回と同様） -->
        <div
            x-show="modalOpen"
            class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-75 z-50"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <!-- 背景クリックで閉じる -->
            <div class="absolute inset-0" @click="modalOpen = false"></div>
            <!-- モーダルコンテンツ -->
            <div class="relative z-10">
                <img
                    :src="modalImg"
                    alt="Full image"
                    class="max-w-full max-h-screen rounded shadow-lg"
                >
                <button
                    @click="modalOpen = false"
                    class="absolute top-2 right-2 text-white bg-gray-800 bg-opacity-75 rounded-full p-2 hover:bg-opacity-100"
                >
                    ×
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
