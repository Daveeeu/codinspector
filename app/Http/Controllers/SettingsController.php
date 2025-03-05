<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Shop;
use App\Models\ApiSetting;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function showForm(Request $request)
    {
        // Lekérjük az aktuális bolt domainjét és tokenjét
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $shop = Shop::where('shop_domain', $shopDomain)->first();
        $settingsData = ApiSetting::where('shop_domain', $shopDomain)->first();

        if (!$shop) {
            $settingsData = [
                "api_domain" => "",
                "api_key" => "",
                "secret_api_key" => "",
            ];
            return view('settings-form', compact('settingsData'));
        }
        $accessToken = $shop->access_token;
        
        try {
            return view('settings-form', compact('settingsData'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error fetching shop data: ' . $e->getMessage()]);
        }
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'api_domain'      => 'required|string',
            'api_key'         => 'required|string',
            'secret_api_key'  => 'required|string',
        ]);
    
        // A shop domain értéke
        $shopDomain = $request->header('X-Shopify-Shop-Domain');

        $domain = $request->host();

        Log::info("domain: ".$shopDomain . "sec". $domain);
    
        // Hozzáadjuk a shop_domain-t az adatokhoz
        $data['shop_domain'] = $shopDomain;
    
        // Ellenőrizzük, hogy van-e már beállítás ehhez a shop_domain-hez
        $existingSetting = ApiSetting::where('shop_domain', $shopDomain)->first();
    
        if ($existingSetting) {
            // Ha van meglévő beállítás, akkor frissítjük
            $existingSetting->update($data);
        } else {
            // Ha nincs meglévő beállítás, akkor létrehozzuk
            ApiSetting::create($data);
        }
    
        // Visszairányítás az előző oldalra siker üzenettel
        return redirect()->back()->with('success', 'Beállítások elmentve!');
    }
    
}
