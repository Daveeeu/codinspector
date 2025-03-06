<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ApiSetting;

class WebhookController extends Controller
{
    /**
     * Kezelje a Shopify rendelés frissítését.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleOrderUpdated(Request $request)
    {
        $shopifyOrderId = $request->input('admin_graphql_api_id');
        $existingOrder = DB::table('orders')->where('order_id', $shopifyOrderId)->first();

        if ($existingOrder) {
            return $this->handleExistingOrder($request, $existingOrder);
        } else {
            return $this->handleNewOrder($request);
        }
    }

/**
 * Kezelje a meglévő rendelést.
 *
 * @param Request $request
 * @param object $existingOrder
 * @return \Illuminate\Http\JsonResponse
 */
private function handleExistingOrder(Request $request, object $existingOrder)
{
    $data = json_decode($request->getContent(), true);

    $orderId = $data['id'] ?? 'unknown';
    $fulfillmentStatus = $data['fulfillment_status'] ?? null;
    $cancelReason = $data['cancel_reason'] ?? null;
    $cancelledAt = $data['cancelled_at'] ?? null;

    // Shop adatok betöltése
    $shopDomain = $request->header('X-Shopify-Shop-Domain');
    $settings = ApiSetting::where('shop_domain', $shopDomain)->first();

    if ($settings) {
        $outcome = 0;

        if ($fulfillmentStatus === 'fulfilled') {
            $outcome = 1;
        }elseif (!empty($cancelledAt) && $cancelReason === 'fraud') {
            $outcome = -1;
        }
        
        // Ha van releváns outcome, küldjük el a visszajelzést
        if ($outcome !== 0) {
            $this->sendFeedback($settings, $request, $orderId, $outcome);
        }
    }

    return response()->json(['message' => 'Webhook received'], 200);
}



    /**
     * Kezelje az új rendelést.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleNewOrder(Request $request)
    {
        $orderId = $request->input('admin_graphql_api_id');
        $response = $this->fetchThresholdData($request);

        if ($response->successful()) {
            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            $thresholdData = $response->json();
            DB::table('orders')->insert([
                'order_id' => $orderId,
                'status' => $thresholdData['status'],
                'threshold' => $thresholdData['threshold'],
                'shop_domain' => $shopDomain,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if($thresholdData['status'] == "0"){
            

                $this->addOrderRisk($request->input('id'), $shopDomain);
                $this->placeOrderOnHold($request->input('id'), $shopDomain);
                $this->addTagToShopifyOrder($shopDomain, $request->input('id'), "No");
            }

        
            
            return response()->json(['message' => 'Adatok sikeresen mentve!'], 200);
        }

        return response()->json(['error' => 'Threshold API hiba!'], 500);
    }

    /**
     * Ellenőrizze, hogy a tag-ok megfelelnek-e a beállításoknak.
     *
     * @param string $tags
     * @param object $settings
     * @return bool
     */
    private function checkTags(string $tags, object $settings): bool
    {
        return str_contains($tags, $settings['success_tag']) || str_contains($tags, $settings['reject_tag']);
    }

    /**
     * Határozza meg a kimenetet a tag-ok alapján.
     *
     * @param string $tags
     * @param object $settings
     * @return int
     */
    private function determineOutcome(string $tags, object $settings): int
    {
        $outcome = 0;
        if (str_contains($tags, $settings['reject_tag'])) {
            $outcome = -1;
        } elseif (str_contains($tags, $settings['success_tag'])) {
            $outcome = 1;
        }
        return $outcome;
    }

    /**
     * Küldje el a visszajelzést a megadott API-nak.
     *
     * @param object $settings
     * @param Request $request
     * @param string $orderId
     * @param int $outcome
     */
    private function sendFeedback(object $settings, Request $request, string $orderId, int $outcome)
    {
        Http::withHeaders([
            'X-Api-Key' => $settings['api_key'],
            'X-Api-Secret' => $settings['secret_api_key'],
        ])->get('https://' . $settings['api_domain'] . '/api/feedback', [
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'orderId' => $orderId,
            'outcome' => $outcome,
        ]);
    }

    /**
     * Töltse le a küszöbérték adatait.
     *
     * @param Request $request
     * @return \Illuminate\Http\Client\Response
     */
    private function fetchThresholdData(Request $request)
    {
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $settings = ApiSetting::where('shop_domain', $shopDomain)->first();
        return Http::withHeaders([
            'X-Api-Key' => $settings['api_key'],
            'X-Api-Secret' => $settings['secret_api_key'],
        ])->get('https://' . $settings['api_domain'] . '/api/threshold', [
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);
    }

    /**
     * Kapja meg a rendelést azonosítóval.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderById(Request $request)
    {
        $orderId = $request->input('order_id');
        $order = DB::table('orders')->where('order_id', $orderId)->first();

        if ($order) {
            return response()->json([
                'order_id' => $order->order_id,
                'status' => $order->status,
                'threshold' => $order->threshold,
            ], 200);
        }

        return response()->json(['error' => 'Order not found'], 404);
    }

    /**
     * Adjon hozzá egy tag-et egy Shopify rendeléshez.
     *
     * @param string $shopDomain
     * @param string $orderId
     * @param string $tag
     * @return bool
     */
    private function addTagToShopifyOrder(string $shopDomain, string $orderId, string $tag): bool
    {
        $shop = Shop::where('shop_domain', $shopDomain)->first();

        if (!$shop) {
            Log::error("Shop not found for domain: {$shopDomain}");
            return false;
        }

        $shopifyApiUrl = "https://{$shopDomain}/admin/api/2023-01/orders/{$orderId}.json";

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
            ])->get($shopifyApiUrl);

            if ($response->successful()) {
                $orderData = $response->json();
                $existingTags = explode(',', trim($orderData['order']['tags']));

                if (!in_array($tag, $existingTags)) {
                    array_push($existingTags, $tag);
                }

                $updateResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                ])->put($shopifyApiUrl, [
                    'order' => [
                        'tags' => implode(',', array_filter($existingTags)),
                    ],
                ]);

                if ($updateResponse->successful()) {
                    Log::info("Tag '{$tag}' hozzáadva a rendeléshez: {$orderId}");
                } else {
                    Log::error("Nem sikerült frissíteni a rendelést: {$updateResponse->body()}");
                }

                return true;
            } else {
                Log::error("Nem sikerült lekérni a rendelést: {$response->body()}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Hiba történt a tag hozzáadásakor: {$e->getMessage()}");
            return false;
        }
    }

    function addOrderRisk($orderId, $shopDomain) {
        $apiUrl = "https://{$shopDomain}/admin/api/2023-01/orders/{$orderId}/risks.json";
        $shop = Shop::where('shop_domain', $shopDomain)->first();

        if (!$shop) {
            Log::error("Shop not found for domain: {$shopDomain}");
            return false;
        }

        // Define the risk data
        $riskData = [
            'risk' => [
                'message' => 'This order has been flagged as high risk due to custom rules.',
                'recommendation' => 'cancel', // Options: "cancel", "investigate", "accept"
                'score' => 1.0, // Risk score (1.0 indicates high risk)
                'source' => 'External', // Use a valid source value
                'cause_cancel' => true, // Whether this risk should cause cancellation
                'display' => true, // Whether this risk should be displayed in the admin
            ],
        ];

        // Make the API request
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type' => 'application/json',
        ])->post($apiUrl, $riskData);

        if ($response->successful()) {
            return $response->json(); // Return the created risk data
        } else {
            Log::error("Failed to add order risk: {$response->body()}");
            return false;
        }
    }


    
    function placeOrderOnHold($orderId, $shopDomain) {
        // Lekérjük az adott shop adatait
        $shop = Shop::where('shop_domain', $shopDomain)->first();
    
        if (!$shop) {
            Log::error("Shop not found for domain: {$shopDomain}");
            return false;
        }
    
        // 1. Payment Pending státusz beállítása
        $paymentPendingUrl = "https://{$shopDomain}/admin/api/2023-01/orders/{$orderId}.json";
        $paymentData = [
            'order' => [
                'id' => $orderId,
                'financial_status' => 'pending', // Payment Pending státusz
            ],
        ];
    
        $paymentResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type' => 'application/json',
        ])->put($paymentPendingUrl, $paymentData);
    
        if ($paymentResponse->failed()) {
            Log::error("Failed to update payment status for order {$orderId}: {$paymentResponse->body()}");
            return false;
        }
    
        Log::info("Payment status updated to 'Pending' for order {$orderId}.");
    
        // 2. On Hold státusz beállítása (Fulfillment Order)
        $fulfillmentOrdersUrl = "https://{$shopDomain}/admin/api/2023-01/orders/{$orderId}/fulfillment_orders.json";
        
        // Fulfillment Order ID lekérése
        $fulfillmentOrdersResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type' => 'application/json',
        ])->get($fulfillmentOrdersUrl);
    
        if ($fulfillmentOrdersResponse->failed()) {
            Log::error("Failed to fetch fulfillment orders for order {$orderId}: {$fulfillmentOrdersResponse->body()}");
            return false;
        }
    
        $fulfillmentOrders = $fulfillmentOrdersResponse->json();
        
        if (empty($fulfillmentOrders['fulfillment_orders'])) {
            Log::error("No fulfillment orders found for order ID: {$orderId}");
            return false;
        }
    
        foreach ($fulfillmentOrders['fulfillment_orders'] as $fulfillmentOrder) {
            $fulfillmentOrderId = $fulfillmentOrder['id'];
            $onHoldUrl = "https://{$shopDomain}/admin/api/2023-01/fulfillment_orders/{$fulfillmentOrderId}/hold.json";
    
            $holdData = [
                'fulfillment_hold' => [
                    'reason' => 'high_risk_of_fraud', // Példa indok: "inventory_issue", "fraud_risk"
                    'reason_notes' => 'Order placed on hold for manual review.',
                ],
            ];
    
            $onHoldResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type' => 'application/json',
            ])->post($onHoldUrl, $holdData);
    
            if ($onHoldResponse->failed()) {
                Log::error("Failed to place fulfillment order {$fulfillmentOrderId} on hold: {$onHoldResponse->body()}");
                return false;
            }
    
            Log::info("Fulfillment order {$fulfillmentOrderId} placed on hold.");
        }
    
        return true;
    }
    


    function getFulfillmentOrderId($orderId, $shopDomain, $accessToken) {
        $apiUrl = "https://{$shopDomain}/admin/api/2023-01/orders/{$orderId}/fulfillment_orders.json";

        // API hívás a Fulfillment Orders lekéréséhez
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get($apiUrl);

        if ($response->successful()) {
            $fulfillmentOrders = $response->json()['fulfillment_orders'] ?? [];

            if (!empty($fulfillmentOrders)) {
                Log::info("Fulfillment orders retrieved for order {$orderId}");
                return $fulfillmentOrders; // Visszaadja az összes fulfillment order-t
            } else {
                Log::warning("No fulfillment orders found for order {$orderId}");
                return null;
            }
        } else {
            Log::error("Failed to retrieve fulfillment orders: {$response->body()}");
            return null;
        }
    }


    /**
     * Kezeli az ügyfél adatlekérési kéréseit (customers/data_request).
     */
    public function handleCustomerDataRequest(Request $request)
    {
    
        return response()->json(['error' => "We don't store user data"], 400);
    }

    /**
     * Kezeli az ügyfél adatainak törlési kéréseit (customers/redact).
     */
    public function handleCustomerDataErasure(Request $request)
    {
        return response()->json(['error' => "We don't store user data"], 400);
    }

    /**
     * Kezeli a bolt adatainak törlési kéréseit (shop/redact).
     */
    public function handleShopDataErasure(Request $request)
    {
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        Log::info("Shop data erasure request received for shop: {$shopDomain}");

        // A bolt adatok törlése az adatbázisból
        Shop::where('shop_domain', $shopDomain)->delete();

        DB::table('orders')->where('shop_domain', $shopDomain)->delete();

        return response()->json(['message' => 'Shop data erased successfully'], 200);
    }
}
