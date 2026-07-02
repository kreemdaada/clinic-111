@if (request('from') === \App\Support\ConfigurationReturnContext::VALUE)
<input type="hidden" name="return_from" value="{{ \App\Support\ConfigurationReturnContext::VALUE }}">
@endif
