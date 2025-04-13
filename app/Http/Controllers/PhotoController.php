<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class PhotoController extends Controller
{
    // 画像投稿フォームを表示するメソッド
    public function create()
    {
        return view('photos.create');
    }

    // 画像投稿処理を行うメソッド（AIによる画像分析・タグ付け付き）
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            // ここでは一旦 8MB 程度の上限に設定（必要に応じて変更）
            'image' => 'required|image|mimes:jpg,png,jpeg,gif|max:8192',
        ]);

        $file = $request->file('image');
        $originalExtension = $file->getClientOriginalExtension();
        // 保存用のファイル名を生成
        $fileName = time() . '_' . uniqid() . '.' . $originalExtension;

        // 画像をそのまま Storage の public/photos フォルダに保存
        Storage::disk('public')->putFileAs('photos', $file, $fileName);

        // Photo レコードを tags は未設定で作成
        $photo = Photo::create([
            'user_id'    => auth()->id(),
            'title'      => $request->title,
            'image_path' => 'photos/' . $fileName,
            'tags'       => null,
        ]);

        // AIによる画像分析（Microsoft Azure Computer Vision API を使用した例）
        try {
            $client = new Client();
            // Endpoint と APIキーは .env に設定しておく
            // 例: AZURE_CV_ENDPOINT=https://your-region.api.cognitive.microsoft.com
            //     AZURE_CV_KEY=your_subscription_key
            $endpoint = env('AZURE_CV_ENDPOINT');
            $apiKey   = env('AZURE_CV_KEY');
            // 分析の対象は Tags。API バージョンは v3.2 の例です。
            $url = $endpoint . '/vision/v3.2/analyze?visualFeatures=Tags';

            // Storageから画像のバイナリデータを取得
            $imageContent = Storage::disk('public')->get($photo->image_path);

            $response = $client->post($url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $imageContent,
            ]);

            $result = json_decode($response->getBody(), true);
            $tagsArray = [];

            // 結果の 'tags' 配列が存在すれば、最初の5件(信頼度が0.5以上など)を取得
            if (isset($result['tags'])) {
                foreach ($result['tags'] as $tag) {
                    // 最大5つまで
                    if (count($tagsArray) >= 5) {
                        break;
                    }
                    if ($tag['confidence'] >= 0.5) {
                        $tagsArray[] = $tag['name'];
                    }
                }
                // 万が一条件に合致するタグがない場合、最初のタグを強制的に取得
                if (empty($tagsArray) && isset($result['tags'][0])) {
                    $tagsArray[] = $result['tags'][0]['name'];
                }
            }

            // リレーショナルなデータベースでは、複数タグはカンマ区切りの文字列で保存する例
            $photo->tags = implode(',', $tagsArray);
            $photo->save();
        } catch (\Exception $e) {
            // エラーはログ出力して処理を続行（必要に応じてハンドリングしてください）
            \Log::error('Azure CV API error: ' . $e->getMessage());
        }

        return redirect()->route('photos.index')->with('success', 'Photo uploaded and analyzed successfully!');
    }

    // 投稿された写真一覧を表示するメソッド
    public function index()
    {
        // 最新の投稿順に一覧を取得（ユーザー情報も eager load）
        $photos = Photo::with('user')->latest()->paginate(10);
        return view('photos.index', compact('photos'));
    }
}
