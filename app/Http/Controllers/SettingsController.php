<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Shop;
use App\Models\ApiSetting;

class SettingsController extends Controller
{
    public function showForm()
    {
        // Lekérjük az aktuális bolt domainjét és tokenjét
        $shopDomain = 'uvclone.myshopify.com';
        $shop = Shop::where('shop_domain', $shopDomain)->first();
        $settingsData = ApiSetting::where('shop_domain', $shopDomain)->first();

        if (!$shop) {
            return back()->withErrors(['error' => 'Shop not found']);
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
            'success_tag'     => 'required|string',
            'reject_tag'      => 'required|string',
        ]);
    
        // A shop domain értéke
        $shopDomain = 'uvclone.myshopify.com';
    
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
