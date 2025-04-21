<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class PhotoController extends Controller
{
    public function create()
    {
        return view('photos.create');
    }

    // 画像投稿処理を行うメソッド（AIによる画像分析・タグ付け＋翻訳付き）
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
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

        // AIによる画像分析（Azure Computer Vision API）
        try {
            $client = new Client();
            $endpoint = env('AZURE_CV_ENDPOINT');
            $apiKey   = env('AZURE_CV_KEY');
            // 分析の対象は Tags。APIバージョンは v3.2 の例
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

            // 結果の 'tags' 配列が存在すれば、信頼度が0.5以上のものを最大5件取得
            if (isset($result['tags'])) {
                foreach ($result['tags'] as $tag) {
                    if (count($tagsArray) >= 5) {
                        break;
                    }
                    if ($tag['confidence'] >= 0.5) {
                        $tagsArray[] = $tag['name'];
                    }
                }
                // 条件に合致するタグがない場合、最初のタグを強制的に取得
                if (empty($tagsArray) && isset($result['tags'][0])) {
                    $tagsArray[] = $result['tags'][0]['name'];
                }
            }

            // 英語のタグを日本語に翻訳する処理
            $translatedTags = $this->translateTags($tagsArray);
            $photo->tags = implode(',', $translatedTags);
            $photo->save();
        } catch (\Exception $e) {
            // エラーはログ出力して処理を続行
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

    /**
     * 英語のタグ配列を Microsoft Translator Text API を利用して日本語に翻訳する。
     *
     * @param array $tags
     * @return array
     */
    private function translateTags(array $tags): array
    {
        $endpoint = env('AZURE_TRANSLATOR_ENDPOINT');
        $apiKey   = env('AZURE_TRANSLATOR_KEY');
        $region   = env('AZURE_TRANSLATOR_REGION');
        // Translator API のエンドポイント（APIバージョン3.0、翻訳先 'ja'）
        $url = $endpoint . '/translate?api-version=3.0&to=ja';

        $client = new Client();
        $body = [];
        foreach ($tags as $tag) {
            $body[] = ['Text' => $tag];
        }

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Ocp-Apim-Subscription-Region' => $region,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $result = json_decode($response->getBody(), true);
            $translatedTags = [];
            // 取得結果から翻訳結果を抽出
            foreach ($result as $translationItem) {
                if (isset($translationItem['translations'][0]['text'])) {
                    $translatedTags[] = $translationItem['translations'][0]['text'];
                }
            }
            return $translatedTags;
        } catch (\Exception $e) {
            \Log::error('Translator API error: ' . $e->getMessage());
            // エラーがあった場合は英語のタグをそのまま返す
            return $tags;
        }
    }
}
