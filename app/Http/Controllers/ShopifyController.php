<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Shop; // Feltételezve, hogy van egy Shop modell az adatok tárolására
use Illuminate\Support\Facades\Log;

class ShopifyController extends Controller
{
    public function install()
    {
        $shop = request('shop');
        $redirectUrl = "https://{$shop}/admin/oauth/authorize?client_id=" . env('SHOPIFY_API_KEY') .
            "&scope=" . env('SHOPIFY_API_SCOPES') .
            "&redirect_uri=" . env('SHOPIFY_API_REDIRECT_URI');

        return redirect($redirectUrl);
    }

    public function callback(Request $request)
    {
        // Ellenőrizd, hogy van-e shop és code paraméter
        if (!$request->has('shop') || !$request->has('code')) {
            return response('Missing required parameters', 400);
        }
    
        $shop = $request->input('shop');
        $code = $request->input('code');
    
        try {
            // Token lekérése a Shopify API-tól
            $response = Http::post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => env('SHOPIFY_API_KEY'),
                'client_secret' => env('SHOPIFY_API_SECRET'),
                'code' => $code,
            ]);
    
            // Ellenőrizd a válasz sikerességét
            if ($response->failed()) {
                return response('Failed to fetch access token', 500);
            }
    
            // Token kinyerése a válaszból
            $data = $response->json();
            $accessToken = $data['access_token'];
    
            // Token mentése az adatbázisba
            Shop::updateOrCreate(
                ['shop_domain' => $shop], // Egyedi azonosító (pl. shop domain)
                ['access_token' => $accessToken] // Frissítendő adatok
            );
    
            // Webhook hozzáadása
            $webhookResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$shop}/admin/api/2024-01/webhooks.json", [
                'webhook' => [
                    'topic' => 'orders/update', // Az esemény típusa (pl. Order update)
                    'address' => 'https://api.codinspector.com/api/webhook/order-updated', // A webhook URL-je
                    'format' => 'json',
                ]
            ]);
    
            if ($webhookResponse->failed()) {
                Log::error('Webhook creation failed:', [
                    'status' => $webhookResponse->status(),
                    'body' => $webhookResponse->body(),
                    'headers' => $webhookResponse->headers(),
                ]);
                            return response('Failed to create webhook'. json_encode($webhookResponse->failed()), 500);
            }
    
            return response('Shopify app installed and webhook added successfully!', 200);
        } catch (\Exception $e) {
            return response('Error during Shopify callback: ' . $e->getMessage(), 500);
        }
    }
    


}
