<div class="form-group">
    <label class="form-label">Doctor (no % commission)</label>
    <select class="form-input" name="doctor_id" id="dff-{{ $prefix }}-doctor-id" required>
        <option value="">Select doctor</option>
        @foreach ($doctors as $doctor)
        <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->code }} — {{ $doctor->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label class="form-label">Treatment</label>
    <select class="form-input" name="treatment_id" id="dff-{{ $prefix }}-treatment-id" required>
        <option value="">Select treatment</option>
        @foreach ($treatments as $treatment)
        <option value="{{ $treatment->id }}" @selected(old('treatment_id') == $treatment->id)>{{ $treatment->code }} — {{ $treatment->name }}</option>
        @endforeach
    </select>
</div>
<div class="dff-grid-2">
    <div class="form-group" style="margin:0;">
        <label class="form-label">Amount per treatment</label>
        <input class="form-input" type="number" step="0.01" min="0.01" name="fee_amount" id="dff-{{ $prefix }}-fee-amount" value="{{ old('fee_amount') }}" required>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Currency</label>
        <select class="form-input" name="currency" id="dff-{{ $prefix }}-currency" required>
            @foreach (\App\Support\ClinicRegistrationOptions::currencyCodes() as $code)
            <option value="{{ $code }}" @selected(old('currency', $defaultCurrency ?? 'AED') === $code)>{{ $code }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="dff-grid-2">
    <div class="form-group" style="margin:0;">
        <label class="form-label">Valid from</label>
        <input class="form-input" type="date" name="valid_from" id="dff-{{ $prefix }}-valid-from" value="{{ old('valid_from') }}">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Valid to</label>
        <input class="form-input" type="date" name="valid_to" id="dff-{{ $prefix }}-valid-to" value="{{ old('valid_to') }}">
    </div>
</div>
