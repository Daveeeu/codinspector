<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Shop; // Feltételezve, hogy van egy Shop modell az adatok tárolására

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

            return response('Shopify app installed successfully!', 200);
        } catch (\Exception $e) {
            return response('Error during Shopify callback: ' . $e->getMessage(), 500);
        }
    }


}
