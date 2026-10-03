<div class="border border-[#2A2A2A] p-4 text-xs text-gray-400">
    <p class="text-gray-200 font-semibold mb-2">{{ __('reservas.privacy_layer.title') }}</p>
    <dl class="grid sm:grid-cols-[8rem_1fr] gap-x-4 gap-y-1">
        <dt class="text-gray-300">{{ __('reservas.privacy_layer.controller_label') }}</dt>
        <dd>{{ __('reservas.privacy_layer.controller') }}</dd>
        <dt class="text-gray-300">{{ __('reservas.privacy_layer.purpose_label') }}</dt>
        <dd>{{ __('reservas.privacy_layer.purpose') }}</dd>
        <dt class="text-gray-300">{{ __('reservas.privacy_layer.legal_basis_label') }}</dt>
        <dd>{{ __('reservas.privacy_layer.legal_basis') }}</dd>
        <dt class="text-gray-300">{{ __('reservas.privacy_layer.recipients_label') }}</dt>
        <dd>{{ __('reservas.privacy_layer.recipients') }}</dd>
        <dt class="text-gray-300">{{ __('reservas.privacy_layer.rights_label') }}</dt>
        <dd>{{ __('reservas.privacy_layer.rights') }} <a href="{{ route('privacidad') }}" class="text-gold underline">{{ __('reservas.privacy_layer.more') }}</a>.</dd>
    </dl>
</div>
