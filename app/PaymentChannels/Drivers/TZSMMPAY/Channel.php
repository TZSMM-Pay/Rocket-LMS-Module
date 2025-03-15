<?php

namespace App\PaymentChannels\Drivers\TZSMMPAY;

use App\Models\Order;
use App\Models\PaymentChannel;
use App\PaymentChannels\BasePaymentChannel;
use App\PaymentChannels\IChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Channel extends BasePaymentChannel implements IChannel
{
    protected $api_key;
    protected $currency;

    protected array $credentialItems = [
        'api_key',
    ];

    /**
     * Channel constructor.
     * @param PaymentChannel $paymentChannel
     */
    public function __construct(PaymentChannel $paymentChannel)
    {
        $this->currency = currency();
        $this->setCredentialItems($paymentChannel);
    }

public function paymentRequest(Order $order)
{
    $user = $order->user;

    $queryParams = http_build_query([
        'api_key' => $this->api_key,
        'cus_name' => $user->full_name,
        'cus_email' => $user->email,
        'cus_number' => $user->mobile,
        'success_url' => url('cart'),
        'cancel_url' => url('cart'),
        'callback_url' => $this->makeCallbackUrl($order),
        'amount' => $order->total_amount,
        'currency' => $this->currency,
        'redirect' => 'true'
    ]);

    return 'https://tzsmmpay.com/api/payment/create?' . $queryParams;
}


    private function makeCallbackUrl(Order $order)
    {
        return route('payment_verify', ['gateway' => 'TZSMMPAY']);
    }

    public function verify(Request $request)
    {
       
        $transaction_id = $request->trx_id;
        if (!$transaction_id) {
            return;
        }

        $order = Order::where('id', $request->cus_city)->where('user_id',)->first();
        $user = $order->user;
        if (!$order || $order->status === Order::$paid) {
            return $order;
        }

        $response = Http::get('https://api.tzsmmpay.com/verify', [
            'api_key' => $this->api_key,
            'trx_id' => $transaction_id,
        ]);

        $data = $response->json();

        if ($response->successful() && isset($data['status']) && $data['status'] === 'Completed') {
            $order->update(['status' => Order::$paid]);
        } else {
            $order->update(['status' => Order::$fail]);
        }

        return $order;
    }
}
