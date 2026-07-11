@if ($showConfigurationBack ?? false)
<nav style="margin-bottom:1rem;" aria-label="Configuration navigation">
    <a href="{{ \App\Support\ConfigurationReturnContext::backUrl() }}" class="btn btn-secondary btn-sm">{{ __('navigation.back_to_configuration') }}</a>
</nav>
@endif
