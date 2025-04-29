<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class PhotoController extends Controller
{
    /**
     * 投稿フォーム表示
     */
    public function create()
    {
        return view('photos.create');
    }

    /**
     * 画像投稿処理（AI画像分析・タグ付け + 翻訳）
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpg,png,jpeg,gif|max:8192',
        ]);

        $file              = $request->file('image');
        $originalExtension = $file->getClientOriginalExtension();
        $fileName          = time() . '_' . uniqid() . '.' . $originalExtension;

        // Storage に保存
        Storage::disk('public')->putFileAs('photos', $file, $fileName);

        // DB に仮登録
        $photo = Photo::create([
            'user_id'    => auth()->id(),
            'title'      => $request->title,
            'image_path' => 'photos/' . $fileName,
            'tags'       => null,
        ]);

        // Azure Computer Vision API 呼び出し
        $endpoint = config('services.azure_cv.endpoint');
        $apiKey   = config('services.azure_cv.key');
        $url      = rtrim($endpoint, '/') . '/vision/v3.2/analyze?visualFeatures=Tags';

        try {
            $client       = new Client();
            $imageContent = Storage::disk('public')->get($photo->image_path);

            $response = $client->post($url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Content-Type'             => 'application/octet-stream',
                ],
                'body'    => $imageContent,
            ]);

            $result    = json_decode($response->getBody(), true);
            $tagsArray = $this->extractTags($result);

            // タグを日本語に翻訳
            $translatedTags = $this->translateTags($tagsArray);
            $photo->tags = implode(',', $translatedTags);
            $photo->save();
        } catch (\Exception $e) {
            Log::error('Azure CV API error: ' . $e->getMessage());
        }

        return redirect()
            ->route('photos.index')
            ->with('success', 'Photo uploaded and analyzed successfully!');
    }

    /**
     * 投稿一覧表示
     */
    public function index()
    {
        $photos = Photo::with('user')->latest()->paginate(10);
        return view('photos.index', compact('photos'));
    }

    /**
     * Azure から返された英語タグを抽出（信頼度 0.5 以上、最大5件）
     */
    private function extractTags(array $result): array
    {
        $tags = [];
        if (isset($result['tags'])) {
            foreach ($result['tags'] as $tag) {
                if (count($tags) >= 5) {
                    break;
                }
                if ($tag['confidence'] >= 0.5) {
                    $tags[] = $tag['name'];
                }
            }
            if (empty($tags) && isset($result['tags'][0])) {
                $tags[] = $result['tags'][0]['name'];
            }
        }
        return $tags;
    }

    /**
     * Microsoft Translator Text API で英語タグを日本語に翻訳
     *
     * @param array $tags
     * @return array
     */
    private function translateTags(array $tags): array
    {
        $endpoint = config('services.azure_translator.endpoint');
        $apiKey   = config('services.azure_translator.key');
        $region   = config('services.azure_translator.region');
        $url      = rtrim($endpoint, '/') . '/translate?api-version=3.0&to=ja';

        $client = new Client();
        $body   = array_map(fn($t) => ['Text' => $t], $tags);

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key'    => $apiKey,
                    'Ocp-Apim-Subscription-Region' => $region,
                    'Content-Type'                 => 'application/json',
                ],
                'json'    => $body,
            ]);

            $result         = json_decode($response->getBody(), true);
            $translatedTags = [];
            foreach ($result as $item) {
                $translatedTags[] = $item['translations'][0]['text'] ?? '';
            }
            return $translatedTags;
        } catch (\Exception $e) {
            Log::error('Translator API error: ' . $e->getMessage());
            return $tags;
        }
    }
}
