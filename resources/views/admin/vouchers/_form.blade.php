<div class="mb-3">
    <label class="form-label" for="code">Mã voucher</label>
    <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ \App\Support\FormInput::old('code', $voucher->code ?? '') }}" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,31}" required>
    <div class="form-text">Tự chuẩn hóa thành chữ in hoa; 3–32 ký tự gồm chữ, số, gạch ngang hoặc gạch dưới.</div>
    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row g-3">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="discount_type">Hình thức giảm</label>
        <select class="form-select @error('discount_type') is-invalid @enderror" id="discount_type" name="discount_type" required>
            <option value="percentage" @selected(\App\Support\FormInput::old('discount_type', $voucher->discount_type ?? 'percentage') === 'percentage')>Phần trăm</option>
            <option value="fixed" @selected(\App\Support\FormInput::old('discount_type', $voucher->discount_type ?? '') === 'fixed')>Số tiền cố định</option>
        </select>
        @error('discount_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="discount_value">Giá trị giảm</label>
        <input class="form-control @error('discount_value') is-invalid @enderror" id="discount_value" name="discount_value" type="number" min="0.01" step="0.01" value="{{ \App\Support\FormInput::old('discount_value', $voucher->discount_value ?? '') }}" required>
        @error('discount_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
<div class="mb-3">
    <label class="form-label" for="max_discount_amount">Giảm tối đa (bắt buộc với phần trăm)</label>
    <input class="form-control @error('max_discount_amount') is-invalid @enderror" id="max_discount_amount" name="max_discount_amount" type="number" min="0.01" step="0.01" value="{{ \App\Support\FormInput::old('max_discount_amount', $voucher->max_discount_amount ?? '') }}">
    @error('max_discount_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row g-3">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="minimum_order_amount">Đơn hàng tối thiểu (đ)</label>
        <input class="form-control @error('minimum_order_amount') is-invalid @enderror" id="minimum_order_amount" name="minimum_order_amount" type="number" min="0" step="0.01" value="{{ \App\Support\FormInput::old('minimum_order_amount', $voucher->minimum_order_amount ?? '0.00') }}" required>
        @error('minimum_order_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="usage_limit">Giới hạn lượt (để trống nếu không giới hạn)</label>
        <input class="form-control @error('usage_limit') is-invalid @enderror" id="usage_limit" name="usage_limit" type="number" min="1" step="1" value="{{ \App\Support\FormInput::old('usage_limit', $voucher->usage_limit ?? '') }}">
        @error('usage_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="starts_at">Bắt đầu</label>
        <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" type="datetime-local" value="{{ \App\Support\FormInput::old('starts_at', isset($voucher) ? $voucher->starts_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" required>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="ends_at">Kết thúc</label>
        <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" type="datetime-local" value="{{ \App\Support\FormInput::old('ends_at', isset($voucher) ? $voucher->ends_at->format('Y-m-d\TH:i') : now()->addMonth()->format('Y-m-d\TH:i')) }}" required>
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
<div class="form-check form-switch mb-4">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked((bool) \App\Support\FormInput::old('is_active', $voucher->is_active ?? true))>
    <label class="form-check-label" for="is_active">Đang hoạt động</label>
</div>
