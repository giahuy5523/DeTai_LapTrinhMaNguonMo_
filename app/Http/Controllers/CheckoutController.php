<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\CartSnapshot;
use App\Support\Money;
use App\Support\SimulatedQr;
use App\Support\StockReservationService;
use App\Support\VoucherService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    // Hiển thị trang thanh toán
    public function index()
    {
        $cart = CartSnapshot::refresh(session()->get('cart', []));
        session()->put('cart', $cart);

        if (empty($cart)) {
            session()->forget('checkout_voucher');

            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        try {
            $subtotal = Money::total($cart);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Giá trị giỏ hàng vượt giới hạn. Vui lòng giảm số lượng.']);
        }

        $service = app(StockReservationService::class);
        $quote = session('checkout_quote');
        $snapshot = CartSnapshot::version($cart);
        if ($quote && hash_equals($quote['snapshot'], $snapshot)) {
            try {
                $service->validate(auth()->id(), $quote['version'], $cart);
                $checkoutVersion = $quote['version'];
                $reservation = $service->reserve(auth()->id(), $checkoutVersion, $cart);
            } catch (ValidationException $exception) {
                $service->cancel(auth()->id(), $quote['version']);
                session()->forget(['checkout_quote', 'checkout_voucher']);

                return redirect()->route('cart.index')->withErrors($exception->errors());
            }
        } else {
            if ($quote) {
                $service->cancel(auth()->id(), $quote['version']);
            }
            $checkoutVersion = bin2hex(random_bytes(32));
            try {
                $reservation = $service->reserve(auth()->id(), $checkoutVersion, $cart);
            } catch (ValidationException $exception) {
                session()->forget('checkout_voucher');

                return redirect()->route('cart.index')->withErrors($exception->errors());
            }
            session()->put('checkout_quote', ['version' => $checkoutVersion, 'snapshot' => $snapshot]);
        }

        $subtotalCents = Money::cents($subtotal);
        $appliedVoucher = $this->resolveSessionVoucher($snapshot, $subtotalCents);
        $discountAmount = $appliedVoucher['discount_amount'] ?? '0.00';
        $total = $appliedVoucher['payable_amount'] ?? $subtotal;

        $serverNowCarbon = now();
        $expiresCarbon = Carbon::parse($reservation['expires_at']);
        $reservationExpiresAt = $expiresCarbon->toIso8601String();
        $reservationExpiresLabel = $expiresCarbon->format('H:i:s d/m/Y');
        $serverNow = $serverNowCarbon->toIso8601String();
        $qrReference = SimulatedQr::reference($checkoutVersion);
        $qrPayload = SimulatedQr::payload($qrReference, $total, $appliedVoucher['code'] ?? null);
        $qrSvg = SimulatedQr::svg($qrReference, $total, $appliedVoucher['code'] ?? null);

        return view('checkout.index', compact(
            'cart',
            'subtotal',
            'discountAmount',
            'appliedVoucher',
            'total',
            'checkoutVersion',
            'reservationExpiresAt',
            'reservationExpiresLabel',
            'serverNow',
            'qrReference',
            'qrPayload',
            'qrSvg'
        ));
    }

    // Áp dụng mã giảm giá (chỉ kiểm tra và xem trước, không trừ lượt)
    public function applyVoucher(Request $request)
    {
        $cart = CartSnapshot::refresh(session()->get('cart', []));
        session()->put('cart', $cart);

        if (empty($cart)) {
            session()->forget('checkout_voucher');

            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        $paymentQuery = $request->input('payment_method') === 'bank_transfer'
            ? ['payment_method' => 'bank_transfer']
            : [];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'voucher_code' => ['required', 'string', 'max:32'],
        ], [
            'voucher_code.required' => 'Vui lòng nhập mã voucher.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('checkout.index', $paymentQuery)
                ->withErrors($validator)
                ->withInput($request->only('voucher_code', 'payment_method'));
        }

        $validated = $validator->validated();

        try {
            $subtotalCents = Money::cents(Money::total($cart));
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Giá trị giỏ hàng vượt giới hạn. Vui lòng giảm số lượng.']);
        }

        try {
            $voucherQuote = app(VoucherService::class)->quote($validated['voucher_code'], $subtotalCents);
        } catch (ValidationException $exception) {
            session()->forget('checkout_voucher');

            return redirect()->route('checkout.index', $paymentQuery)
                ->withErrors($exception->errors())
                ->withInput($request->only('voucher_code', 'payment_method'));
        }

        session()->put('checkout_voucher', [
            'code' => $voucherQuote['code'],
            'snapshot' => CartSnapshot::version($cart),
        ]);

        return redirect()->route('checkout.index', $paymentQuery)
            ->with('success', 'Đã áp dụng mã giảm giá '.$voucherQuote['code'].' (-'.Money::format($voucherQuote['discount_amount']).' đ).');
    }

    // Gỡ mã giảm giá đang chọn khỏi phiên thanh toán
    public function removeVoucher(Request $request)
    {
        session()->forget('checkout_voucher');

        $paymentQuery = $request->input('payment_method') === 'bank_transfer'
            ? ['payment_method' => 'bank_transfer']
            : [];

        return redirect()->route('checkout.index', $paymentQuery)
            ->with('success', 'Đã gỡ mã giảm giá.');
    }

    // Xử lý đặt hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 .()\-]{7,18}[0-9]$/D', function ($attribute, $value, $fail) {
                if (is_string($value) && (preg_match_all('/[0-9]/', $value) < 9 || preg_match_all('/[0-9]/', $value) > 15)) {
                    $fail('Số điện thoại phải chứa từ 9 đến 15 chữ số.');
                }
            }],
            'checkout_version' => ['required', 'string', 'size:64'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:cod,bank_transfer'],
            'voucher_code' => ['nullable', 'string', 'max:32'],
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            $replayedOrder = Order::where('checkout_token', $validated['checkout_version'])
                ->where('user_id', auth()->id())
                ->first();
            if ($replayedOrder) {
                session()->put('last_checkout_order_id', $replayedOrder->id);

                return redirect()->route('checkout.success')
                    ->with('success', 'Đặt hàng thành công!')
                    ->with('order_id', $replayedOrder->id);
            }

            return redirect()->route('cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        if (CartSnapshot::normalize($cart) !== $cart) {
            session()->put('cart', CartSnapshot::refresh($cart));
            session()->forget(['checkout_quote', 'checkout_voucher']);
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng đã được chuẩn hóa. Vui lòng xem lại trang thanh toán.']);
        }
        $quote = session('checkout_quote');
        $cartSnapshot = CartSnapshot::version($cart);
        if (! $quote || ! hash_equals($quote['version'], $validated['checkout_version'])
            || ! hash_equals($quote['snapshot'], $cartSnapshot)) {
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng đã thay đổi. Vui lòng xem lại trang thanh toán.']);
        }

        $voucherCode = $this->resolveRequestedVoucherCode($validated['voucher_code'] ?? null, $cartSnapshot);

        // A single lock order prevents opposite cart insertion orders causing deadlocks.
        ksort($cart, SORT_NUMERIC);

        try {
            $order = Order::where('checkout_token', $validated['checkout_version'])->where('user_id', auth()->id())->first()
                ?? app(StockReservationService::class)->consume(auth()->id(), $validated['checkout_version'], $cart, function () use ($validated, $cart, $voucherCode) {
                    $orderItems = [];
                    $seenProducts = [];
                    $subtotalCents = 0;

                    // Kiểm tra giá, trạng thái bán và tồn kho từ database
                    foreach ($cart as $id => $item) {
                        $quantity = filter_var(
                            $item['quantity'] ?? null,
                            FILTER_VALIDATE_INT
                        );

                        if (! $quantity || $quantity < 1) {
                            throw ValidationException::withMessages([
                                'cart' => 'Số lượng sản phẩm trong giỏ hàng không hợp lệ.',
                            ]);
                        }

                        $product = Product::where('is_active', true)
                            ->whereKey($id)
                            ->lockForUpdate()
                            ->first();

                        if (! $product) {
                            throw ValidationException::withMessages([
                                'cart' => 'Một sản phẩm không còn tồn tại hoặc đã ngừng bán.',
                            ]);
                        }

                        if (isset($seenProducts[$product->id])) {
                            throw ValidationException::withMessages(['cart' => 'Giỏ hàng chứa sản phẩm trùng. Vui lòng mở lại giỏ hàng.']);
                        }
                        $seenProducts[$product->id] = true;

                        if ($quantity > $product->quantity) {
                            throw ValidationException::withMessages([
                                'cart' => 'Sản phẩm "'.$product->name
                                    .'" không đủ số lượng trong kho. Hiện còn: '
                                    .$product->quantity.'.',
                            ]);
                        }

                        if (Money::cents($product->price) !== Money::cents($item['price'])) {
                            throw ValidationException::withMessages(['cart' => 'Giá sản phẩm đã thay đổi. Vui lòng mở lại trang thanh toán để xác nhận giá mới.']);
                        }
                        $lineSubtotal = Money::line($product->price, $quantity);
                        if ($lineSubtotal > Money::MAX_CENTS - $subtotalCents) {
                            throw ValidationException::withMessages(['cart' => 'Tổng giá trị đơn vượt giới hạn. Vui lòng giảm số lượng.']);
                        }
                        $subtotalCents += $lineSubtotal;

                        $orderItems[] = [
                            'product' => $product,
                            'quantity' => $quantity,
                            'subtotal' => $lineSubtotal,
                        ];
                    }

                    // Kiểm tra và trừ lượt voucher trong cùng DB transaction
                    $voucherQuote = $voucherCode !== null
                        ? app(VoucherService::class)->consume($voucherCode, $subtotalCents)
                        : null;

                    // Tạo đơn hàng, giữ trạng thái ban đầu là pending
                    $order = Order::create([
                        'user_id' => auth()->id(),
                        'voucher_id' => $voucherQuote['voucher_id'] ?? null,
                        'voucher_code' => $voucherQuote['code'] ?? null,
                        'customer_name' => $validated['customer_name'],
                        'phone' => $validated['phone'],
                        'address' => $validated['address'],
                        'note' => $validated['note'] ?? null,
                        'total_amount' => $voucherQuote['payable_amount'] ?? Money::decimal($subtotalCents),
                        'subtotal_amount' => Money::decimal($subtotalCents),
                        'discount_amount' => $voucherQuote['discount_amount'] ?? '0.00',
                        'payment_method' => $validated['payment_method'],
                        'checkout_token' => $validated['checkout_version'],
                        'status' => 'pending',
                    ]);

                    // Lưu chi tiết đơn hàng và trừ tồn kho
                    foreach ($orderItems as $item) {
                        $product = $item['product'];

                        $order->items()->create([
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $item['quantity'],
                            'unit_price' => $product->price,
                            'subtotal' => Money::decimal($item['subtotal']),
                        ]);

                        $product->decrement('quantity', $item['quantity']);
                    }

                    return $order;
                });
        } catch (QueryException|ValidationException $exception) {
            if ($exception instanceof QueryException && ! str_contains($exception->getMessage(), 'checkout_token')) {
                throw $exception;
            }

            // A concurrent replay can see depleted stock before reaching the unique index.
            // The transaction has rolled back here; read the committed order outside its snapshot.
            $duplicate = Order::where('checkout_token', $validated['checkout_version'])
                ->where('user_id', auth()->id())->first();
            if (! $duplicate) {
                if ($exception instanceof ValidationException) {
                    $expiredOrInactive = DB::table('stock_reservations')
                        ->where('user_id', auth()->id())
                        ->where('token', $validated['checkout_version'])
                        ->where(function ($query) {
                            $query->where('status', '!=', 'active')
                                ->orWhere('expires_at', '<=', now());
                        })
                        ->exists();
                    if ($expiredOrInactive) {
                        app(StockReservationService::class)->cancel(auth()->id(), $validated['checkout_version']);
                        session()->forget(['checkout_quote', 'checkout_voucher']);
                        throw $exception->redirectTo(route('cart.index'));
                    }

                    if (array_key_exists('voucher_code', $exception->errors())) {
                        session()->forget('checkout_voucher');
                    }
                }

                throw $exception;
            }
            $order = $duplicate;
        }

        // Chỉ xóa giỏ hàng sau khi transaction thành công
        session()->forget(['cart', 'checkout_quote', 'checkout_voucher']);

        // Lưu ID để trang thành công lấy đúng đơn hàng
        session()->put('last_checkout_order_id', $order->id);

        return redirect()->route('checkout.success')
            ->with('success', 'Đặt hàng thành công!')
            ->with('order_id', $order->id);
    }

    // Trang thông báo đặt hàng thành công
    public function success()
    {
        $orderId = session('last_checkout_order_id');

        if (! $orderId) {
            return redirect()->route('store.index');
        }

        $order = Order::whereKey($orderId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $order) {
            return redirect()->route('store.index');
        }

        return view('checkout.success', compact('order'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSessionVoucher(string $snapshot, int $subtotalCents): ?array
    {
        $voucherSession = session('checkout_voucher');
        if (! is_array($voucherSession) || ! is_string($voucherSession['code'] ?? null) || ! is_string($voucherSession['snapshot'] ?? null)) {
            if ($voucherSession !== null) {
                session()->forget('checkout_voucher');
            }

            return null;
        }

        if (! hash_equals($voucherSession['snapshot'], $snapshot)) {
            session()->forget('checkout_voucher');

            return null;
        }

        try {
            return app(VoucherService::class)->quote($voucherSession['code'], $subtotalCents);
        } catch (ValidationException $exception) {
            session()->forget('checkout_voucher');
            $firstMessage = $exception->errors()['voucher_code'][0] ?? 'Mã giảm giá đã chọn không còn hợp lệ và đã được gỡ bỏ.';
            session()->flash('warning', $firstMessage);

            return null;
        }
    }

    private function resolveRequestedVoucherCode(?string $inputCode, string $cartSnapshot): ?string
    {
        $normalizedInput = VoucherService::normalizeCode($inputCode);
        if ($normalizedInput !== '') {
            return $normalizedInput;
        }

        $voucherSession = session('checkout_voucher');
        if (is_array($voucherSession)
            && is_string($voucherSession['code'] ?? null)
            && is_string($voucherSession['snapshot'] ?? null)
            && hash_equals($voucherSession['snapshot'], $cartSnapshot)) {
            $normalizedSession = VoucherService::normalizeCode($voucherSession['code']);

            return $normalizedSession !== '' ? $normalizedSession : null;
        }

        return null;
    }
}
