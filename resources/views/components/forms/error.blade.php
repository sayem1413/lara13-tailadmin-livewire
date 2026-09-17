@props(['for' => null, 'bag' => 'default'])

@php $messages = $errors->getBag($bag); @endphp

@if ($for && $messages->has($for))
    <p {{ $attributes->merge(['class' => 'mt-1.5 text-sm text-red-600 dark:text-red-400']) }}>
        {{ $messages->first($for) }}
    </p>
@endif
