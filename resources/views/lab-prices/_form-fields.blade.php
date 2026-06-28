<div class="form-group">
    <label class="form-label">Laboratory</label>
    <select class="form-input" name="lab_id" id="lp-{{ $prefix }}-lab-id" required>
        <option value="">Select lab</option>
        @foreach ($labs as $lab)
        <option value="{{ $lab->id }}" @selected(old('lab_id') == $lab->id)>{{ $lab->code }} — {{ $lab->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label class="form-label">Treatment</label>
    <select class="form-input" name="treatment_id" id="lp-{{ $prefix }}-treatment-id" required>
        <option value="">Select treatment</option>
        @foreach ($treatments as $treatment)
        <option value="{{ $treatment->id }}" @selected(old('treatment_id') == $treatment->id)>{{ $treatment->code }} — {{ $treatment->name }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label class="form-label">Doctor override</label>
    <select class="form-input" name="doctor_id" id="lp-{{ $prefix }}-doctor-id">
        <option value="">General (all doctors)</option>
        @foreach ($doctors as $doctor)
        <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->code }} — {{ $doctor->name }}</option>
        @endforeach
    </select>
</div>
<div class="lp-grid-2">
    <div class="form-group" style="margin:0;">
        <label class="form-label">Unit cost</label>
        <input class="form-input" type="number" step="0.01" min="0.01" name="unit_cost" id="lp-{{ $prefix }}-unit-cost" value="{{ old('unit_cost') }}" required>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Currency</label>
        <select class="form-input" name="currency" id="lp-{{ $prefix }}-currency" required>
            @foreach (\App\Support\ClinicRegistrationOptions::currencyCodes() as $code)
            <option value="{{ $code }}" @selected(old('currency', $defaultCurrency ?? 'AED') === $code)>{{ $code }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="lp-grid-2">
    <div class="form-group" style="margin:0;">
        <label class="form-label">Valid from</label>
        <input class="form-input" type="date" name="valid_from" id="lp-{{ $prefix }}-valid-from" value="{{ old('valid_from') }}">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Valid to</label>
        <input class="form-input" type="date" name="valid_to" id="lp-{{ $prefix }}-valid-to" value="{{ old('valid_to') }}">
    </div>
</div>
